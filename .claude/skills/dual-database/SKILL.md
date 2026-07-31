---
name: dual-database
description: The auth/main two-database split in fireguard-sso-api — which modules live where, the explicit $entityManager wiring every repository needs, the migration commands per database, and the test-database setup. Use before wiring a repository, generating a migration, or debugging data that went to the wrong place.
---

# Two databases, two entity managers, two migration histories

This is the single most expensive thing to get wrong in this codebase, because **it fails silently**: no exception, no failing test, just data written to the wrong database and discovered much later.

## The split

| | `auth` | `main` |
| --- | --- | --- |
| Entity manager | `doctrine.orm.auth_entity_manager` | `doctrine.orm.main_entity_manager` |
| Migration config | `config/migrations/auth.yaml` | `config/migrations/main.yaml` |
| Migrations folder | `migrations/auth/` | `migrations/main/` |
| Version table | `doctrine_migration_versions_auth` | `doctrine_migration_versions_main` |
| Apply target | `make migrate-auth` | `make migrate-main` |
| Docker container | `fireguard-sso-api-auth_database-1` | `fireguard-sso-api-main_database-1` |
| Owns | OAuth · User · Otp · Authorization · Session · Tenant · TrustedDevice · Audit | Organization · Facility · Equipment · Inspection · Intervention · Notification and the other business modules |

**`config/packages/doctrine.yaml` is the authority.** Find the `dir:`/`prefix:` pair that maps your module's `…\Infrastructure\Persistence\Doctrine\Record` namespace, and read which `entity_managers:` block it sits under. The table above is a summary that will drift; the config will not.

## The wiring that everyone forgets

Two entries in `config/modules/<module>.yaml`, both mandatory:

```yaml
Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository:
  arguments:
    $entityManager: '@doctrine.orm.main_entity_manager'   # <- without this, the default wins

Facility\Application\Port\Outbound\FacilityRepositoryPort:
  alias: Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository
```

Autowiring resolves `EntityManagerInterface` to the **default** manager. A repository, processor, or provider without an explicit `$entityManager` argument compiles, passes phpstan, passes deptrac, passes `lint:container` — and queries the wrong database.

The same applies to **processors and providers** that touch Doctrine directly:

```yaml
Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider:
  arguments:
    $entityManager: '@doctrine.orm.main_entity_manager'
```

Grep an existing module's YAML before writing a new one — `config/modules/facility.yaml` shows the full pattern.

## Migrations

Generate — **never without `--configuration`**:

```bash
php -d memory_limit=1G bin/console doctrine:migrations:diff --configuration=config/migrations/main.yaml
```

Status and apply:

```bash
php -d memory_limit=1G bin/console doctrine:migrations:status --configuration=config/migrations/main.yaml
make migrate-main        # or migrate-auth, or migrate-all
```

Each YAML carries `em:`, so `--configuration` alone selects the entity manager.

> **`-d memory_limit=1G` is not optional.** A bare `php bin/console` dies with
> `Allowed memory size of 134217728 bytes exhausted` while building the container.
> Every Makefile target sets it (`PHP_MEMORY_LIMIT ?= 1G`); match them.

**Never edit a migration that already exists** — its checksum and its position in the history are fixed once it has run anywhere. A PreToolUse hook blocks the write. The fix for a wrong migration is a new migration.

## What cannot cross the line

The two databases are **separate servers**. No foreign key between them. No SQL join across them. If a `diff` ever produces one, the Doctrine mapping is wrong, not the migration.

A module needing data from the other side goes through the owning module's **port and contract types** — the same rule as any cross-module dependency.

## Test databases

```bash
make test-db          # create + migrate fireguard_auth_test and fireguard_main_test
make test-db-clean
make seed-fixtures    # knows about both databases — use this, not doctrine:fixtures:load
```

The suite runs on **PostgreSQL because production does**. Substituting SQLite to make a test pass discards the schema, the constraints, and the dialect — which is most of what an integration test exists to check.

## Symptoms of getting it wrong

- A repository returns nothing though the row visibly exists → wrong entity manager.
- `doctrine:migrations:status` shows an unexpected count → the migration registered in the other version table.
- A migration lands in `migrations/auth/` with a `DoctrineMigrations\Main` namespace → `--configuration` was omitted or wrong.
- A foreign key in a generated migration points at a table on the other database → the mapping is wrong.
