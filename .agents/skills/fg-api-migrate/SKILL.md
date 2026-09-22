---
name: fg-api-migrate
description: "Generate, apply, or review a Doctrine migration on the correct database — this app has two entity managers with separate migration histories."
---

# fg-api-migrate

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/migrations.md`,
[dual-database ownership](references/dual-database.md) and the owning `MODULE.md`.
Confirm the Record namespace's auth/main mapping before running
anything. Every console command uses `php -d memory_limit=1G` and the explicit migration
configuration.

Only schema or persisted-data changes require a migration; a query or mapper-only refactor does not.
Generate a new migration; never edit an existing one. Read the generated file and verify folder,
namespace, scope, symmetric reversal and absence of cross-database constraints. Highlight any
destructive SQL before applying it. Show status before and after through the correct history.

Validate the affected schema and migration status. Security-sensitive auth, permission, session
or audit changes also require a security review and denial tests.
