---
description: Run the backend quality gate — cs-fix, phpstan, deptrac, container/yaml lint, and tests — stopping at the first failure.
argument-hint: '[optional PHPUnit --filter or a test path, e.g. Facility or tests/Functional/Api]'
allowed-tools: Bash(make *), Bash(php vendor/bin/phpunit *)
---

Run the gate, **narrowest first**, and report each step.

1. `make cs-fix` — PHP-CS-Fixer (two-space indent; it rewrites, it does not merely check).
2. `make phpstan` — static analysis.
3. `make deptrac` — **the hexagonal layer-direction proof**. Presentation → Application → Domain, Infrastructure → Application, Domain → nothing but SharedDomain.
4. `make lint` — `lint:container` **and** `lint:yaml config`. `lint:container` is what catches a port aliased to nothing, which both phpstan and deptrac miss.
5. Tests — if `$ARGUMENTS` is a path, run `php vendor/bin/phpunit $ARGUMENTS`; if it looks like a class or a filter, `php vendor/bin/phpunit --filter $ARGUMENTS`; otherwise `make phpunit-fast`.

`make test` runs `cs-lint phpstan deptrac lint phpunit-parallel` in one shot when you want the whole gate.

If a step fails, **stop**, show the real output, and propose the fix. Do not continue to the next step. End with a one-line PASS/FAIL per step.

> Tests need the PostgreSQL test databases: `make test-db` once, then `make seed-fixtures`. The suite runs on PostgreSQL because production does — never substitute SQLite to make it pass.
