---
name: fg-api-api-platform-contract
description: "The API Platform surface in fireguard-sso-api — the six-item endpoint checklist, Resource/Operation/DTO/Processor/Provider layout, the reference-catalog decision, security placement, error mapping, and which status codes carry meaning. Use when adding or changing an endpoint."
---

# fg-api-api-platform-contract

Work from the API root. Read `AGENTS.md`, `.codex/workflow.md`,
`.codex/rules/presentation.md`, `ARCHITECTURE.md` and the owning `MODULE.md`.

Every endpoint ships as one coherent contract:

1. Resource with route and coarse security.
2. Typed operation constant and API Platform metadata.
3. Input and Output DTOs with serialization groups.
4. Processor for writes or Provider for reads.
5. Validation and centralized exception mapping.
6. Functional tests for success and denial.

Processors and providers translate; handlers decide. Check organization/tenant ownership
beyond role expressions. Output DTOs never expose Domain types, and enum literals match
consumers byte for byte. Use 201 with Location for creation, 204 for deletion, explicit 200
for actions on existing resources, 403 for authenticated-but-unentitled callers, and 404
when another tenant's resource must remain undisclosed.

Choose deliberately between a static contract, a module-local reference catalog and a
business resource. Contextual catalogs enforce access inside their provider. Map domain
exceptions centrally to RFC 7807. Update `MODULE.md`, export OpenAPI, inspect `debug:router`,
and run the targeted functional tests plus PHPStan, Deptrac and container/YAML lint.
