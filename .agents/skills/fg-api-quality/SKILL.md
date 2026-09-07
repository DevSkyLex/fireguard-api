---
name: fg-api-quality
description: "Run the backend quality gate — cs-fix, phpstan, deptrac, container/yaml lint, and tests — stopping at the first failure."
---

# fg-api-quality

Read `AGENTS.md`, `.codex/workflow.md` and the relevant local rules. Run the narrowest
useful check first, then widen according to the change:

1. `make cs-fix` for touched PHP style;
2. focused PHPUnit on the host;
3. `make phpstan`;
4. `make deptrac`;
5. `make lint` for container and YAML;
6. OpenAPI and both schema checks when endpoints or Doctrine changed;
7. `make test` only when the blast radius justifies the complete gate.

Prepare test PostgreSQL databases with `make test-db`. Do not run tests in the development app
container and do not seed development fixtures. Stop to diagnose a failing dependent gate; report
actual PASS/FAIL results and anything intentionally skipped.
