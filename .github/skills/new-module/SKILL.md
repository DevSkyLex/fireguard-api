---
name: new-module
description: 'Create a brand new module in this Symfony hexagonal backend. Use for scaffolding src/<Module> with Application, Domain, Infrastructure, Presentation, config/modules wiring, Doctrine mapping, migrations, API Platform resources, ports and adapters, MODULE.md, and the minimum unit, integration, or functional tests.'
argument-hint: 'Module name and purpose, for example: Inspection-like asset compliance module on main database'
---

# New Module

## When to Use

- Create a new bounded context under `src/<Module>`
- Add a new domain area with its own API, persistence, and tests
- Introduce a new module that depends on existing shared ports or exposes new contracts
- Scaffold a module before implementing detailed use cases

## Required Inputs

Before creating files, determine:

- module name
- business purpose and scope boundaries
- data ownership and isolation model: `tenantId`, `organizationId`, user ownership, or global
- target database and entity manager: `auth` or `main`
- whether the module is API-facing or internal-only
- first use cases to support, usually one command and one query
- cross-module dependencies and which side owns the contract

If those answers are incomplete, inspect nearby modules with similar behavior before scaffolding.

## Procedure

## Assets

Use these templates and snippets to scaffold the module consistently:

- [Service config template](./assets/module-services-template.yaml.txt)
- [Doctrine mapping snippet](./assets/doctrine-mapping-snippet.yaml.txt)
- [MODULE.md template](./assets/module-doc-template.md)
- [Module checklist](./assets/module-checklist.md)
- [Reference notes](./references/patterns.md)

### 1. Pick the Closest Reference Module

- Find an existing module with similar persistence, API shape, and security requirements.
- Reuse its folder layout, naming style, DTO style, exception naming, and config wiring style.
- Prefer repo-specific patterns over generic Symfony defaults.

Good anchors in this repo:

- `Equipment` for full CRUD plus lifecycle commands
- `Facility` for organization-scoped hierarchy and validation
- `Organization` for broad business module patterns
- `Auth`, `OAuth`, `Session`, `Otp`, `TrustedDevice`, `Authorization`, `Audit` for security-sensitive flows

### 2. Create the Module Skeleton

Create `src/<Module>/` with only the folders actually needed, following the standard structure:

- `Application/Port/Outbound`
- `Application/Port/Inbound` when other modules should call into this one
- `Application/Contract` for stable cross-module DTOs or enums
- `Application/UseCase/Command/<Area>/<UseCase>/`
- `Application/UseCase/Query/<Area>/<UseCase>/`
- `Domain/Model`
- `Domain/ValueObject`
- `Domain/Event`
- `Domain/Exception`
- `Infrastructure/Adapter`
- `Infrastructure/Persistence/Doctrine/Record`
- `Infrastructure/Persistence/Doctrine/Repository`
- `Infrastructure/DataFixtures` when test or seed data is needed
- `Presentation/Api/Resource`
- `Presentation/Api/Operation`
- `Presentation/Api/Processor`
- `Presentation/Api/Provider`
- `Presentation/Api/Dto/Input`
- `Presentation/Api/Dto/Output`
- `Presentation/Api/Validator` when custom validation is needed
- `Presentation/Api/Serialization` when serialization behavior needs module-local helpers

Do not put business rules in Presentation or Infrastructure.

### 3. Define the Module Boundaries First

Before wiring endpoints, establish:

- aggregate roots and core domain vocabulary
- value objects for identifiers and constrained values
- domain exceptions that represent business failures
- outbound ports for persistence or external integrations
- inbound ports or contracts only when other modules must depend on this module

The Application layer should orchestrate business logic. Domain must remain framework-agnostic and must not depend on Symfony, Doctrine, or HTTP classes.

### 4. Start with One Command and One Query

Scaffold at least one representative write and one representative read flow unless the module is intentionally read-only or write-only.

For a command use case, add:

- `<UseCase>Command.php`
- `<UseCase>Handler.php`
- `<UseCase>Result.php`

For a query use case, add:

- `<UseCase>Query.php`
- `<UseCase>Handler.php`
- `<UseCase>Result.php`

Handlers should:

- validate required inputs
- enforce scoping and invariants
- call ports, not adapters directly
- return explicit result objects, not raw arrays

### 5. Add Persistence Through Ports and Doctrine Records

If the module persists data:

- define repository ports in `Application/Port/Outbound`
- implement repositories in `Infrastructure/Persistence/Doctrine/Repository`
- map persistence classes under `Infrastructure/Persistence/Doctrine/Record`
- add mappers only when domain and record conversion is non-trivial

Choose the correct entity manager deliberately:

- `auth` for identity, auth, session, security, OAuth, and related sensitive stores
- `main` for tenant or organization business modules such as facilities, equipment, inspections, notifications, onboarding

Do not mix the wrong entity manager into the repository constructor wiring.

### 6. Wire the Module Services

Create `config/modules/<module>.yaml` and wire:

- `Presentation` namespace as a resource
- concrete repository services with the correct entity manager argument
- aliases from ports to adapters or repositories
- explicit `messenger.message_handler` tags for command and query handlers
- any cross-module adapters needed by the module

