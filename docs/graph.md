# The graph behind DuckTail

How a GraphQL request turns into Cypher, why it is batched, how the graph stays consistent with Postgres, and what the API does in each failure. Rules for writing code are in [`AGENTS.md`](../AGENTS.md) and [`.claude/rules/06-grafo-e-graphql.md`](../.claude/rules/06-grafo-e-graphql.md); when this text and the tests disagree, the tests win and this text gets fixed.

## 1. The problem, in product terms

A visitor opens the page of the Church of São Francisco de Assis in Ouro Preto. The page shows the location chain (Brazil › Minas Gerais › Ouro Preto), the categories, "related places and why", "near here", "friends who went" and "companies that operate here" and "tours that stop here". Each of those is a different walk on the graph starting at the same node, and a search result page asks the same walks for 20 places at once.

Two things can go wrong:

- **N+1.** Resolving `related` once per place on screen is 20 round trips to Neo4j for one page; nested one level deeper, 400.
- **Inconsistency.** A visit is written to Neo4j by a user who exists in Postgres. There is no transaction spanning both databases.

## 2. From a GraphQL document to Cypher

```
POST /graphql
  → OptionalAuthenticateMiddleware        (no token: anonymous; bad token: 401 envelope; good: Auth::setUser)
  → Lighthouse parses and validates the document against graphql/schema.graphql (depth ≤ 12)
  → for each field: App\Base\GraphQL\ResolverProvider → App\GraphQL\Resolvers\{Type}\{Field}Resolver
  → resolver: GraphAuth (session?) → DTO::fromArgs (ArgsValidator, ceilings) → Query::get / Action::handle
  → Query/Action: Cypher with parameters → App\Base\Graph\Graph → Bolt → Neo4j
  → rows are plain PHP arrays (the Cypher projects primitives), returned as the field value
  → nested fields without a resolver class read the array key with the same name
```

Why the schema has no directives: the web generates its types by reading `graphql/schema.graphql` directly, and unknown directives (`@field`, `@guard`) break that. Binding by convention (`Place.related` → `Resolvers\Place\RelatedResolver`) keeps the file pure SDL and the wiring as discoverable as a controller.

Why the Cypher projects primitives (`p {.id, .name, latitude: p.location.latitude}`, `toString(v.date)`): the result needs no conversion layer, and the shape of each GraphQL type comes from one place (`App\Base\Graph\Cypher`). A raw node or point would leak driver objects into the resolvers.

## 3. Batching (the N+1 fix)

Every nested field that reads the graph (`Place.location`, `Place.related`, `Place.nearby`, `Location.companies`, `Tour.stops`, `User.visited`, `Itinerary.stops`…) returns a `GraphQL\Deferred` from `App\Base\GraphQL\BatchLoader` instead of a value:

1. webonyx resolves the document breadth-first. While resolving the 20 `place` nodes of a page, each `related` resolver calls `$loader->load($place['id'])` and gets a `Deferred` back. Nothing has hit Neo4j yet.
2. When webonyx starts settling deferred values, the first one triggers the loader's `fetch` with **all 20 pending ids**. The Query runs once: `UNWIND $ids AS id MATCH (s:Place {id: id}) … RETURN id, collect(…)` and returns `[id => value]`.
3. The other 19 deferreds read from the loaded map.

One loader instance exists per field path (list indexes removed) **and per argument set**, so `nearby(radiusKm: 1)` and `nearby(radiusKm: 5)` in the same document never share results. Keys requested after a batch was fetched (a deeper level) go into a new batch. `tests/Unit/BatchLoaderTest.php` proves both behaviors. The cost is bounded by the document: at most one query per field path per level, not per node.

Exception, on purpose: `Location.places` is a paginated connection per location (`SKIP/LIMIT` per parent does not batch well), and a page shows one location.

## 4. Consistency between Postgres and Neo4j

| Data | Source of truth | In the other store |
|---|---|---|
| Users, passwords, tokens, registration requests | Postgres | Graph has `(:User {id, name})` only |
| Places, locations, tours, categories, themes, companies, edges | Neo4j (rebuilt from Wikidata + seeder) | — |
| Visits, wish list, follows, itineraries | Neo4j | — |

The `User` node is the only shared fact, and it is **eventually consistent** by design:

