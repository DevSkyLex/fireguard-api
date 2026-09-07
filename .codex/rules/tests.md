# PHPUnit tests

Use PHPUnit 12 attributes. Unit tests mirror `src/` under `tests/Unit/<Module>/`;
integration tests cover PostgreSQL repositories; functional API tests cover HTTP
contracts and denial; E2E tests cover multi-endpoint flows.

For a new endpoint, add handler and processor/provider unit coverage plus functional
success and denial paths. Assert 403 for an entitled-context failure and 404 for a record
outside the caller's organization. Exact statuses, enum values and Result fields are
contract assertions; do not weaken them to non-null checks.

Mock every handler port and assert calls, failures, events and the no-event idempotent
path. Reuse `tests/Support` and `tests/Helper` before creating fixtures.

Prepare both PostgreSQL test databases with `make test-db`. Run tests on the host, never
inside the development app container. `phpunit.dist.xml` supplies test memory settings;
the `-d memory_limit=1G` requirement applies to `bin/console`, not ad-hoc PHPUnit calls.
Do not leave skipped tests or change production code merely to satisfy a test.
