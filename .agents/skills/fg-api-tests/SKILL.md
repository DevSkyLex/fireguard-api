---
name: fg-api-tests
description: "Write or repair PHPUnit tests — unit for handlers and domain, integration for repositories, functional for endpoint contracts including denial paths, E2E for full flows."
---

# fg-api-tests

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/tests.md`, the module-testing skill
and owning `MODULE.md`. Choose and state the correct unit, integration, functional or E2E level.

Mirror source paths, reuse test helpers and use PHPUnit 12 attributes. Handler tests mock every
port and cover exact Results, failures, events and idempotence. Functional endpoint tests cover
success, 403 entitlement denial and 404 cross-organization isolation. Keep assertions exact and
never weaken production behavior to satisfy a test.

Prepare PostgreSQL with `make test-db`, run tests on the host, then run the narrowest relevant
static checks. Preserve other edits and report commands/results honestly.
