---
name: api-platform-contract
description: The API Platform surface in fireguard-sso-api — the six-item endpoint checklist, Resource/Operation/DTO/Processor/Provider layout, the reference-catalog decision, security placement, error mapping, and which status codes carry meaning. Use when adding or changing an endpoint.
---

# API Platform contract

`ARCHITECTURE.md`, Presentation Layer Standard. The Presentation layer **translates**; it never decides. A processor that branches on a business condition has taken the handler's job.

## The six-item checklist — all of them, every endpoint

- [ ] Resource with proper route **and security**
- [ ] Operation constant and metadata
- [ ] Input/Output DTOs
- [ ] Processor (write) or Provider (read)
- [ ] Validation rules and error mapping
- [ ] Functional tests for success **and error** cases

Five of six is a defect. Security and the error-case tests are the two that go missing.

## Layout

```text
Presentation/Api/
  Resource/<Name>Resource.php
  Operation/<Module>Operations.php          # typed constants
  Processor/<Area>/<Action>Processor.php    # writes
  Provider/<Area>/<Resource>Provider.php    # reads
  Dto/Input/<Area>/<Action>Input.php
  Dto/Output/<Area>/<Action>Output.php
  Validator/<Rule>/<Rule>.php + <Rule>Validator.php
  EventSubscriber/                          # centralized exception -> HTTP mapping
  Serialization/
```

Operation constants are typed (PHP 8.4):

```php
public const string CREATE_FACILITY = 'facility_create';
```

## Processor and provider

**May**: unwrap the Input DTO · dispatch through `CommandBusPort` / `QueryBusPort` · map the Result to an Output DTO · translate a domain exception into HTTP.

**May not**: hold a business rule · query a repository directly · reach into another module.

Wiring in `config/modules/<module>.yaml`, and **anything touching Doctrine names its entity manager explicitly**:

```yaml
Facility\Presentation\Api\Processor\Facility\CreateFacilityProcessor:
  arguments:
    $entityManager: '@doctrine.orm.main_entity_manager'
```

See the `dual-database` skill — omitting this is the silent wrong-database bug.

## Reference catalogs — the three-way decision

| Kind | What it is | Endpoint? |
| --- | --- | --- |
| **Static contract** | stable enum-like values, no scope or permission | **no** — OpenAPI or a shared contract suffices |
| **Reference catalog** | read-only list for a select, filter, or light UI metadata | yes — dedicated `GetCollection` under the owning module |
| **Business resource** | real collection with lifecycle, relations, search, writes | yes — a normal resource, never degraded into an option feed |

Add a reference catalog when at least one holds: the frontend should not hard-code the values · labels belong to the backend contract · values may change without a frontend redeploy · the list depends on permissions, tenant, organization, or country · the response needs more than a bare string list.

Route it **module-locally** — `/facilities/statuses`, `/inspections/results`, `/organizations/legal-types`. Never `/options`, `/lookups`, `/metadata/selects`, or an aggregated multi-list payload. Keep the output to `value` and `label` unless the frontend genuinely needs more. Providers stay thin and may read straight from an enum or registry.

**When the list is contextual, the permission or scope check goes in the provider.** Resource-level security is the coarse gate only.

## Status codes that carry meaning

| Code | When |
| --- | --- |
| 201 + `Location` | create |
| 204, no body | delete |
| 400 | malformed input |
| 401 | unauthenticated |
| **403** | authenticated but **not entitled** |
| **404** | a record outside the caller's scope — **deliberately not 403**, which would confirm it exists |
| 409 | conflict |
| 429 | rate limited |

The 403/404 distinction is a security decision, not a style choice.

## Security

Set it on the Resource **and** register the API access rule in `config/packages/security.yaml`. Public endpoints stay explicit and few.

A resource-level role check does **not** prove the caller owns *this* record. When the endpoint is scoped to a tenant or organization, the ownership check belongs in the handler or the provider. That gap is the IDOR, and the cross-tenant 404 functional test is what catches it.

## Errors

Symfony Validator constraints on the Input DTO. A custom rule gets its own folder holding both the constraint and its validator. Map domain exception → HTTP status **centrally in an `EventSubscriber`**, not in try/catch at each call site, so the mapping stays consistent. The error body follows RFC 7807 (`status`, `type`, `title`, `detail`) because the frontend parses that shape.

## DTOs

`<Action>Input` (received) / `<Action>Output` (returned). An Output DTO **never exposes a Domain type** — that would couple the wire format to internal invariants and make every refactor breaking.

Enum literals must match what the frontend expects **byte for byte**: `'in_progress'`, not `'inProgress'`. TypeScript will not catch a mismatch; a blank status pill in production will.

## Verify

```bash
php -d memory_limit=1G bin/console debug:router | grep <module>
php -d memory_limit=1G bin/console api:openapi:export --output=/tmp/openapi.json
make lint
php vendor/bin/phpunit tests/Functional/Api/<Path>
```

The exported OpenAPI is the generated source of truth for what the API actually publishes — prefer it over inferring from attributes. `-d memory_limit=1G` is required; a bare console call runs out of memory building the container.
