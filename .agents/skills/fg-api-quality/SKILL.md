---
name: fg-api-quality
description: "Run scoped backend quality checks and report actual results, including formatting scope, both Deptrac gates, PostgreSQL tests and contract checks."
---

# fg-api-quality

Read `AGENTS.md`, `.codex/workflow.md` and the relevant local rules. Run the narrowest
useful check first, then widen according to the change:

1. Inspect the worktree; `make cs-lint` checks the configured PHP finder without rewriting files.
   `make cs-fix` rewrites that entire finder, not only touched PHP. For a scoped change use the
   project fixer with explicit assigned files and `--path-mode=intersection --using-cache=no`;
   reserve the global target for a scope that permits all resulting edits;
2. focused PHPUnit on the host;
3. `make phpstan`;
4. `make deptrac` (both layer and module-boundary configurations);
5. `make lint` for container and YAML;
6. `make openapi-check` for HTTP contract changes and `make schema-check` for Doctrine mapping
   changes (it checks auth and main against test databases);
7. `make test` only when the blast radius justifies the complete gate.

Prepare test PostgreSQL databases with `make test-db`. Do not run tests in the development app
container and do not seed development fixtures. Read the
[testing procedures](../fg-api-tests/references/testing.md) for targeted commands.
Stop to diagnose a failing dependent gate; report
actual PASS/FAIL results and anything intentionally skipped.
