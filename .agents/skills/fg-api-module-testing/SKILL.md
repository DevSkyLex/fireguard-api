---
name: fg-api-module-testing
description: "How to test fireguard-sso-api — which level covers what, the test path mirroring src/, the denial paths a functional test must assert, the PostgreSQL test databases, and the exact PHPUnit commands. Use before writing or running any test."
---

# fg-api-module-testing

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/tests.md` and the owning
`MODULE.md`. Select the smallest level that proves the behavior:

- unit for Domain and handlers with mocked ports;
- integration for Doctrine mapping, queries and constraints on PostgreSQL;
- functional for the HTTP contract and denial paths;
- E2E for flows spanning endpoints.

Test paths mirror `src/` beneath `tests/Unit/<Module>`. A new endpoint normally needs a
handler test, a processor/provider test and a functional test. Handler tests assert every
port call, Result field, failure, event and no-event idempotent replay. Functional tests
cover 403 for missing entitlement and 404 for a record outside the caller's organization.

Reuse `tests/Support` and `tests/Helper`. Use PHPUnit 12 attributes, exact contract
assertions and no skipped tests. Prepare PostgreSQL with `make test-db`, then run tests on
the host. Never use the development app container or substitute SQLite.
