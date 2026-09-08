# Doctrine migrations

There are two independent histories. Every command names its configuration.

| Database | Configuration | Folder | Version table |
| --- | --- | --- | --- |
| auth | `config/migrations/auth.yaml` | `migrations/auth/` | `doctrine_migration_versions_auth` |
| main | `config/migrations/main.yaml` | `migrations/main/` | `doctrine_migration_versions_main` |

Use `php -d memory_limit=1G bin/console doctrine:migrations:<command>
--configuration=config/migrations/<db>.yaml`, or the corresponding Makefile target.

Never edit an existing migration. Generate a new one, read it, and verify its namespace,
folder, scope and symmetric `up()`/`down()`. Call out destructive statements and their
data consequence. Foreign keys and joins cannot cross auth/main. Show migration status
before and after application and never point a migration command at production.
