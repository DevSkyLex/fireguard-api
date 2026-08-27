---
description: Generate, apply, or review a Doctrine migration on the correct database — this app has two entity managers with separate migration histories.
argument-hint: '<auth|main|all> [diff|migrate|status] — e.g. "main diff" or "all migrate"'
---

Delegate to the **fg-migration-builder** subagent: $ARGUMENTS

The non-negotiable: **every Doctrine command names its database.** A bare `doctrine:migrations:diff` targets the default entity manager, writes into the wrong folder, and registers in the wrong version table.

| Database | Configuration | Folder | Version table |
| --- | --- | --- | --- |
| `auth` | `config/migrations/auth.yaml` | `migrations/auth/` | `doctrine_migration_versions_auth` |
| `main` | `config/migrations/main.yaml` | `migrations/main/` | `doctrine_migration_versions_main` |

Require it to:

1. **Confirm ownership in `config/packages/doctrine.yaml`** — find the `dir:`/`prefix:` pair mapping the module's `Record` namespace — before generating anything.
2. Generate with `php -d memory_limit=1G bin/console doctrine:migrations:diff --configuration=config/migrations/<db>.yaml`. **The memory flag is required**: a bare console call dies with `Allowed memory size of 134217728 bytes exhausted` building the container.
3. **Open and read the generated file.** Verify it landed in the right folder with the matching namespace, that `up()` and `down()` are symmetric, that it contains only the intended change, and that no foreign key crosses the auth/main boundary — the two are separate servers.
4. Flag every destructive statement (`DROP COLUMN`, `DROP TABLE`, a narrowing type change) with its data consequence. That needs a human decision, not just a schema decision.
5. Apply with `make migrate-<db>` (or `migrate-all`), and show `doctrine:migrations:status` **before and after** — that is what proves it registered in the right version table.
6. **Never edit an existing migration.** Its checksum and its position in the history are fixed. A PreToolUse hook blocks the write; the fix for a wrong migration is always a new one.

A migration touching auth, sessions, tokens, permissions, or the audit ledger goes to **fg-security-auditor** in the same change.
