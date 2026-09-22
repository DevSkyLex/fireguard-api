# PHPUnit tests

Use PHPUnit 12 attributes. Unit tests mirror `src/` under `tests/Unit/<Module>/`;
integration tests cover PostgreSQL repositories; functional API tests cover HTTP
contracts and denial; E2E tests cover multi-endpoint flows.

For a new endpoint, add handler and processor/provider unit coverage plus functional
success and denial paths. Assert 403 for missing entitlement and 404 for a record
outside the caller's organization. Exact statuses, enum values and Result fields are
contract assertions; do not weaken them to non-null checks.

Mock every handler port and assert calls, failures, events and the no-event idempotent
path. Reuse `tests/Support` and `tests/Helper` before creating fixtures.

Prepare both PostgreSQL test databases with `make test-db`. Run tests on the host, never
inside the development app container. Match the Makefile's `php -d memory_limit=1G`
runner when using an explicit PHPUnit path/filter; `phpunit.dist.xml` remains the test config.
`make test-db` replaces test templates; do not repeat it per test or add `make seed-fixtures`
(development) to that procedure. See the [targeted commands](../../.agents/skills/fg-api-tests/references/testing.md).
Do not leave skipped tests or change production code merely to satisfy a test.
