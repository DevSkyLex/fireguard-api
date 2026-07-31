---
paths:
  - 'src/*/Presentation/**/*.php'
---

# Presentation layer (API Platform)

**Presentation translates; it never decides.** A processor unwraps an Input DTO, dispatches a command, and maps the Result to an Output DTO. The moment it branches on a business condition, that branch belongs in a handler.

## Every endpoint needs all six

- [ ] Resource with proper route **and security**
- [ ] Operation constant and metadata
- [ ] Input/Output DTOs
- [ ] Processor (write) or Provider (read)
- [ ] Validation rules and error mapping
- [ ] Functional tests for success **and error** cases

Five of six is a defect. Security and the error-case tests are the two that go missing.

## Rules

- A processor or provider may **not** hold a business rule, query a repository directly, or reach into another module.
- **A resource-level role check does not prove the caller owns _this_ record.** When the endpoint is scoped to a tenant or organization, the ownership check belongs in the handler or the provider. That gap is the IDOR.
- Status codes carry meaning: 201 + `Location` · 204 on delete · 400 malformed · 401 unauthenticated · **403 authenticated-but-not-entitled** · **404 for a record outside the caller's scope** (deliberately not 403, which would confirm it exists) · 409 conflict · 429 rate-limited.
- **An Output DTO never exposes a Domain type.** Enum literals match the frontend **byte for byte** — `'in_progress'`, never `'inProgress'`; TypeScript will not catch a mismatch.
- Domain exception → HTTP status is mapped **centrally in an `EventSubscriber`**, never try/catch at each call site. The body follows RFC 7807 (`status`, `type`, `title`, `detail`).
- A custom validator gets its own folder holding the constraint **and** its validator.
- Operation names are typed constants in `<Module>Operations`: `public const string CREATE_FACILITY = 'facility_create';`.
- A list endpoint is a **static contract** (no endpoint), a **reference catalog** (module-local `GetCollection`, minimal `value`/`label`), or a **business resource**. Never a generic `/options` or `/lookups`. A contextual catalog gates **in the provider**.
- A processor or provider touching Doctrine needs an explicit `$entityManager` in `config/modules/<module>.yaml` — see the `infrastructure` rule.

Update the `MODULE.md` endpoint table in the same change.
