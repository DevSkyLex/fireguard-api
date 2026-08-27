---
description: Add or change an API Platform endpoint — Resource, Operation constant, DTOs, Processor or Provider, validators, security, error mapping, and the functional test.
argument-hint: '<Module> <endpoint> — e.g. "Equipment list endpoint with filters and pagination"'
---

Delegate to the **fg-endpoint-builder** subagent: $ARGUMENTS

Require all six checklist items from `ARCHITECTURE.md`, reported item by item:

- [ ] Resource with proper route **and security**
- [ ] Operation constant and metadata
- [ ] Input/Output DTOs
- [ ] Processor (write) or Provider (read)
- [ ] Validation rules and error mapping
- [ ] Functional tests for success **and error** cases

Plus:

1. The processor or provider **translates, it never decides** — no business branch, no direct repository query. Logic belongs in a handler.
2. An explicit `$entityManager` argument in `config/modules/<module>.yaml` for anything touching Doctrine.
3. For a list endpoint, apply the three-way rule: **static contract** (no endpoint needed), **reference catalog** (module-local `GetCollection`, minimal `value`/`label`), or **business resource**. Never a generic `/options` or `/lookups`. A contextual catalog puts its permission check **in the provider**.
4. The functional test asserts **403** for an authenticated-but-unentitled caller and **404** for a record in another tenant. Those two prove isolation; a 200-only test proves nothing.
5. Output DTOs never expose a Domain type, and enum literals match the frontend byte for byte (`'in_progress'`, never `'inProgress'`).
6. Update `MODULE.md` — the endpoint table is one of its seven required sections.
7. Run `make cs-fix phpstan deptrac lint` plus the functional test.
