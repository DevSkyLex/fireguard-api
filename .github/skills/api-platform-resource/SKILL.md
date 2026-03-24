---
name: api-platform-resource
description: 'Add or update an API Platform resource in this Symfony hexagonal backend. Use for ApiResource classes, operation constants, input and output DTOs, processors, providers, serialization groups, OpenAPI metadata, filters and pagination, permission gates, HTTP exception mapping, and the required functional or unit tests.'
argument-hint: 'Module + resource change, for example: Equipment list endpoint with filters and pagination'
---

# API Platform Resource

## When to Use

- Create a new API Platform resource for an existing module
- Add or modify operations on an existing resource
- Add or update input or output DTOs
- Add or update processors, providers, OpenAPI metadata, filters, pagination, or serialization groups
- Refactor API-facing behavior while keeping business logic in handlers

## Required Inputs

Before editing code, identify:

- target module and resource name
- endpoint shape: collection, item, command action, query action, or mixed resource
- operation method and URI template
- whether the flow is write-oriented or read-oriented
- input DTO, output DTO, and expected HTTP status codes
- permission and scoping requirements: `tenantId`, `organizationId`, ownership, or role-only access
- expected failure modes and how they should map to HTTP exceptions

If any of these are unclear, inspect the closest resource, operations class, processor or provider, DTOs, and module tests before changing anything.

## Procedure

## Assets

Use these templates when you need a starting point instead of copying a random module file:

- [Resource template](./assets/resource-template.php.txt)
- [Operations template](./assets/operations-template.php.txt)
- [Processor template](./assets/processor-template.php.txt)
- [Provider template](./assets/provider-template.php.txt)
- [Paginated provider template](./assets/paginated-provider-template.php.txt)
- [Input DTO template](./assets/input-dto-template.php.txt)
- [Output DTO template](./assets/output-dto-template.php.txt)
- [Reference notes](./references/patterns.md)

### 1. Anchor on the Closest Existing Resource

- Find the module resource that most closely matches the shape you need.
- Reuse its style for:
  - `ApiResource` declaration
  - operation constants
  - DTO naming
  - security expression placement
  - OpenAPI metadata
  - exception mapping
  - pagination and filtering behavior

This repo favors explicit, verbose resource definitions over hidden conventions.

### 2. Decide the Resource Shape

Choose the right combination of pieces:

- `Presentation/Api/Resource/<Resource>Resource.php`
- `Presentation/Api/Operation/<Resource>Operations.php`
- `Presentation/Api/Dto/Input/<Area>/<Action>Input.php`
- `Presentation/Api/Dto/Output/<Area>/<Action>Output.php` or a shared output DTO
- `Presentation/Api/Processor/<Area>/<Action>Processor.php` for writes
- `Presentation/Api/Provider/<Area>/<Action>Provider.php` for reads
- `Presentation/Api/Serialization/<Module>SerializationGroup.php` when groups need module-level reuse
- `Presentation/Api/Validator/...` when input validation exceeds built-in constraints

Do not place business rules in the resource class itself.

### 3. Model the Operation Explicitly

For each operation, define:

- operation name constant in the `Operations` class
- HTTP method and URI template
- `input` and `output` classes or `false` where appropriate
- `processor` for commands or `provider` for queries
- read or pagination flags when needed
- normalization and denormalization groups
- security expression and OpenAPI metadata

Use the operation class to centralize stable operation names. This keeps route-level behavior readable and consistent.

### 4. Keep Resource Metadata Honest

In the `ApiResource` definition, ensure:

- `routePrefix` matches the module's URI shape
- descriptions describe the external behavior accurately
- OpenAPI responses cover the real success and failure statuses
- query parameters are declared when filters are publicly supported
- pagination settings match the actual provider behavior

Do not declare filter or response behavior that the provider does not implement.

### 5. Build Thin Input and Output DTOs

Input DTOs should:

- expose only writable request fields
- use Symfony Validator constraints for request validation
- use `ApiProperty` metadata where helpful
- use write serialization groups only

Output DTOs should:

- expose only externally readable fields
- avoid leaking internal-only identifiers or sensitive values
- use read serialization groups only
- represent the result contract, not the internal domain model shape

Keep DTOs simple. Mapping complexity belongs in the processor or provider.

### 6. Keep Processors Thin and Command-Oriented

For write operations, processors should:

- validate the authenticated user exists when required
- extract and validate URI variables explicitly
- enforce coarse permission checks before dispatch
- build the command DTO for the application layer
- dispatch through the command bus
- map domain or validation failures to HTTP exceptions
- map the result object to the output DTO

Processors must not own business rules, lifecycle transitions, or repository logic.

