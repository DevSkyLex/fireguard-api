# Persistence implementation

Read the owning `MODULE.md`, `.codex/rules/infrastructure.md`, and
[dual-database ownership](../../fg-api-migrate/references/dual-database.md).
Identify the existing Application port, Record mapping and service alias before editing.

Keep Doctrine Records and query builders in Infrastructure. Map explicitly to Domain or
Application contracts, preserving nullability, identifiers, dates and enum semantics.
Avoid lazy-loaded Doctrine objects escaping the adapter. Tenant/organization predicates
must be applied before pagination and mutation, including bulk and direct-identifier paths.

Use the explicit owning entity manager and transaction connection. Acquire row/advisory
locks in the documented order, then reread mutable state under the lock; account for
already-managed stale Records. Preserve atomic invariant checks and unique constraints.
Never hold locks across network calls or claim auth/main atomicity.

The persistence builder owns assigned Records, repositories, mappers and their tests.
Hand schema/index changes and persisted-data transitions to the migration specialist with
the target database and required constraint; do not edit migration history yourself.
Repository-only changes do not automatically require a migration.

Prove mapping and query behavior with PostgreSQL integration tests, including missing and
foreign-organization records, pagination and concurrent updates where relevant. Run both
schema checks after mapping changes and container lint after wiring changes. Report the
actual transaction/lock guarantees and tests, not only successful CRUD.