Use existing module configs as the source of truth for service style.

### 7. Register Doctrine Mapping and Migrations

If the module has Doctrine records:

- add a mapping entry to `config/packages/doctrine.yaml` under the correct entity manager
- point the mapping to `src/<Module>/Infrastructure/Persistence/Doctrine/Record`
- use the correct namespace prefix and alias

If schema changes are needed:

- create migrations in the correct tree, `migrations/auth/` or `migrations/main/`
- keep database ownership aligned with the chosen entity manager

### 8. Build the API Platform Layer

For API-facing modules, add:

- resource classes under `Presentation/Api/Resource`
- operation definitions under `Presentation/Api/Operation`
- input and output DTOs under `Presentation/Api/Dto`
- processors for commands
- providers for queries

Processors and providers should remain thin. They are responsible for:

- parsing request or route inputs
- obtaining the authenticated user when needed
- enforcing permission checks before dispatch
- calling the command or query bus
- mapping result objects to output DTOs
- translating domain or validation failures into HTTP exceptions

Keep business rules in handlers, not in processors, providers, controllers, subscribers, or serializers.

### 9. Preserve Scoping and Permissions

Every new module must define its isolation model clearly.

Verify for each read and write:

- whether data is tenant-scoped, organization-scoped, owner-scoped, or system-scoped
- where `tenantId` or `organizationId` enters the flow
- which repository methods must enforce scope
- which permission names are checked in Presentation
- which denial paths need explicit tests

If the module is security-sensitive, prefer fail-closed behavior and avoid returning or logging secrets, tokens, OTPs, or unnecessary PII.

### 10. Add Module Documentation

Create `src/<Module>/MODULE.md` and include:

- module purpose
- API endpoint table
- main command and query flows, ideally with sequence diagrams
- domain model overview
- persistence notes and database ownership
- configuration locations
- error codes or notable exceptions
- test locations

Document the first public API shape as soon as the module exists. This repo consistently keeps `MODULE.md` files alongside modules.

### 11. Add the Minimum Test Suite

At minimum, add:

- unit tests for handlers under `tests/Unit/<Module>/Application/UseCase/...`
- integration tests for repositories or adapters when persistence logic is non-trivial
- functional API tests when the module exposes HTTP operations

Cover:

- success paths
- meaningful failure paths
- permission denial when relevant
- tenant or organization isolation when relevant
- API contract details like status codes or mapped output when relevant

Tests should validate behavior, not implementation details.

### 12. Final Verification Pass

Before considering the module scaffold complete, verify:

- namespace and folder names align exactly
- handlers are registered and dispatchable by Messenger
- Doctrine records are mapped under the correct entity manager
- repository port aliases resolve correctly
- resources, operations, processors, providers, DTOs, and validation line up coherently
- scope and permission names are consistent with the new API
- `MODULE.md` and tests exist

## Decision Points

### Choose `auth` vs `main`

- use `auth` when the module owns authentication, authorization, sessions, tokens, trusted devices, audit, or identity-related persistence
- use `main` for tenant and organization business data

### Expose Contracts vs Keep Internals Private

- add `Application/Contract` only for stable types meant to cross module boundaries
- do not expose domain models outside the module

### Create a New Port vs Depend on Shared Infrastructure

- add a module-local port when the module needs persistence or an external dependency
- depend on Shared contracts or bus ports when a shared abstraction already exists
- never inject infrastructure classes into handlers

### Public API First vs Internal Module First

- add Presentation resources immediately when the module is API-facing
- skip unnecessary API scaffolding when the module is internal-only, but keep the Application and Domain layers complete

## Completion Checklist

The module scaffold is complete only if all of the following are true:

- `src/<Module>/` follows the repo's hexagonal layout
- at least one command or query flow exists to validate the architecture
- ports and adapters are separated correctly
- `config/modules/<module>.yaml` exists and wires handlers, ports, and repositories
- Doctrine mapping is registered under the correct entity manager when persistence exists
- migrations target the correct database tree when schema changes exist
- API Platform resources, processors, providers, and DTOs exist when the module is API-facing
- scoping and permissions were designed explicitly
- `src/<Module>/MODULE.md` exists
- tests cover representative success and failure behavior

## Project-Specific Guardrails

- Follow `ARCHITECTURE.md` for the standard module structure
- Follow `.github/instructions/backend.instructions.md` for layer boundaries and use case ownership
- Follow `.github/instructions/tests.instructions.md` for test expectations
- Follow `.github/instructions/security.instructions.md` for security-sensitive modules and configs

## Combine With

- Use `add-use-case` right after scaffolding to build the module's first command and query flows.
- Use `api-platform-resource` when the module exposes HTTP resources through API Platform.
- Use `module-tests` to add the baseline unit, integration, and functional coverage expected for the first public behaviors.
- Use `security-sensitive-change` in parallel when the new module lives in Auth, OAuth, Session, Otp, TrustedDevice, Authorization, or Audit.

## Example Prompts

- `/new-module Compliance module on main database with organization-scoped CRUD and list API`
- `/new-module Vendor management module with one create command, one list query, Doctrine persistence, and API Platform resources`
- `/new-module Auth-adjacent device posture module on auth database with strict security and audit requirements`
