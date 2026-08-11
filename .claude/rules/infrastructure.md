---
paths:
  - 'src/*/Infrastructure/**/*.php'
---

# Infrastructure layer

> The entity-manager half abridges the `dual-database` skill — **change one, change both.**

Infrastructure **implements the ports** the Application layer defines. It may depend on `Application`, `Domain`, and the `Shared*` layers — never on `Presentation`.

- Adapters are `<Capability>Adapter`; persistence classes are `<Entity>Repository`.
- **Hide third-party types behind the adapter.** A vendor's classes must never appear in a signature the Application layer sees.
- **Keep persistence logic in repositories, with no business rules.** A rule that lives in a query is a rule no handler test can reach.
- A dedicated integration that outgrows one adapter gets its own folder under `Infrastructure/` with an `Adapter/` subfolder (`Infrastructure/OAuth2/League/`) — **never a global `External/`**.
- The Doctrine `Record` is a **separate shape** from the Domain model: it carries the ORM attributes, the model carries the invariants, and a mapper (or the repository) translates.

## The dual-database trap — the one that bites

Every repository must be wired with an **explicit** entity manager in `config/modules/<module>.yaml`:

```yaml
Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository:
  arguments:
    $entityManager: '@doctrine.orm.main_entity_manager'
```

Autowiring resolves `EntityManagerInterface` to the **default** manager. Without that argument the code compiles, phpstan passes, deptrac passes, `lint:container` passes — and it queries the wrong database. No exception, no failing test, just missing data.

`auth` owns OAuth · User · Otp · Authorization · Session · Tenant · TrustedDevice · Audit. `main` owns the business modules. **Confirm in `config/packages/doctrine.yaml`** by finding the `dir:`/`prefix:` pair for your module's `Record` namespace — the summary drifts, the config does not.

The two databases are **separate servers**: no foreign key between them, no join across them.

A Doctrine repository is best covered by an **integration** test (`tests/Integration/`) against the real schema — that is what `make test-db` exists for.
