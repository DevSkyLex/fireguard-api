# Module development

Build a capability at the module owning its strongest business invariant. Keep public HTTP shape and private implementation ownership explicit.

**Authoritative references:** [Architecture](../../ARCHITECTURE.md) · [Security](../../SECURITY.md).

## Construction order

1. Define the module's ownership, allowed dependencies and database in its MODULE.md.
2. Define domain invariants and typed commands/queries/results.
3. Publish the needed application ports and implement their adapters.
4. Wire adapters and explicit entity managers in the owner's module entry point and configuration fragments.
5. Add resources, operation metadata, DTOs and processors/providers for the HTTP surface.
6. Add validation, central error mapping and success/denial tests.
7. Regenerate the OpenAPI contract and add a new migration when persisted data changes.

## Composition and public extensions

Keep services in `config/modules/<owner>.yaml` and owned mappings, API discovery,
exception mappings, transports/routing and fixtures in its imported `doctrine.yaml`,
`api_platform.yaml`, `framework*.yaml` and `seeds.yaml` fragments. Shared package
configuration defines connections, managers and framework defaults. Select only
the owner's fragments when composing a smaller kernel; production still selects
the complete FireGuard application.

Publish immutable contribution definitions and typed capability ports under
Application. The owner implements and registers the contribution; the receiving
engine composes it without knowing the owner's private models. Registries reject
duplicate keys at boot and deny new writes or execution for unregistered keys.
Persisted historical keys remain readable independently of installed contributors.
Keep technical enums closed and preserve existing business keys, labels, order,
permission grants and policy defaults when extracting a catalog.

Declare client mutations with the published HTTP operation contract, including
database, final transaction owner and primary result schema. Test current-access
denial, changed input, duplicate execution, rollback and unknown commit outcomes.
Provider callbacks derive replay identity only after native signature/identity
verification. See the [mutation and replay standard](../../ARCHITECTURE.md#mutation-and-replay-contracts).

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

After composition changes, also run the [exact core kernel suite](../../tests/Composition/phpunit.xml).
It uses the existing full PostgreSQL templates and verifies selected metadata,
routes and services; it does not authorize new migrations or a separate database baseline.
