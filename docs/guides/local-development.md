# Local API development

Use the repository Compose stack and configure private local overrides. Development resets and test-template preparation have different targets.

**Authoritative references:** [Makefile](../../Makefile) · [Compose](../../compose.yaml) · [Environment example](../../.env.example) · [Environment defaults](../../.env.dist).

## Prepare and start

```sh
composer install
make docker-up
```

Use PHP 8.4 or newer. Start from the checked-in environment example/defaults and
keep personal overrides outside version control. Set both database connections and
the required application/encryption configuration before real API use. Example
URLs can use `https://api.example.com` and `https://app.example.com`; the configured
issuer, cookies, CORS and Mercure origins must describe the same environment.

The local stack publishes the application on 8000, auth PostgreSQL on 5433, main
PostgreSQL on 5434, Redis on 6379 and Mailpit UI on 8025. Compose definitions remain
the source of truth for overrides. Use `make docker-logs` and `make docker-shell`
for local diagnosis; application containers are not the test runner.

## Compose project and data identity

The default local project is `fireguard-api`, independently of the checkout
directory. Use `docker compose -p <local-project>` or `COMPOSE_PROJECT_NAME` when
running another checkout alongside it. Persistent volumes use
`<local-project>_local_<volume>`; for example,
`fireguard-api_local_auth_database_data`. The `local` namespace keeps development
data separate from volumes belonging to older installations of the same project.
Database commands in the Makefile address Compose services, so they follow the
selected project name.

Changing a project or volume name does not move existing data. Before starting
the renamed stack, stop the existing containers and copy each named volume to
its new name while the original is mounted read-only. Preserve ownership and
permissions, compare the copies, then verify both databases and application
health after startup. Retain the original volumes for rollback; never use
`docker compose down -v` for a rename. See Docker's
[project-name rules](https://docs.docker.com/compose/how-tos/project-name/) and
[volume migration procedure](https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes).

VPS deployments use `compose.prod.yaml`, their explicit `COMPOSE_PROJECT_NAME`
and `VOLUME_PREFIX`. Repository directory names do not change the installation
paths or deployed data identities recorded in the
[installation appendix](../operations/current-installation.md).

## Apply both migration histories

```sh
make migrate-all
```

The named `migrate-auth` and `migrate-main` targets apply one history each. Console
commands use `php -d memory_limit=1G bin/console`. New persisted changes receive new
migrations; existing migration files are immutable.

## Explicit development reset

`make seed-fixtures-docker` purges and rebuilds both local development databases.
Use it deliberately after starting the stack. Supply five distinct recoverable
passwords using the fixture loader's `FIXTURE_USER_PASSWORDS_JSON` contract: keys
`admin`, `test`, `demo`, `staff`, `dev_client`, each 16–72 bytes without NUL.
Keep the JSON in an ignored file with restrictive permissions and retain the
credentials privately before resetting.

On PowerShell, for an illustrative ignored `var/fixture-user-passwords.local.json`:

```powershell
$env:FIXTURE_USER_PASSWORDS_JSON = [System.IO.File]::ReadAllText((Resolve-Path -LiteralPath 'var/fixture-user-passwords.local.json').Path)
try { make seed-fixtures-docker } finally { Remove-Item Env:\FIXTURE_USER_PASSWORDS_JSON -ErrorAction SilentlyContinue }
```

Do not put password JSON into Compose or shell history. Direct Doctrine fixture
loading cannot coordinate the two owners; use the repository loader. Test fixtures
are deterministic and prepared with `make test-db`, not a development reset.
