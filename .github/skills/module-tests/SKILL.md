---
name: module-tests
description: 'Add or update tests in this Symfony hexagonal backend. Use for choosing Unit, Integration, or Functional coverage, mirroring module structure under tests/, writing PHPUnit 12 tests with attributes, covering success and failure paths, permission denials, tenant or organization isolation, API status codes, serialization, filters, repository persistence, and regression assertions.'
argument-hint: 'Module + flow + test level, for example: Equipment assign-to-facility functional and unit tests'
---

# Module Tests

## When to Use

- Add tests for a new command, query, repository, processor, or provider
- Expand coverage after a bug fix or behavioral change
- Decide whether a case belongs in Unit, Integration, or Functional tests
- Review whether a module has the minimum useful regression coverage

## Required Inputs

Before writing tests, identify:

- target module and flow
- what behavior changed or must be protected
- whether the risk is in pure business logic, persistence, or HTTP behavior
- expected success path
- expected failure or denial path
- whether tenant, organization, user, or permission isolation is relevant

If any of these are unclear, inspect the handler, repository, processor or provider, nearby tests, and `.github/instructions/tests.instructions.md` before writing anything.

## Procedure

## Assets

Use these templates when you need a fast but repo-aligned starting point:

- [Unit handler test template](./assets/unit-handler-test-template.php.txt)
- [Integration repository test template](./assets/integration-repository-test-template.php.txt)
- [Functional API test template](./assets/functional-api-test-template.php.txt)
- [Functional auth API test template](./assets/functional-auth-api-test-template.php.txt)
- [Functional CRUD API test template](./assets/functional-crud-api-test-template.php.txt)
- [Functional security API test template](./assets/functional-security-api-test-template.php.txt)
- [Regression checklist](./assets/regression-checklist.md)
- [Reference notes](./references/patterns.md)

### 1. Choose the Right Test Level

Use the smallest level that proves the behavior, then add a higher level only when it protects a real contract.

Choose `Unit` when you need to verify:

- handler orchestration
- domain behavior and invariants
- provider or processor branching with mocked buses or ports
- mapper or serializer logic that does not need the full kernel

Choose `Integration` when you need to verify:

- Doctrine repository queries and persistence
- entity manager wiring
- fixtures or adapters that depend on the Symfony container or real infrastructure wiring
- tenant or organization filtering at the repository layer

Choose `Functional` when you need to verify:

- HTTP status codes
- request and response serialization
- API-level permission denial
- filter, pagination, or URI variable behavior
- public contract behavior exposed through API Platform

Do not jump to functional tests when a unit test would prove the behavior more directly.

### 2. Mirror the Module Structure

Place tests where intent is obvious:

- `tests/Unit/<Module>/Application/UseCase/...`
- `tests/Unit/<Module>/Presentation/Api/...`
- `tests/Integration/<Module>/Infrastructure/...`
- `tests/Functional/Api/...` or the existing API test structure already used by the repo

Mirror the source path as closely as practical. Readers should be able to infer the covered class from the test path alone.

### 3. Follow Repo Test Style

Use PHPUnit 12 attributes consistently:

- `#[CoversClass(...)]`
- `#[Test]`

Use the right base class:

- `PHPUnit\Framework\TestCase` for unit tests
- `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase` for integration tests
- `Symfony\Bundle\FrameworkBundle\Test\WebTestCase` for functional HTTP tests

Keep unit tests deterministic and avoid framework bootstrapping there.

### 4. Write Behavior-First Unit Tests

For handlers and application services:

- mock outbound ports and external dependencies only
- create real domain objects when possible instead of over-mocking final or readonly classes
- use support factories when available
- assert business results, state changes, and exception behavior

Typical unit coverage in this repo includes:

- happy path result assertions
- invalid input exceptions
- repository or port save behavior only when it is contract-critical
- partial update behavior
- permission or access denial mapping in processors or providers

Do not test private implementation details or framework internals.

### 5. Write Real Integration Tests for Persistence

For repositories and infrastructure adapters:

- boot the kernel
- obtain the correct entity manager from the container
- instantiate the real repository or adapter
- persist representative records and flush when needed
- assert find, list, save, delete, and scope-filter behavior
- close the entity manager in `tearDown`

