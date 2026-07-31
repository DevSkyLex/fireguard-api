---
name: module-testing
description: How to test fireguard-sso-api — which level covers what, the test path mirroring src/, the denial paths a functional test must assert, the PostgreSQL test databases, and the exact PHPUnit commands. Use before writing or running any test.
---

# Testing

`ARCHITECTURE.md`, Testing Standard. PHPUnit **12.5** with attributes (`#[Test]`, `#[DataProvider]`), not annotations.

## Pick the level

| Level | Path | Covers | Database? |
| --- | --- | --- | --- |
| **Unit** | `tests/Unit/<Module>/…` | handlers with ports mocked · domain models and value objects · adapters translating vendor types | no |
| **Integration** | `tests/Integration/…` | Doctrine repositories: the query, the mapping, the constraint | yes |
| **Functional** | `tests/Functional/Api/…` | the HTTP contract: status, body, headers, **denial paths** | yes |
| **E2E** | `tests/E2E/…` | a full flow across several endpoints | yes |
| **Architecture** | `tests/Architecture/…` | structural rules expressed as tests | no |

The path mirrors `src/` **exactly**:

```text
src/Facility/Application/UseCase/Command/Facility/ArchiveFacility/ArchiveFacilityHandler.php
tests/Unit/Facility/Application/UseCase/Command/Facility/ArchiveFacility/ArchiveFacilityHandlerTest.php
```

**Minimum for a new endpoint**: a processor-or-provider unit test, a handler unit test, and a functional API test. Three.

## The handler test

Mock every port. Assert the boundary the handler owns:

- the Result, field by field,
- which ports were called, with what arguments,
- the domain exception raised on each failure path,
- the event dispatched — **and the idempotent path where it must not be**. An action that re-dispatches on a second call is a real bug, and only an explicit negative assertion catches it.

No database, no container. A handler test that needs either is testing the wrong unit.

## The functional test — the denial paths are the point

Assert, per endpoint:

| Code | Case |
| --- | --- |
| 200 / 201 | success, including response shape and `Location` |
| 400 | malformed input |
| 401 | unauthenticated |
| **403** | authenticated but **not entitled** |
| **404** | a record belonging to **another organization or tenant** |
| 409 | conflict, where possible |
| 429 | where a rate limiter applies |

The **403** and the cross-tenant **404** are the two that matter most and the two most often missing. 404 rather than 403 for another tenant's record is deliberate: 403 confirms the record exists. If an endpoint has no such test, that gap *is* the finding — report it rather than filling the suite with happy paths.

## Running

```bash
make test-db            # create + migrate fireguard_auth_test and fireguard_main_test (once)
make seed-fixtures      # knows about both databases — use this, not doctrine:fixtures:load
make test-db-clean

make phpunit-fast       # quick suite
make phpunit-parallel   # what `make test` runs
make test               # cs-lint phpstan deptrac lint phpunit-parallel — the full gate
make coverage           # exports XDEBUG_MODE=coverage for this target only

php vendor/bin/phpunit --filter ArchiveFacilityHandlerTest
php vendor/bin/phpunit tests/Functional/Api/Facility
```

The suite runs on **PostgreSQL because production does**. Never substitute SQLite to make a test pass — the schema, the constraints, and the dialect are part of what is under test.

There are **two** test databases. A test hitting the wrong one fails confusingly; check which database owns your module (see the `dual-database` skill).

## Rules that catch real bugs

- **Never change production code to make a test pass.** If a unit is untestable at its boundary, that is a finding for the reviewer, not something to paper over.
- Never weaken an assertion to `assertNotNull` where the exact status, enum literal, or Result field **is** the contract.
- No `markTestSkipped` left in a committed test, and no test that passes because its assertion never executed.
- A test that locks in an anti-pattern — business logic asserted inside a processor, a raw-array Result — encodes the wrong contract. Flag it instead of extending it.
- Type mocks honestly. An untyped double hides a signature change and turns a compile error into a silent pass.
- Enum literals in an assertion match the backend string byte for byte (`'in_progress'`).

## Regression tests for security findings

A security fix without a regression test will be undone. For a confirmed risk, the test is the deliverable: the 403 for the unentitled caller, the cross-tenant 404, the 429 after the limit, the replayed webhook rejected.
