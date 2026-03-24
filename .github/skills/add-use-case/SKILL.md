---
name: add-use-case
description: 'Add a new command or query use case in this Symfony hexagonal backend. Use for CQRS handlers, Command/Query DTOs, Result DTOs, API Platform processors/providers, input-output DTOs, permission checks, tenant or organization scoping, exception mapping, and the required unit or functional tests.'
argument-hint: 'Module + command/query + operation, for example: Equipment command assign-to-facility'
---

# Add Use Case

## When to Use

- Add a new command to an existing module
- Add a new query to an existing module
- Extend API behavior with a new Processor or Provider
- Wire a handler through the existing CQRS structure
- Add the minimal tests required for a new use case

## Required Inputs

Before writing code, identify:

- Module name, for example `Equipment`, `Facility`, `Organization`
- Use case type: `Command` or `Query`
- Business action, for example `CreateEquipment`, `ListFacilities`, `AssignToFacility`
- API shape: new endpoint, existing endpoint extension, or internal-only use case
- Scope and authorization rules: `tenantId`, `organizationId`, ownership checks, permission name
- Expected success result and expected failure modes

If any of these are unclear, inspect the target module's `MODULE.md`, nearby handlers, processors, providers, DTOs, and tests before editing.

## Procedure

## Assets

Use these templates when you need a concrete starting point:

- [Command template](./assets/command-template.php.txt)
- [Query template](./assets/query-template.php.txt)
- [Handler template](./assets/handler-template.php.txt)
- [Result template](./assets/result-template.php.txt)
- [Service wiring snippet](./assets/module-services-snippet.yaml.txt)
- [Equipment-style command template](./assets/equipment-command-template.php.txt)
- [Equipment-style handler template](./assets/equipment-handler-template.php.txt)
- [Equipment-style result template](./assets/equipment-result-template.php.txt)
- [Paginated query template](./assets/paginated-query-template.php.txt)
- [Reference notes](./references/patterns.md)

### 1. Anchor on an Existing Pattern

- Open the target module and locate the closest existing use case with the same shape.
- Reuse the module's naming, folder layout, result mapping style, and exception handling style.
- Keep architecture boundaries strict:
  - Presentation orchestrates HTTP only
  - Application handlers contain business orchestration
  - Domain stays framework-agnostic
  - Infrastructure implements outbound ports

### 2. Decide the Flow Shape

For a `Command`:

- Create `Application/UseCase/Command/<Area>/<UseCase>/<UseCase>Command.php`
- Create `Application/UseCase/Command/<Area>/<UseCase>/<UseCase>Handler.php`
- Create `Application/UseCase/Command/<Area>/<UseCase>/<UseCase>Result.php`
- Add or reuse a `Presentation/Api/Processor/...` if this is API-facing

For a `Query`:

- Create `Application/UseCase/Query/<Area>/<UseCase>/<UseCase>Query.php`
- Create `Application/UseCase/Query/<Area>/<UseCase>/<UseCase>Handler.php`
- Create `Application/UseCase/Query/<Area>/<UseCase>/<UseCase>Result.php`
- Add or reuse a `Presentation/Api/Provider/...` if this is API-facing

If the use case needs new persistence or cross-module data access:

- Add or extend an outbound port in `Application/Port/Outbound/...`
- Implement it in Infrastructure
- Do not bypass the port from Presentation or Domain

### 3. Design the Handler Contract First

- Define the command or query with explicit fields
- Define a typed result object instead of returning raw arrays
- Keep handler responsibilities explicit:
  - validate required inputs
  - enforce business invariants
  - call outbound ports
  - return a result DTO

Use value objects or domain methods for domain validation when possible. Do not move business rules into API Platform processors or providers.

### 4. Preserve Scope and Security

For every read or write, verify:

- which identifier establishes scope: `tenantId`, `organizationId`, both, or ownership
- whether repository lookups and writes are properly scoped
- which permission string must be checked in Presentation
- whether missing authentication should return access denied
- whether invalid URI variables should return bad request

