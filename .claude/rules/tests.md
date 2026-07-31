---
paths:
  - 'tests/**/*.php'
---

# PHPUnit tests

PHPUnit **12.5** with attributes (`#[Test]`, `#[DataProvider]`), not annotations. The test path mirrors `src/` exactly.

| Level       | Path                     | Covers                                            | Database? |
| ----------- | ------------------------ | ------------------------------------------------- | --------- |
| Unit        | `tests/Unit/<Module>/…`  | handlers (ports mocked), domain models, adapters  | no        |
| Integration | `tests/Integration/…`    | Doctrine repositories: query, mapping, constraint | yes       |
| Functional  | `tests/Functional/Api/…` | the HTTP contract, **denial paths**               | yes       |
| E2E         | `tests/E2E/…`            | a flow across several endpoints                   | yes       |

**Minimum for a new endpoint**: a processor-or-provider unit test, a handler unit test, and a functional API test. Three, not one.

## The handler test

Mock **every port**. Assert the Result field by field, which ports were called with what, the domain exception on each failure path, and the event dispatched — **plus the idempotent path where it must not be**. An action that re-dispatches on a second call is a real bug only a negative assertion catches.

A handler test that touches a database is testing the wrong unit.

## The functional test — denial paths are the point

Assert **403** for an authenticated-but-unentitled caller and **404** for a record in **another organization or tenant**. Those two prove isolation. 404 rather than 403 is deliberate: 403 confirms the record exists.

A suite that only covers 200 proves the endpoint exists and nothing about who may call it. If an existing endpoint has no such test, **that gap is the finding** — report it rather than adding another happy path.

## Rules

- **Never change production code to make a test pass.** An untestable boundary is a finding for the reviewer.
- Never weaken an assertion to `assertNotNull` where the exact status, enum literal, or Result field **is** the contract.
- No `markTestSkipped` left behind; no test that passes because its assertion never ran.
- Type mocks honestly — an untyped double hides a signature change and turns a compile error into a silent pass.
- A test that locks in an anti-pattern (logic asserted inside a processor, a raw-array Result) encodes the wrong contract. Flag it instead of extending it.

Setup: `make test-db` once, then `make seed-fixtures` (it knows about both databases). The suite runs on **PostgreSQL because production does** — never substitute SQLite.
