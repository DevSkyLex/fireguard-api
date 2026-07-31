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

## `phpunit` takes no `-d memory_limit` — `bin/console` does

The rule that every backend command needs `-d memory_limit=1G` is about **`bin/console`**, which builds the container and dies at the 128 MB default. It does **not** extend to phpunit, and adding it there is a small regression: `permissions.allow` lists `Bash(php vendor/bin/phpunit:*)`, and the `php -d …` form does not match that prefix, so every test run starts asking for approval.

```bash
php vendor/bin/phpunit --filter <Name>Test        # correct — no flag
php -d memory_limit=1G bin/console debug:router   # correct — flag mandatory
```

`phpunit.dist.xml` sets `<ini name="memory_limit" value="2G" />` at bootstrap, which is why a bare run works (measured: 210 MB used, well past the php.ini ceiling). The Makefile passes the flag on its phpunit targets belt-and-braces; that is not a reason to copy it into an ad-hoc command.

## The formatter strips an import you have not used *yet*

`php-cs-fixer` runs as a PostToolUse hook on **every** edit, and it removes unused imports. Add an import in one edit and its first usage in a later edit, and the import is gone before the second edit lands — phpstan then reports `class.notFound` on a name that looks right in your diff. **Put the import and its first usage in the same edit.**