1. `UserObserver` reacts to `saved`/`deleted` **after the Postgres commit** (`ShouldHandleEventsAfterCommit`). A user whose transaction rolled back never reaches the graph (`UserSyncTest` › "does not sync before the commit…").
2. A failure to reach Neo4j is logged (`graph.user_sync_failed`) and swallowed: approving a registration or deleting a user must not fail because the graph is down (`UserSyncTest` › "keeps the Postgres write…").
3. Every mutation `MERGE`s the user node before writing, so a missed sync heals itself on the user's first action (`UserGraphTest` › "recreates the user node…").
4. `ducktail:sync-users` (daily, and on demand) creates/renames every user node and deletes nodes of users that no longer exist, with their itineraries.

The failure that remains: a user deleted while Neo4j was down keeps a node (with visits) until the next reconciliation. Visits are only shown to followers and recommendations are anonymous, so the exposure is small and bounded by a day.

## 5. Transactions in the graph

`Graph::transaction(fn(GraphTransaction $t) => …)` opens an explicit transaction, commits at the end and **rolls back on any exception**, including an `ApiException` thrown by a business rule (`CreateItineraryAction` throws when a stop's place does not exist, after the itinerary node was already created in the same transaction: nothing is persisted). A `TransientError` from Neo4j (deadlock between write transactions) re-runs the callback up to `NEO4J_TRANSACTION_ATTEMPTS` times; the callback has no external side effects.

The driver's own `writeTransaction` is not used: in laudis 3.x it rethrows non-Neo4j exceptions without rolling back and retries transient errors on the same, already failed transaction.

Idempotent writes (`markVisited`, `wantToVisit`, `follow`, imports) are `MERGE` on properties with a uniqueness constraint, which Neo4j serializes by locking the constrained value, so two concurrent identical requests produce one relationship.

Bulk recomputation (`NEAR`, `RELATED_TO`) runs in batches of 200 places, one transaction each, and deletes old edges 5,000 at a time: a single transaction for a whole state exceeds `dbms.memory.transaction.total.max` (this happened on the first real import of Minas Gerais).

## 6. Situation → what the API does

| Situation | Where it is detected | GraphQL answer | Why |
|---|---|---|---|
| `place(id)` / `location(id)` / `company(id)` / `tour(id)` unknown | Query returns `null` | `data.place: null`, no error | The type is nullable; "not found" is a normal state of a page |
| Acting on an unknown place (`markVisited`, `suggestedItinerary`) | Action/resolver | `NOT_FOUND` (404) | The client asked to do something with it |
| No path between two places | `shortestPath` returns null | `[]` | Absence of a connection is an answer, not an error |
| Invalid argument (rating 6, radius above the ceiling, `first: 51`, foreign cursor, blank title, duplicated stop slot) | DTO `fromArgs` | `VALIDATION` (422) with the field's message | Same semantics as a FormRequest |
| Field requiring a session, no token | `GraphAuth::require` | `UNAUTHENTICATED` (401) | Public pages keep working for anonymous users |
| Token present but invalid/expired | Middleware, before GraphQL | HTTP 401, REST envelope | One refresh-and-retry path for REST and GraphQL |
| Itinerary with an unknown place | Action, inside the transaction | `VALIDATION` `itineraries.invalid_place`; nothing written | All or nothing |
| Neo4j unreachable | Driver exception | `INTERNAL` (500), logged with `request_id`/`user_id`; `/up` answers 500 | Unexpected; must page someone |
| Too many requests | `throttle:graphql` | HTTP 429, REST envelope, `Retry-After` | Before any work is done |
| Document too deep | Lighthouse validation | GraphQL validation error | A recursive document would walk the whole graph |

## 7. Checklist for the next graph feature

- [ ] Contract change agreed with the web, schema still free of directives, descriptions updated.
- [ ] Resolver class per field, thin; reads in a `Query` (`UNWIND $ids` when nested), writes in an `Action`.
- [ ] Every value as a Cypher parameter; projections from `Cypher`; `LIMIT` on every list; ceiling on every client number.
- [ ] Several writes in one `Graph::transaction()`; a test proves the rollback.
- [ ] New uniqueness or lookup → `GraphSchema` (add only) and `ducktail:graph-migrate`.
- [ ] Messages in the three `lang/*/messages.php`.
- [ ] Feature test against the test Neo4j: shape, validation, `NOT_FOUND`, session.
