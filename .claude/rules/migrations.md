---
paths:
  - 'migrations/**/*.php'
---

# Doctrine migrations

**There are two databases, and every command must name which one.** A bare `doctrine:migrations:diff` targets the default entity manager, writes into the wrong folder, and registers in the wrong version table.

| Database | Configuration                 | Folder             | Version table                      |
| -------- | ----------------------------- | ------------------ | ---------------------------------- |
| `auth`   | `config/migrations/auth.yaml` | `migrations/auth/` | `doctrine_migration_versions_auth` |
| `main`   | `config/migrations/main.yaml` | `migrations/main/` | `doctrine_migration_versions_main` |

```bash
php -d memory_limit=1G bin/console doctrine:migrations:diff --configuration=config/migrations/main.yaml
php -d memory_limit=1G bin/console doctrine:migrations:status --configuration=config/migrations/main.yaml
make migrate-main        # or migrate-auth, or migrate-all
```

**`-d memory_limit=1G` is not optional.** A bare `php bin/console` dies with `Allowed memory size of 134217728 bytes exhausted` building the container. Every Makefile target sets it.

## Never edit an existing migration

Its checksum and its position in the ordered history are fixed the moment it runs anywhere; editing it desynchronises every environment that already applied it. **A PreToolUse hook blocks the write.** The fix for a wrong migration is always a _new_ migration.

## Read what you generated

A generated migration is a draft:

- right folder and matching `DoctrineMigrations\Auth|Main` namespace,
- `up()` and `down()` **symmetric** — an unusable `down()` makes it one-way in practice,
- **only the change you intended.** A diff against a drifted local database happily includes someone else's pending work; delete what is not yours,
- **no foreign key or join across the auth/main boundary** — they are separate servers. If a diff produces one, the mapping is wrong, not the migration,
- every destructive statement (`DROP COLUMN`, `DROP TABLE`, a narrowing type) called out explicitly with its data consequence. That is a human decision, not a schema one.

Never point a migration at a production DSN.
