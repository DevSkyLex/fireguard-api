# Test levels and PostgreSQL execution

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

## Commands and database baseline

Run from the API root after installing the dependencies declared in `composer.json` and
starting the local PostgreSQL infrastructure described in `OPERATIONS.md`. `make test-db`
creates both test databases, migrates auth/main explicitly and loads fixtures with `--env=test`.
Run it once, then after a migration or fixture change. It replaces the test baseline and is
not a harmless per-test setup command. `make seed-fixtures` targets development and must not
be added to test setup. Each suite run clones the two templates through `tests/bootstrap.php`.

The following commands match the project runner and memory setting; replace placeholders
with an existing assigned test path or class. Do not paste angle-bracket placeholders literally.

```text
php -d memory_limit=1G vendor/bin/phpunit -c phpunit.dist.xml <assigned-test-file>
php -d memory_limit=1G vendor/bin/phpunit -c phpunit.dist.xml --filter <test-class-or-method>
php -d memory_limit=1G vendor/bin/phpunit -c phpunit.dist.xml --testsuite "General Unit Tests"
make phpunit-fast
make phpunit-parallel
```

Use the narrowest file/filter first. The named unit suite still loads the repository bootstrap;
do not promise database-free execution solely because a test is called unit. Test template
creation/seeding is a writer task, never part of a read-only reviewer invocation.
