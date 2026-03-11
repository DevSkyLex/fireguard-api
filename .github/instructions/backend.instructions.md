---
applyTo: "src/**/*.php"
---

This repository uses a hexagonal module structure. Keep layer boundaries explicit:

- Presentation calls application use cases
- Application depends on ports and domain
- Infrastructure implements ports
- Domain must not depend on Symfony, Doctrine records, HTTP classes, or framework services

Keep business logic in `Application/UseCase/*/*Handler.php`.

Do not move validation, authorization, lifecycle transitions, or orchestration logic into processors, providers, mappers, or subscribers unless the code is truly presentation-only.

When changing list or get flows, verify scoping by tenant and organization before mapping results.

When changing commands, preserve invariants, lifecycle rules, idempotency expectations, and transactional safety.

Return explicit result objects from handlers instead of raw arrays when the use case contract is being changed or introduced.

If an API-facing DTO, resource, filter, or processor changes, check whether tests and module documentation should change too.