Security-sensitive modules must fail closed. Never weaken token, session, OTP, authorization, or audit behavior while adding a use case.

### 5. Build the Presentation Layer

For API-facing commands:

- add or update input DTOs under `Presentation/Api/Dto/Input/...`
- add or update output DTOs under `Presentation/Api/Dto/Output/...`
- implement a `ProcessorInterface` processor
- extract route variables from `uriVariables`
- fetch the authenticated user from Symfony Security
- check permissions before dispatching the command
- translate domain and validation failures into the expected HTTP exceptions
- map the result object to the output DTO

For API-facing queries:

- implement a `ProviderInterface` provider
- check permissions before asking the query bus
- map filters, pagination, and route variables explicitly
- return the expected output shape or paginator style already used by the module

Keep processors and providers thin. They orchestrate request parsing, permission checks, bus dispatch, and result mapping only.

### 6. Update Resource or Operation Wiring

- Add or update the Api Platform resource or operation definitions in the module
- Verify HTTP method, URI template, input class, output class, and processor or provider
- If behavior is externally visible, consider OpenAPI and serialization impact

### 7. Add Tests at the Right Levels

At minimum, add the closest matching regression coverage:

- Unit test for the handler in `tests/Unit/<Module>/Application/UseCase/...`
- Functional API test when the use case is exposed over HTTP

Cover both:

- success path
- relevant failure path

Also cover when relevant:

- permission denied
- tenant or organization isolation
- invalid input mapping
- not found or conflict behavior

Test behavior, not implementation details. Keep unit tests deterministic and mock outbound ports only.

### 8. Check Supporting Configuration

If the new use case introduces new wiring, verify:

- module service configuration under `config/modules/*.yaml`
- Doctrine mapping if new records were added
- serializer or validation config if the module uses it
- module documentation when the API surface changed

## Decision Points

### Command vs Query

- Choose `Command` when state changes or side effects occur
- Choose `Query` when the flow is read-only

### New Handler vs Extend Existing Handler

- Create a new handler for a distinct business action
- Extend an existing handler only when the new behavior is the same use case with a small contract change

### New Port vs Reuse Existing Port

- Reuse a port when the capability already belongs to it
- Add a new port method when the handler needs a new persistence or integration capability
- Do not leak Doctrine or framework details into Application or Domain

### New Endpoint vs Existing Endpoint Change

- Add a new operation when the action has its own permission, lifecycle, or failure semantics
- Extend an existing operation only when the contract remains coherent and backwards compatible

## Completion Checklist

The use case is complete only if all of the following are true:

- Command or Query, Handler, and Result exist in the correct module path
- Handler owns business orchestration and returns an explicit result object
- Presentation code is thin and performs permission and input checks only
- Tenant, organization, and ownership scoping were verified
- Expected exceptions map to the correct HTTP behavior
- Tests cover success and at least one meaningful failure path
- API wiring and DTO mapping compile logically with the module's existing patterns
- Documentation was updated if the public API changed

## Project-Specific Guardrails

- Follow `ARCHITECTURE.md` for module layout and dependency direction
- Follow `.github/instructions/backend.instructions.md` for application-layer placement of business rules
- Follow `.github/instructions/tests.instructions.md` for test expectations
- Follow `.github/instructions/security.instructions.md` when touching Auth, OAuth, Session, Otp, TrustedDevice, Authorization, or Audit

## Combine With

- Use `api-platform-resource` when the use case needs a new or updated HTTP operation, DTO contract, provider, or processor.
- Use `module-tests` to add the matching unit, integration, or functional regression coverage.
- Use `security-sensitive-change` when the use case touches Auth, OAuth, Session, Otp, TrustedDevice, Authorization, Audit, or sensitive permission boundaries.
- Use `new-module` first when the target bounded context does not exist yet.

## Example Prompts

- `/add-use-case Equipment command commission-equipment`
- `/add-use-case Facility query list-child-facilities`
- `/add-use-case Organization command update-organization-settings`
- `/add-use-case OAuth query get-client-details`
