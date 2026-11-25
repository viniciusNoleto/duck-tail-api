# DuckTail API

API of [DuckTail](../duck-tail-web), a general tourism site. Attractions belong to **locations**, and a location can be the child of another (Brazil → Minas Gerais → Belo Horizonte; Portugal → Lisbon), so a country, a state and a city are all the same kind of thing. **Tourism companies** offer **tours**, and a tour passes through attractions in order. The central question is "I want to pass through A, B and C: which tours do that?" (`toursThrough`). Around it: places **related** to or **near** the one you are looking at, which **friends** have been there, and itineraries. The graph (Neo4j) is how the data is stored and walked; the web never shows it as a graph.

Built with **Laravel 13** (PHP 8.5), **Neo4j 5** for the domain, **GraphQL** ([Lighthouse](https://lighthouse-php.com)) as its API and **PostgreSQL 16** for users and sessions. The name comes from a migratory duck: routes are flight paths.

[![CI](../../actions/workflows/ci.yml/badge.svg)](../../actions/workflows/ci.yml)

## Why a graph, and why GraphQL

The questions the product asks are traversals: "places one or two hops away through a shared author or theme", "who visited this also visited…", "shortest path between two places", "friends (FOLLOWS) who VISITED this", "places of the same theme within a radius, ordered by distance". In SQL each is a recursive CTE or a self-join chain; in Cypher each is one readable query (see [`app/Queries/Graph`](app/Queries/Graph)). This is also a portfolio project: the goal is to show a graph database and GraphQL working together behind a conventional, well-tested Laravel codebase.

- **Neo4j** holds places, locations, tours, categories, themes, companies, visits, itineraries and every connection between them. The PHP driver is [`laudis/neo4j-php-client`](https://github.com/neo4j-php/neo4j-php-client) (Bolt). `@neo4j/graphql` is Node-only, so the resolvers are written by hand in Cypher.
- **GraphQL** fits a graph: the client asks for `place { related { place { nearby { … } } } }` in one round trip. The schema, [`graphql/schema.graphql`](graphql/schema.graphql), is the contract with the web app, which generates its TypeScript types from the file with graphql-codegen. It is plain SDL, **without Lighthouse directives**, so the codegen reads it as is; each field is bound by convention to a resolver class.
- **PostgreSQL** keeps users and authentication exactly like the white-label-stock-api (opaque tokens, rotation with reuse detection, admin-approved registration), over REST. The graph only has a `User` node with the same `id` and the name, kept in sync after every commit.
- **The web app** (Next.js, `../duck-tail-web`) never touches a database: its server calls `POST /graphql` and `/api/auth/*` with `Authorization: Bearer <access_token>`, keeping the tokens in httpOnly cookies on its side. No CORS is needed.

## Stack

- [Laravel 13](https://laravel.com). REST: Controller → FormRequest → DTO ([spatie/laravel-data](https://spatie.be/docs/laravel-data)) → Action → Eloquent. GraphQL: Resolver → DTO (`fromArgs`) → Action (Cypher writes) or Query (Cypher reads) → `App\Base\Graph\Graph`. No repositories. Conventions are in [`AGENTS.md`](AGENTS.md) and [`.claude/rules/`](.claude/rules/README.md); the graph mechanics are explained for humans in [`docs/graph.md`](docs/graph.md).
- Neo4j 5.26 (LTS, Community): uniqueness constraints, point index (distance queries), full-text index with accent folding.
- PostgreSQL 16: users, tokens, registration requests.
- [Pest 5](https://pestphp.com): feature tests against a real Postgres **and** a real Neo4j, architecture tests, multi-process concurrency tests.
- Docker: nginx + php-fpm + scheduler + Postgres + Neo4j (+ a second Neo4j only for tests, locally).

## Quality gates

Every push and pull request runs [CI](.github/workflows/ci.yml); `make ci` runs the same steps locally:

| Step | Tool | Command |
|---|---|---|
| Style | Pint ([`pint.json`](pint.json)) | `make lint` |
| Static analysis | Larastan level 5 ([`phpstan.neon`](phpstan.neon)), no baseline | `make analyse` |
| Vulnerable dependencies | `composer audit` | `make audit` |
| Tests | Pest: `Unit` (helpers, error mapping, retries, batch loader, **architecture rules**), `Feature` (every REST endpoint and GraphQL field against Postgres + Neo4j, error paths, schema contract) and `Concurrency` (forked processes racing on the same refresh token) | `make test` |
| Coverage | pcov, line coverage of `app/`, **minimum 95%** (currently ~96.5%) | `make test-coverage` |

The tests run in CI **inside the production PHP image**, with Postgres and Neo4j as job services. Style, static analysis and the audit run on `setup-php`. Shell scripts are checked with shellcheck.

## REST: sessions and users

Every REST response uses the same envelope, with `message` translated into the web app's three locales:

```json
{ "success": true, "message": { "pt-br": "…", "es-mx": "…", "en-us": "…" }, "data": {} }
```

Errors always carry `data: null`:

| Status | When |
|---|---|
| 400 | malformed JSON |
| 401 | no session, invalid credentials, or an invalid/expired token (also on `/graphql`) |
| 403 | admin-only route, or signing in while the registration is still pending |
| 404 | not found (including invalid ids) and unknown routes |
| 409 | e-mail already taken, or a concurrent change that survived the retries |
| 422 | invalid payload or business rule |
| 429 | rate limit (`Retry-After` header), including `/graphql` |
| 500 | unexpected error |
| 503 | maintenance during a deploy |

| Method | Path | Session | Effect |
|---|---|---|---|
| `POST` | `/api/auth/login` | — | `{email, password}` → session (below). 401 wrong credentials, 403 pending registration. |
| `POST` | `/api/auth/register` | — | `{name ≤150, email, password 8..255}` → 201, creates a **registration request**, not a user. 409 e-mail taken. |
| `POST` | `/api/auth/token/refresh` | — | `{refresh_token}` → new session (rotation). 401 invalid/expired/reused. |
| `POST` | `/api/auth/logout` | optional | Revokes the Bearer access token and the `{refresh_token}` in the body. |
| `GET` | `/api/auth/me` | user | `{user}` |
| `PUT` | `/api/auth/password` | user | `{current_password, password}` → new session; every other session ends. |
| `GET` | `/api/users` | admin | Paginated (`search`, `page`, `per_page`): `{items, meta}` |
| `DELETE` | `/api/users/{id}` | admin | Deletes a regular user (admin → 422); tokens and the graph node (visits, itineraries) go with it. |
| `PUT` | `/api/users/{id}/password` | admin | Resets a regular user's password and ends their sessions. |
| `GET` | `/api/registration-requests` | admin | Pending registrations, oldest first. |
| `POST` | `/api/registration-requests/{id}/approve` | admin | Creates the user (201); the graph `User` node is created after the commit. |
| `DELETE` | `/api/registration-requests/{id}` | admin | Rejects (deletes) the request. |
| `GET` | `/up` | — | 200 only if Laravel, Postgres **and** Neo4j answer. |

Every response carries an `X-Request-Id` header (a valid incoming one is reused), and every log line of the request includes it and the `user_id`.

## GraphQL: the domain

`POST /graphql` with `{ "query": "…", "variables": { … } }`. The full contract, with descriptions, is [`graphql/schema.graphql`](graphql/schema.graphql):

| Field | Session | What it answers |
|---|---|---|
| `place(id)` | — | A place, or `null`. Nested: `location` (the most specific one, with `ancestors`), `tours`, `categories`, `themes`, `related(limit)` (with the **reason**: same author, same style, curated, same theme; same location first, then a shared category, best rating, with a photo), `nearby(radiusKm ≤ 20)`, `friendsWhoVisited` (empty without a session), `companies`, `rating` (average of the visit ratings, one decimal, `null` without any), `ratingCount` and `myVisit` (the session user's visit, date and rating; `null` without a session or a visit). |
| `searchPlaces(term, category, categoryId, first ≤ 50, after)` | — | Full-text search on name and description (prefix per word, accent-insensitive) and/or category (by name or id), cursor-paginated. |
| `search(term, limit ≤ 20)` | — | Search-as-you-type over places, locations, categories, companies and tours (up to 5 of each); exact name first, then names starting with the term (also inside each kind, before the cut at 5; the full-text query boosts the name over the description); categories and locations before places. |
| `category(id)` / `categories(limit ≤ 50)` | — | A category (opaque UUID id, `name` in pt-br, `names { ptBr esMx enUs }` in every API language, falling back to pt-br; `icon`: an Iconify icon name picked from the name by keyword, `CategoryIconEnum`; `image`: curated or the photo of its most sought place, `placeCount`) / the ones with the most places. |
| `nearbyPlaces(lat, lng, radiusKm ≤ 50)` | — | "Near me", by distance. |
| `featuredPlaces(locationId, limit ≤ 50)` | — | Places with a photo, best average rating first (unrated last), then most sought (tours ×10, visits ×5, relations ×1), in a location and every location below it, or everywhere. |
| `tourSuggestions(placeIds, limit ≤ 50)` | — | Tour stops not in `placeIds` (0–10), for the tour finder: best average rating, then with a photo, then most tours shared with `placeIds`, then most tours. |
| `toursThrough(placeIds[1..10], limit ≤ 50)` | — | Tours that stop at at least one of the places: the ones through **all** of them first (`coversAll`), then the ones covering more; `matchedPlaceIds` says which. Unknown ids are ignored. |
| `suggestedItinerary(placeId, theme, radiusKm ≤ 100)` | — | Places of the theme around the starting point, nearest first. |
| `recommendations(limit)` | user | "Who visited what you visited also visited…", most shared first. |
| `location(id)` / `locations(parentId, kind, limit)` | — | A location (`parent`, `ancestors`, `children`, `placeCount`, `places(term, categoryId)` paginated — searched by name with the exact name first, filtered by category —, `categories` of its places, `companies`, `tours` — all counting the locations below) or `null` / the children of a parent, every location of a `kind`, or the roots, most places first. |
| `company(id)` / `tour(id)` | — | Tourism company (`baseLocation`, `tours`, `places`) / tour (`company`, `stops {order, place}`, `durationMinutes`, `price`, `currency`), or `null`. |
| `me` | — | The session user (`visited`, `wantsToVisit`, `itineraries`, `following`), or `null`. |
| `markVisited(placeId, rating 1..5)` | user | Records (or updates) the visit with today's date; removes it from "want to visit". |
| `removeVisitRating(placeId)` | user | Clears the rating of the visit; the visit and its date stay (idempotent). |
| `wantToVisit(placeId)` | user | Adds to the wish list (idempotent). |
| `unwantToVisit(placeId)` | user | Removes from the wish list (idempotent). |
| `follow(userId)` | user | Follows another user (idempotent; not yourself). |
| `createItinerary(input)` | user | Itinerary with stops `{placeId, day, order}`; all or nothing. |

Errors follow the GraphQL format (HTTP 200) with the REST vocabulary in `extensions`:

```json
{ "errors": [{ "message": "Lugar não encontrado.", "path": ["markVisited"],
  "extensions": { "code": "NOT_FOUND", "category": "not_found", "status": 404, "key": "messages.places.not_found",
                  "messages": { "pt-br": "Lugar não encontrado.", "es-mx": "Lugar no encontrado.", "en-us": "Place not found." } } }] }
```

`code` is `UNAUTHENTICATED`, `NOT_FOUND`, `VALIDATION`, `CONFLICT` or `INTERNAL` (no trace or internal message, ever). A missing session on a field that requires one is `UNAUTHENTICATED`; an **invalid or expired token** is answered before GraphQL runs with **HTTP 401 in the REST envelope**, so the client has a single "refresh and retry" path for REST and GraphQL. Documents deeper than 12 levels are rejected; `/graphql` is rate-limited per token (or per IP when anonymous).

## The graph

```
(Place)-[:LOCATED_IN]->(Location)-[:WITHIN]->(Location)…      (Place)-[:HAS_CATEGORY]->(Category)   (Place)-[:PART_OF]->(Theme)
(Place)-[:RELATED_TO {kind, value}]->(Place)                   (Place)-[:NEAR {distanceM}]->(Place)
(Company)-[:BASED_IN|SERVES]->(Location)   (Company)-[:OPERATES_AT]->(Place)   (Company)-[:OFFERS]->(Tour)-[:STOPS_AT {order}]->(Place)
(User)-[:VISITED {date, rating}]->(Place)   (User)-[:WANTS_TO_VISIT]->(Place)   (User)-[:FOLLOWS]->(User)
(User)-[:CREATED]->(Itinerary)-[:INCLUDES {day, order}]->(Place)
```

Constraints and indexes ([`App\Base\Graph\GraphSchema`](app/Base/Graph/GraphSchema.php)) are applied by `php artisan ducktail:graph-migrate`: unique `Place.id`, `Place.wikidataId`, `Location.id`, `Location.wikidataId`, `Company.id`, `Tour.id`, `User.id`, `Itinerary.id`…, a point index on `Place.location`, and a full-text index on `Place[name, description]` with accent folding. [`docs/graph.md`](docs/graph.md) explains how a GraphQL document becomes batched Cypher and how the `User` node is kept in sync.

**Data.** Places come from [Wikidata](https://www.wikidata.org) (CC0), never typed in by hand:

| Command | Effect |
|---|---|
| `php artisan ducktail:import --area=BR-MG [--limit=5000]` | SPARQL for any country subdivision, by its ISO 3166-2 code (P300: `BR-MG`, `PT-11`, `AR-C`): tourist attractions (P31/P279* Q570116) **or** listed heritage (P1435) with coordinates, inside a city/municipality directly under the subdivision; builds the location chain country (P17) → subdivision → city with `WITHIN`; image (P18), types → categories (named in pt-br — the `pt-br` label, or the `pt` one with Brazilian accents —, es and en; a re-import moves places out of categories they no longer have and deletes categories left without places), architects (P84) and styles (P149). `MERGE` by `wikidataId`: running again updates, never duplicates. Public id = the QID. |
| `php artisan ducktail:proximity --radius=3000` | Recomputes `NEAR` between places (point index, in batches). |
| `php artisan ducktail:relate` | Recomputes `RELATED_TO`: same author anywhere; same style only within the same location (a style alone would link hundreds of baroque churches to each other). Curated relations are kept. |
| `php artisan ducktail:sync-users` | Reconciles graph `User` nodes with Postgres (also daily). |

Heritage listings were added to the original query because the attraction subtree alone missed almost every historic church, which is the product's showcase (Aleijadinho in Ouro Preto and Congonhas). A full Minas Gerais import brings ~3,600 places in ~15 s; proximity at 3 km adds ~390k `NEAR` edges in ~12 s.

The **seeder** (`make db-seed`) creates a small sample graph without the importer: a location tree (Brazil → Minas Gerais and Rio de Janeiro → six + one cities; Portugal → Lisbon), eighteen places, six themes, six **fictional** tourism companies (`.example` domains) and their nine tours, then `RioSaoPauloGraphSeeder` adds São Paulo state and Rio/São Paulo cities (Niterói, Petrópolis, Paraty, Santos, Campos do Jordão), 37 places, nine more fictional companies and thirteen tours, plus — only with `SEED_DEMO_USERS=true` — two demo users who follow each other and have visits.

## Guarantees and the tests that prove them

| Rule | How it is enforced | Test (and its reach) |
|---|---|---|
| The schema file is the contract and codegen can read it | No directives; binding by convention; every `Query`/`Mutation` field has a resolver class | `tests/Feature/GraphQL/SchemaTest.php` (parses the file, builds the Lighthouse schema) |
| No N+1 on nested lists | `BatchLoader` per field + Queries with `UNWIND $ids` | `tests/Unit/BatchLoaderTest.php` proves N loads → one fetch (behavioral, for the loader); that each nested resolver uses it is a code convention checked in review |
| Resolvers never hold Cypher; Queries never write; the driver is only used through `Graph`; no repositories; Cypher never interpolates `$dto`/`$args` | Architecture rules | `tests/Unit/ArchitectureTest.php` (static; the regex checks are heuristics) |
| Itinerary is all or nothing | One `Graph::transaction()` with explicit rollback on any exception | `UserGraphTest` › "writes nothing when one of the places does not exist", `GraphCommandsTest` › "rolls back every write…" |
| The graph mirrors Postgres users, only after commit, and a graph outage never breaks Postgres | `UserObserver` (`ShouldHandleEventsAfterCommit`), errors logged as `graph.user_sync_failed`; mutations `MERGE` the node; daily `ducktail:sync-users` | `tests/Feature/Graph/UserSyncTest.php` (approve, rename, delete, rollback, Neo4j down, reconcile) |
| Search input is never a Lucene query; every radius and page size has a ceiling | `lucene_prefix_query()`; `RadiusDTO`/`LimitDTO`/`PageDTO` | `HelpersTest`, `PlaceQueriesTest` |
| Tests never touch the development graph | `TestCase` switches to `NEO4J_TESTING_URI` and refuses to run if it is missing or equal to `NEO4J_URI` | Every Feature test (the graph is wiped before each one) |
| Errors are translated and never leak internals | `ApiErrorHandler`; Lighthouse `debug = NONE` | `GraphQLErrorsTest` (Neo4j down → `INTERNAL`, logged with request and user ids) |
| Session security (token rotation, reuse detection, dedupe, rate limits, constant-time login) | Same implementation as white-label-stock-api | `tests/Feature/Auth`, `tests/Concurrency/RefreshSessionConcurrencyTest.php` |

## Authentication

Login and refresh return the session in the body:

```json
{ "user": { "id": 1, "name": "…", "email": "…", "is_admin": true }, "access_token": "…", "refresh_token": "…", "expires_in": 900, "refresh_expires_in": 2592000 }
```

- **Opaque tokens** (32 random bytes, not JWT); only their SHA-256 is stored. Access 15 min, refresh 30 days; expired tokens pruned daily.
- **Rotation with reuse detection.** Every refresh rotates inside a family; presenting a rotated token revokes the family, including its access tokens, and logs `auth.refresh_token_reused`.
- **Concurrent refreshes** within 10 s get the same pair (SSR + browser refreshing together). *Trade-off, accepted on purpose:* a stolen token replayed inside that window is not detected as reuse; without the window real users would be logged out several times a day.
- **Rate limits:** login 5/min per e-mail+IP and 20/min per IP; registration 3/min and 10/h per IP; refresh 10/min per token and 300/min per IP; password change 5/min per token; GraphQL 240/min per token (anonymous: per IP) and 1,200/min per IP. The client IP comes from `X-Forwarded-For` only from private networks (`TRUSTED_PROXIES`).
- **E-mail enumeration.** Login spends exactly one bcrypt check on every path. Registration answers 409 for a taken e-mail (no transactional e-mail exists to say "check your inbox"); the rate limit bounds probing.
- **Graph privacy.** The `User` node holds only `id` and `name`; e-mail and password never reach Neo4j.

### Permissions (a decision, not an omission)

Two roles. **Admins** approve registrations and manage users. **Users** mark visits, follow people and create itineraries; what they write is **theirs** (their visits, their wish list, their itineraries), so there is nothing to protect between users except privacy, which is covered: private itineraries are only returned to their owner, and `friendsWhoVisited` only shows people the user follows. Places, locations, companies and tours are read-only through the API (they come from Wikidata and the seeder). When curation by staff arrives (editing companies, curated relations), it gets its own role and middleware, like `admin`.

## Local setup

Requirements: Docker + Docker Compose (PHP and Composer run inside the containers).

```bash
cp .env.example .env   # fill SEED_ADMIN_*; optionally SEED_DEMO_USERS=true + SEED_DEMO_PASSWORD
make install           # build, start, composer install, key, migrate (Postgres + graph), seed
make graph-import      # optional: real places from Wikidata (area=BR-MG radius=3000)
```

Containers: `duck-tail-api-nginx` (API at `http://localhost:8080`), `-php`, `-scheduler`, `-postgres` (`localhost:5432`), `-neo4j` (Browser at `http://localhost:7474`, Bolt `7687`) and `-neo4j-testing` (no port, tmpfs: used only by the tests). The `${DB_DATABASE}_testing` database is created on the first start of the Postgres volume.

| Command | Effect |
|---|---|
| `make up` / `make down` / `make restart` | Start / stop / restart the containers |
| `make shell` / `make in` / `make tinker` / `make cypher-shell` | PHP shell / pick a container / Laravel REPL / Cypher shell on the dev graph |
| `make migrate` / `make migrate-fresh` / `make db-seed` | Migrations + graph constraints / rebuild Postgres / seed (admin, sample graph, demo users) |
| `make graph-import area=BR-MG radius=3000` / `make graph-sync-users` | Wikidata import + `NEAR` + `RELATED_TO` / reconcile graph users |
| `make graphql-schema` | Validate the schema |
| `make test` / `make test-coverage` | Pest (`make test TEST="--filter=places"`) / with coverage, failing below 95% |
| `make lint` / `make lint-fix` / `make analyse` / `make audit` / `make ci` | Quality gates |
| `make backup` / `make backup-verify` / `make restore file=…` | Postgres dump / prove it restores / restore it |
| `make logs` / `make logs-docker` | Laravel log / container output |

## Deploy

Same procedure as the white-label-stock-api (Nginx Proxy Manager in front; `docker-compose.production.yml` publishes no port; Neo4j only on the internal network).

**First install.** `.env` with `APP_ENV=production`, `APP_DEBUG=false`, `DOCKER_COMPOSE_FILE=docker-compose.production.yml`, strong `DB_PASSWORD` and `NEO4J_PASSWORD`, `NEO4J_HEAP_SIZE`/`NEO4J_PAGECACHE_SIZE`, `GRAPHQL_INTROSPECTION=false`, `LOG_STACK=stderr` + JSON formatter, `LOG_SLACK_WEBHOOK_URL`, `PROXY_NETWORK`. Then `make install-production`, and `make graph-import` for real data.

**Updates.** `make deploy` (or `make deploy REF=<tag>`): maintenance → Postgres backup → code → rebuild changed containers → `composer install --no-dev` → migrations → `ducktail:graph-migrate` → caches (`config`, `route`, `lighthouse:cache`) → restart php-fpm and scheduler (opcache does not revalidate in production) → online → tag `deploy-YYYYMMDD-HHMMSS` pushed to the remote.

**Rollback.** `make deploy REF=<previous deploy tag>` (code only; migrations and graph constraints are additive). With data: `make restore file=…` first (it dumps the current state before restoring).

**Migration policy (expand/contract).** Postgres migrations and `GraphSchema` only add. Removals happen in a later release, after no deployed code depends on them.

**Operations.** `/up` checks Postgres and Neo4j (point an external uptime monitor at it). Logs carry `request_id` and `user_id`; graph user-sync failures are `warning` `graph.user_sync_failed`. Scheduled: `auth:prune-tokens` (03:30), `ducktail:sync-users` (04:00). Versions are pinned (images by tag, Actions by SHA, `composer.lock`); Dependabot updates them through CI.

## Try it

With the containers up and the seed loaded (`make install`, `SEED_DEMO_USERS=true`, `SEED_DEMO_PASSWORD=ducktail123`; needs `curl` and `jq`):

```bash
API=http://localhost:8080
gql() { curl -s $API/graphql -H 'Content-Type: application/json' -H "Authorization: Bearer ${TOKEN:-}" -d "$1" | jq -c; }
# Sample ids are opaque UUIDs (`seed_id('seed-…')`); look them up by name:
id() { docker exec duck-tail-api-php php artisan tinker --execute "echo seed_id('$1');"; }

# Related places, with the reason (Aleijadinho!)
gql '{"query":"{ place(id: \"'"$(id seed-igreja-sao-francisco-ouro-preto)"'\") { name related(limit: 3) { reason place { name location { name } } } } }"}'

# How do these two places connect?
gql '{"query":"{ toursThrough(placeIds: [\"'"$(id seed-estacao-maria-fumaca)"'\", \"'"$(id seed-matriz-santo-antonio-tiradentes)"'\"]) { coversAll tour { name company { name } } } }"}'

# Signed in: friends who went, recommendations, and a visit
TOKEN=$(curl -s $API/api/auth/login -H 'Content-Type: application/json' \
  -d '{"email":"ana@ducktail.local","password":"ducktail123"}' | jq -r .data.access_token)
gql '{"query":"{ place(id: \"'"$(id seed-matriz-santo-antonio-tiradentes)"'\") { friendsWhoVisited { user { name } rating } } recommendations { name } }"}'
gql '{"query":"mutation { markVisited(placeId: \"'"$(id seed-matriz-santo-antonio-tiradentes)"'\", rating: 5) { name } }"}'
```

## Known limitations

- **The graph is not in the automated backup.** `make backup` dumps Postgres only. Places, locations and their edges are rebuilt from Wikidata and the seeder; what exists only in Neo4j (visits, wish lists, follows, itineraries, curated relations) is not backed up yet. Neo4j Community dumps only with the database stopped (`neo4j-admin database dump`). Next step: a `scripts/graph-backup.sh` with the same discipline as the Postgres one (maintenance, `.partial`, retention, offsite, verify by loading into a throwaway container), run by the deploy.
- **No distributed transaction between Postgres and Neo4j.** The user mirror is eventually consistent: synced after commit, healed by the mutations (`MERGE`) and by the daily reconciliation. A deleted user's graph node can survive until that run if Neo4j was down at deletion time.
- **No cache for recommendations or paths.** Every query hits Neo4j; Redis caching is planned but not needed at the current size.
- **Recommendations have no cold start.** A user without visits gets `[]`; the web shows search and popular themes instead.
- **Dense `NEAR` in big cities.** At 3 km, downtown Belo Horizonte produces hundreds of edges per place (~390k for Minas Gerais). It only feeds "nearby" lists today; a per-place cap (nearest N) is the next tweak.
- **Sample data mixes with imported data.** Seeded places have opaque ids (`seed_id('seed-…')`, a deterministic UUID) and no `wikidataId`, so an import does not merge them with their Wikidata twins. Run the seeder or the importer, not both, when demoing. Exception: `RioSaoPauloGraphSeeder` keys every node by `wikidataId` (the importer's key), so it merges with `ducktail:import --area=BR-RJ|BR-SP` in either order.
- **Places and companies are read-only through the API.** Curation (companies, curated relations, themes) is done by seeder/commands; an admin GraphQL surface is future work.
- **Itineraries cannot be edited or deleted yet**, only created.
- **No transactional e-mail** (hence registration approval by an admin and the 409 on a taken e-mail).
- **Personal data:** the graph stores only user id and name; deleting the user deletes the node, visits and itineraries. There is no export flow (LGPD) yet.

## Contributing

`main` is protected: changes arrive through a branch and a pull request that passes CI. One concern per PR, small commits whose message says **why**, and the PR template asks whether the REST JSON or `graphql/schema.graphql` changed (both are consumed by the web app as is).

## License

Released under the [MIT License](LICENSE.md). Place data imported from Wikidata is CC0.
