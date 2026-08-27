---
description: Review the API Platform contract — resource metadata, DTOs, serialization groups, status codes, filters, pagination, OpenAPI output, and exception mapping — for regressions and drift. Read-only.
argument-hint: '[endpoint, resource, or diff scope]'
---

Delegate to the **fg-contract-reviewer** subagent: $ARGUMENTS

The frontend consumes this API. A renamed field or a changed enum literal breaks it **at runtime**, not at build time, and TypeScript will not catch it.

Require it to check:

1. **Breaking changes, named as such** — a removed or renamed field, a narrowed type, a changed enum literal, a moved status code, a field that became required. Each is breaking for a consumer already in production.
2. **Enum literals byte for byte** — `'in_progress'` versus `'inProgress'` is the highest-signal defect here; one of them silently fails a frontend `switch`.
3. **Output DTOs never expose a Domain type.**
4. **Status codes** — 201 + `Location`, 204 on delete, 400/401/**403**/**404**/409/429. The 403-versus-404 choice carries security meaning: 404 for another tenant's record is deliberate, since 403 would confirm it exists.
5. **Serialization groups** — every exposed field in a group the operation requests, and no group leaking a field it should not return.
6. **Pagination and filters** — consistent names, a bounded page size, a documented default. An unbounded collection is a denial-of-service primitive.
7. **Reference catalogs** — static contract versus reference catalog versus business resource; module-local routes, never a generic `/options` or `/lookups`; contextual lists gated **in the provider**.
8. **Error mapping** — centralized in an `EventSubscriber`, RFC 7807 shape (`status`, `type`, `title`, `detail`).

Substantiate with `php -d memory_limit=1G bin/console api:openapi:export` — the generated source of truth for what the API actually publishes — and `debug:router`.

Ask for findings ranked **breaking → should-fix → nit**, with breaking changes in their own section at the top and the consumer impact spelled out. End with a verdict: contract stable, additive only, or breaking. Frontend-side drift belongs to the monorepo-root `fg-contract-sync`.
