# Dual-database ownership and operations

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/module-config.md` and
`.codex/rules/migrations.md` from the API root.

`auth` owns identity, OAuth, users, OTP, sessions, trusted devices, tenants and audit.
`main` owns business modules. `config/packages/doctrine.yaml` is authoritative: locate the
Record namespace under the matching entity manager before wiring or migrating anything.

Every Doctrine repository, processor and provider receives an explicit entity manager in
`config/modules/<module>.yaml`. The default manager is auth, so omitted wiring can compile
while querying the wrong database. Cross-database joins and foreign keys are forbidden;
exchange identifiers through Application ports/contracts instead.

Use the named migration configurations and histories:

- auth: `config/migrations/auth.yaml`, `migrations/auth/`
- main: `config/migrations/main.yaml`, `migrations/main/`

Every `bin/console` call uses `php -d memory_limit=1G`. Prepare both PostgreSQL test
databases with `make test-db`; do not seed development databases for tests. Validate service
wiring, migration status and the relevant schema separately for each manager.
