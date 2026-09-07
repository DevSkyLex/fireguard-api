---
name: fg-api-endpoint
description: "Add or change an API Platform endpoint — Resource, Operation constant, DTOs, Processor or Provider, validators, security, error mapping, and the functional test."
---

# fg-api-endpoint

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/presentation.md`,
`ARCHITECTURE.md` and the owning `MODULE.md`. Build the complete API Platform contract:
Resource/security, operation constant, DTOs, Processor or Provider, validation/error mapping,
and functional success plus denial tests.

Move business decisions into an Application handler. Verify organization ownership beyond
route roles, use explicit status codes, keep Domain types out of Output DTOs and preserve exact
enum literals. Contextual catalogs check permission in their Provider. Anything touching
Doctrine receives its explicit auth/main entity manager.

Update `MODULE.md` and OpenAPI. Run focused tests, PHPStan, Deptrac, container/YAML lint and
the OpenAPI check. Preserve unrelated edits and report each contract item completed.
