## What changes

<!-- One concern per PR: a fix, a feature or an infra change. -->

## Why

<!-- The problem or the decision behind it. Link the issue, if there is one. -->

## How it was tested

- [ ] `make ci` passes (lint, Larastan, audit, Pest against Postgres and the test Neo4j)
- [ ] New rule, endpoint or GraphQL field has a test that **fails without the change**
- [ ] Session/locks under concurrency: test in `tests/Concurrency`, inside `withoutTransactionRetries()`

## Contract and operations

- [ ] REST JSON and `graphql/schema.graphql` unchanged (or aligned with `duck-tail-web` before merging); schema still free of directives
- [ ] New messages in the three `lang/*/messages.php`
- [ ] Migration only expands (new nullable column, table, index, `CHECK`); graph constraints/indexes only added to `GraphSchema`
- [ ] Cypher parameterized, in a Query (read) or Action (write); nested list fields batched (`BatchLoader`)
- [ ] README / `AGENTS.md` / `docs/graph.md` updated if behavior or a convention changed
