# Module development

Build a capability at the module owning its strongest business invariant. Keep public HTTP shape and private implementation ownership explicit.

**Authoritative references:** [Architecture](../../ARCHITECTURE.md) · [Security](../../SECURITY.md).

## Construction order

1. Define the module's ownership, allowed dependencies and database in its MODULE.md.
2. Define domain invariants and typed commands/queries/results.
3. Publish the needed application ports and implement their adapters.
4. Wire adapters and explicit entity managers in `config/modules/`.
5. Add resources, operation metadata, DTOs and processors/providers for the HTTP surface.
6. Add validation, central error mapping and success/denial tests.
7. Regenerate the OpenAPI contract and add a new migration when persisted data changes.

## Documentation contract

Keep Overview, API Endpoints, Flows, Architecture, Configuration, Testing and Error
Codes in every module. Tables state method/path, contextual security and meaningful
behavior. Record unusual decisions and invariants; move inventories and chronological
test narratives into explanations or link to the actual source/tests.

## Verification

Use scoped formatting, PHPStan, both Deptrac configurations and container lint.
Run `make openapi-check` after HTTP changes and `make schema-check` after mapping
changes. PostgreSQL tests establish real repository SQL and concurrency behavior;
mocked builders cannot establish those contracts. See [testing](testing.md).