When the repository is scope-sensitive, always add a cross-tenant or cross-organization negative assertion.

### 6. Write Contract-Focused Functional Tests

For API-facing behavior:

- create a `WebTestCase` client
- perform real HTTP requests against the target endpoint
- assert status codes explicitly
- assert body shape, mapped output, and serialization where relevant
- assert unauthenticated or forbidden behavior where relevant
- assert filter or route variable behavior when the endpoint exposes it

Use functional tests to prove the externally visible contract, not to duplicate all handler internals.

### 7. Cover Both Success and Failure Paths

Every changed behavior should have at least:

- one success assertion
- one meaningful failure or denial assertion

Add the relevant failure shape for the target flow:

- invalid input
- not found
- conflict
- unauthenticated access
- forbidden access
- tenant or organization isolation
- invalid enum or value object construction
- repository miss or filtered-out data

If the change fixes a bug, add the regression assertion that would have failed before the fix.

### 8. Cover Isolation and Permissions When Relevant

For modules with tenant, organization, ownership, or permission rules, tests must prove that invalid access is denied.

Typical expectations in this repo:

- user A cannot access user B resources
- tenant A data does not appear for tenant B
- organization A actions do not affect organization B
- API endpoints return `401` or `403` for unauthenticated or forbidden requests when that is the established contract

Do not treat isolation as optional coverage in sensitive flows.

### 9. Use Support Helpers Instead of Brittle Test Setup

Prefer existing factories and deterministic helpers when available, for example:

- support factories for real domain objects
- deterministic ID or event providers for stable assertions

This reduces mocking noise and keeps tests closer to real business behavior.

### 10. Keep Assertions High-Value

Good assertions in this repo typically verify:

- returned result DTO values
- persisted or retrieved domain state
- thrown exception type and message when the message is part of the contract
- status codes and JSON fields for API behavior
- collection counts and scope filtering for repositories

Low-value patterns to avoid:

- asserting every internal mock call when only the outcome matters
- booting the kernel in unit tests
- duplicating framework behavior already covered elsewhere
- writing only happy-path tests for risky flows

## Decision Points

### Unit vs Integration

- choose unit when mocking ports is enough to prove the business rule
- choose integration when correctness depends on Doctrine queries, mappings, or container-backed adapters

### Integration vs Functional

- choose integration to prove repository and infrastructure behavior
- choose functional to prove the public HTTP contract

### Real Domain Objects vs Mocks

- prefer real domain objects and test factories for aggregates and value objects
- mock ports, buses, and external adapters

### One Test File or Multiple Files

- keep one test file per class or closely related contract when practical
- split files when behavior branches are large enough to reduce readability

## Completion Checklist

The test work is complete only if all of the following are true:

- the chosen test level matches the risk being covered
- test paths mirror the module or class being tested
- PHPUnit 12 attributes are used consistently
- success and failure paths are both covered
- permission or tenant or organization isolation is covered when relevant
- tests remain deterministic and avoid unnecessary framework bootstrapping
- regression assertions were added for bug fixes
- API-facing changes verify status codes, serialization, filters, or mapped output when relevant

## Project-Specific Guardrails

- Follow `.github/instructions/tests.instructions.md` as the baseline for all test changes
- Follow `.github/instructions/backend.instructions.md` when deciding where business rules belong, because that determines what unit tests should target
- Follow `.github/instructions/security.instructions.md` when testing Auth, OAuth, Session, Otp, TrustedDevice, Authorization, or Audit

## Combine With

- Use `add-use-case` when the behavior under test comes from a new command or query.
- Use `api-platform-resource` when the regression risk is at the HTTP contract, DTO mapping, filters, or pagination layer.
- Use `new-module` when you need the first representative test suite for a freshly scaffolded module.
- Use `security-sensitive-change` when denial paths, cookies, tokens, rate limits, or fail-closed behavior are part of the risk surface.

## Example Prompts

- `/module-tests Equipment create-equipment command unit plus functional coverage`
- `/module-tests Facility repository integration tests for organization isolation`
- `/module-tests Auth login denial paths and rate limit functional tests`
- `/module-tests Authorization role repository integration regression`
