---
description: Write or repair PHPUnit tests — unit for handlers and domain, integration for repositories, functional for endpoint contracts including denial paths, E2E for full flows.
argument-hint: '<Module> <flow> [level] — e.g. "Equipment assign-to-facility functional and unit"'
---

Delegate to the **fg-test-writer** subagent: $ARGUMENTS

Require it to:

1. **Pick the level deliberately** — unit (handlers with ports mocked, domain models, adapters), integration (Doctrine repositories against a real schema), functional (the HTTP contract), E2E (a flow across endpoints), and say why.
2. Mirror `src/` exactly in the test path: `src/Facility/Application/UseCase/Command/Facility/ArchiveFacility/ArchiveFacilityHandler.php` → `tests/Unit/Facility/Application/UseCase/Command/Facility/ArchiveFacility/ArchiveFacilityHandlerTest.php`.
3. For a handler test: mock **every port**; assert the Result field by field, which ports were called with what, the domain exception on each failure path, and the event dispatched — **plus the idempotent path where it must not be**.
4. For a functional test, cover the **denial paths**, which are the point: **403** for an authenticated-but-unentitled caller and **404** for a record in another organization. A 200-only test proves the endpoint exists and nothing about who may call it. If those tests are missing for an existing endpoint, that gap **is** the finding.
5. Use PHPUnit 12 attributes, not annotations.
6. **Never change production code to make a test pass.** An untestable boundary is a finding for the reviewer, not something to paper over.
7. Never weaken an assertion to `assertNotNull` where the exact status, enum literal, or Result field is the contract. Leave no `markTestSkipped`.

Run with `make test-db` and `make seed-fixtures` first (both databases), then `make phpunit-fast` or a targeted `php vendor/bin/phpunit --filter`. The suite runs on **PostgreSQL because production does** — substituting SQLite discards most of what an integration test checks.