### 7. Keep Providers Thin and Query-Oriented

For read operations, providers should:

- validate the authenticated user exists when required
- extract URI variables and request query parameters explicitly
- enforce coarse permission checks before asking the query bus
- create pagination or filtering contracts when the module uses them
- map query results to output DTOs or paginator objects
- translate invalid inputs or expected domain failures into HTTP exceptions

When the repo already uses `TraversablePaginator`, `Pagination`, or search and sorting helpers, stay aligned with those patterns.

### 8. Preserve Scoping and Permission Boundaries

For every operation, verify:

- where `tenantId`, `organizationId`, or ownership enters the flow
- which permission string is required in the provider or processor
- whether the resource-level `security` expression is sufficient only as a coarse gate
- whether the underlying handler still enforces the real business scope

API Platform metadata should not become the only security layer.

### 9. Map Exceptions Deliberately

Do not let domain errors fall through as generic runtime failures when the API contract is known.

Map the relevant failures explicitly, for example:

- invalid URI or payload values to `BadRequestHttpException`
- domain conflicts to `ConflictHttpException`
- missing authentication or permission to `AccessDeniedHttpException`
- not found cases to `NotFoundHttpException`

If the repo already uses an exception unwrapper trait for the module, reuse it instead of inventing a new mapping style.

### 10. Update OpenAPI and Serialization Together

Whenever an operation changes, verify all of these together:

- `ApiProperty` descriptions
- request or response examples when present
- response status declarations
- normalization and denormalization groups
- query parameter descriptions

OpenAPI, DTOs, and processor or provider behavior should describe the same contract.

### 11. Add the Right Tests

At minimum, add or update:

- functional tests for externally visible API behavior
- unit tests for processors or providers when branching or mapping logic is non-trivial

Cover:

- success path
- one meaningful failure path
- permission denial when relevant
- tenant or organization isolation when relevant
- serialization, mapped output, filters, or pagination when relevant

For API-facing changes, do not stop at handler tests alone.

### 12. Verify Supporting Wiring

If the resource introduces new classes, verify:

- service discovery under the module presentation namespace still picks them up
- module config remains consistent if explicit service definitions are needed
- related use case handlers and DTOs exist and match the resource contract
- module documentation should be updated when the public API changes

## Decision Points

### Processor vs Provider

- use a processor when the operation changes state or triggers side effects
- use a provider when the operation is read-only

### Shared Output DTO vs Per-Action Output DTO

- reuse a shared output DTO when the response contract is stable across operations
- create an action-specific output DTO when the returned shape meaningfully differs

### Resource-Level Security vs Permission Check in Code

- keep resource `security` as the coarse gate, usually role-level
- keep permission and scope checks in the provider or processor, with handler enforcement behind them

### Inline Operation Metadata vs New Resource

- extend an existing resource when the operation belongs to the same aggregate API surface
- create a distinct resource only when the contract or lifecycle is materially different

## Completion Checklist

The API Platform change is complete only if all of the following are true:

- resource and operation names are explicit and consistent
- input and output DTOs match the actual request and response contract
- processors or providers remain thin and delegate business logic to handlers
- permission and scope checks were reviewed explicitly
- exception mapping aligns with the intended HTTP contract
- OpenAPI metadata, query parameters, and serialization groups match runtime behavior
- pagination or filtering behavior is implemented, not just documented
- tests cover success and at least one meaningful failure or denial path

## Project-Specific Guardrails

- Follow `.github/instructions/backend.instructions.md` to keep business logic in application handlers rather than API Platform code
- Follow `.github/instructions/tests.instructions.md` for API-facing regression coverage expectations
- Follow `.github/instructions/security.instructions.md` when the resource touches Auth, OAuth, Session, Otp, TrustedDevice, Authorization, or Audit
- Follow `ARCHITECTURE.md` for the expected Presentation layer structure: Resource, Operation, Processor, Provider, DTO, Validator, and Serialization

## Combine With

- Use `add-use-case` when the resource needs a new command or query handler behind it.
- Use `module-tests` to add functional HTTP coverage and unit coverage for non-trivial provider or processor branching.
- Use `security-sensitive-change` when the resource touches sensitive modules, permission boundaries, cookies, tokens, or audit-visible behaviors.
- Use `new-module` when the resource belongs to a module that still needs initial scaffolding and wiring.

## Example Prompts

- `/api-platform-resource Equipment list endpoint with facility filter and pagination`
- `/api-platform-resource Facility create and patch operations with input-output DTOs`
- `/api-platform-resource Organization read model provider with scoped pagination`
- `/api-platform-resource OAuth client resource review for OpenAPI and exception mapping`
