# Agent Instructions

These instructions are mandatory for AI agents working in this repository. They are
imported by [CLAUDE.md](CLAUDE.md) and apply to any assistant, not one vendor's.

This file states the **architectural rules**. The Claude Code **tooling** — agents,
commands, skills, MCP servers, hooks — is documented in
[.claude/README.md](.claude/README.md).

## First Read

- Treat [ARCHITECTURE.md](ARCHITECTURE.md) — the Module Architecture Standard — as the
  normative source for backend architecture, and [SECURITY.md](SECURITY.md) for anything
  touching authentication, tokens, sessions, or the audit ledger.
- Before changing a module, read that module's `src/<Module>/MODULE.md`.
- Existing mismatches are transitional. Do not copy them as precedent for new code.
- Keep changes focused on the requested task and move touched code toward the target
  architecture when practical.

## Project Context

- Read [composer.json](composer.json) for the stack and its versions, and [Makefile](Makefile)
  for the commands. Do not restate either here — a hand-maintained copy goes stale.
- What the manifests do not state, and what this repo therefore requires: hexagonal
  four-layer modules under `src/<Module>/`, API Platform as the HTTP surface,
  `declare(strict_types=1)` everywhere, `final readonly class` for handlers and value
  objects, and **two separate databases** (see below).
- Two HTTP surfaces sit outside API Platform's resource/DTO shape, both deliberate: the
  Stripe webhook (`config/routes/billing.yaml` → `Billing\Presentation\Controller\StripeWebhookController`)
  and the calendar ICS feed (an API Platform operation wired to a `controller:`,
  `src/Calendar/Presentation/Api/Resource/CalendarFeedIcsResource.php:39`). They are
  exceptions justified in their own files, not a pattern to copy.
- Do not add dependencies or new architectural patterns unless the task explicitly requires
  it and the existing codebase has no suitable pattern.

## The Two Databases

The single most expensive thing to get wrong here, because **it fails silently**.

- `auth` holds OAuth, User, Otp, Authorization, Session, Tenant, TrustedDevice, Audit.
  `main` holds the business modules. Separate entity managers, separate migration
  histories, separate containers, **no joins between them**.
  `config/packages/doctrine.yaml` is the authority on which module lives where.
- Every repository, processor, and provider that touches Doctrine names its entity manager
  explicitly in `config/modules/<module>.yaml`
  (`$entityManager: '@doctrine.orm.main_entity_manager'`). Omit it and autowiring picks
  `default_entity_manager`, which is **`auth`**: the code compiles, static analysis passes,
  the container lints — and a business module quietly queries the wrong database. Relying on
  the default is only ever defensible for an `auth`-side service, and then it is said in a
  comment (see `config/modules/audit.yaml`).
- Every migration command names its configuration
  (`--configuration=config/migrations/{auth,main}.yaml`), or goes through `make migrate-auth`
  / `make migrate-main`.
- **Never edit an existing migration.** Write a new one.

## Layer Direction

- One-way: Presentation → Application → Domain · Infrastructure → Application (it implements
  the ports) · Domain depends on nothing outside itself and `SharedDomain`.
- **Use cases are the single entry point for business logic.** Not processors, not providers,
  not controllers, not repositories.
- External dependencies go through ports: a handler injects `…Port` interfaces from
  `Application/Port/`, never a Doctrine repository, an adapter, or an `EntityManagerInterface`.
- **Cross-module access is through `Application\Port\` and `Application\Contract\` only** —
  never a sibling module's `Domain\`, `Infrastructure\`, or `Record`.
- Do not trust the tooling to catch a boundary break. `deptrac.yaml` permits
  `Presentation → Infrastructure` outright, and **no collector sees a cross-module edge at
  all** — importing a sibling's `Domain\` is green. That one is on the reviewer.

## Module Structure

- Follow the layout in [ARCHITECTURE.md](ARCHITECTURE.md) § Standard Directory Layout:
  `Application/{UseCase,Port/{Outbound,Inbound},Contract,Service}`,
  `Domain/{Model,ValueObject,Event,Exception}`, `Infrastructure/{Persistence,Adapter,…}`,
  `Presentation/Api/{Resource,Operation,Dto,Processor,Provider}`. Do not invent undocumented
  sibling folders, and do not create empty ones.
- Naming follows § Naming Conventions: `<Action>Command` / `<Action>Query` +
  `<Action>Handler` + `<Action>Result`, `<Capability>Port`, `<Capability>Adapter` or
  `<Entity>Repository`, `<Action>Processor`, `<Resource>Provider`, `<Action>Input` /
  `<Action>Output`, `<Module>Operations`. Namespaces mirror folders.
- Group ports by area under `Application/Port/Outbound/<Area>` when it keeps the layer
  readable.
- A new bounded context is a new `src/<Module>/`, with its own `config/modules/<module>.yaml`
  and its own `MODULE.md`.

## Use Cases

- Handlers hold the business logic and inject ports only.
- Writes go through `CommandBusPort`, reads through `QueryBusPort`.
- Handlers **return Result objects, not raw arrays**. A command handler with nothing to hand
  back may return `void`; what is forbidden is an untyped array or a framework type.
- Domain events are dispatched **after** the durable save, never before.
- Invariants belong inside the Domain model or value object, not in the handler that calls it.

## Presentation And The API Contract

- Every endpoint carries all six: a Resource with its route and security, an Operation
  constant and metadata, Input/Output DTOs with serialization groups, a Processor (write) or
  Provider (read), validation plus error mapping, and functional tests for the success **and**
  the error cases.
- Business logic never lands in a processor or a provider. They translate; the handler decides.
- Error mapping is centralized in an EventSubscriber. Do not hand-roll a `try`/`catch` that
  produces an HTTP response in a processor.
- Reference-catalog endpoints (lists for UI selects) follow § Reference Catalog Endpoints:
  module-local routes such as `/facilities/statuses`, a minimal `value`/`label` contract, and
  no generic `/options` or `/lookups` aggregator.
- Keep resource-level security as the coarse gate, and add explicit permission, tenant, or
  organization checks wherever the result is contextual.

## Security

- Auth, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC permissions, the audit ledger,
  multi-tenant scoping, secrets, and the Stripe billing webhook are the crown-jewel paths.
  Read [SECURITY.md](SECURITY.md) before touching one.
- **Every bearer token is signature-verified before any of its claims is trusted**, and that
  check is never conditional — the database lookup keys on `jti` and never binds it back to
  `sub`, so a branch that skipped verification would authenticate a forged token as an
  arbitrary subject (`SECURITY.md` § Token and cookie security).
- Fail closed. A missing scope, an absent tenant, or an unresolved permission denies.
- Public endpoints are explicit and limited, declared in `config/packages/security.yaml`.
- Never log or serialize tokens, secrets, or password material.
- A change to any of these paths owes a **denial-path test**, not only a happy-path one.

## Configuration

- Each module has `config/modules/<module>.yaml`: port-to-adapter aliases, processors,
  providers, event subscribers, and the explicit entity manager.
- A port aliased to nothing passes static analysis and fails at runtime — `make lint`
  (`lint:container`) is what catches it.
- Environment-driven configuration lives in `config/packages` or `.env`, never inline.

## Testing

- Unit tests in `tests/Unit/<Module>` (handlers, adapters, domain models and value objects),
  functional in `tests/Functional/Api` (endpoint contracts), E2E in `tests/E2E` (full flows).
  The test path mirrors `src/`.
- A new endpoint owes at minimum: a handler unit test, a processor or provider unit test, and
  a functional API test covering success **and** denial.
- The suite runs on **PostgreSQL because production does**. Never substitute SQLite to make a
  test pass, and never change production code to make a test green.
- Test databases: **`make test-db` alone** is the whole setup — it creates both databases,
  migrates each configuration, and loads the fixtures with `--env=test`. `make seed-fixtures`
  runs *without* `--env=test` and seeds the **dev** databases; it is not part of test setup.
- Those two test databases are templates. Each run clones them and works on the copy, so no
  test may assume it owns the database, and `make test-db` is the only thing that moves the
  baseline.

## Documentation

- `src/<Module>/MODULE.md` carries seven sections: Overview, API endpoints, Flows,
  Architecture, Configuration, Testing, Error codes.
- Update it **in the same change** that adds or alters an endpoint, a flow, an error code, or
  a configuration requirement. A new module ships with one.
- Keep it normative. Do not turn it into a file catalog.

## Code Style

- `declare(strict_types=1);` · **two-space indentation** (the PHP-CS-Fixer config sets
  `setIndent('  ')`, not PSR-12's four) · `// #region` blocks · `final readonly class` for
  handlers and value objects · PHPDoc with `@category`, `@version`, `@author` on classes and
  `@since`/`@param`/`@return` on methods · grouped imports with explicit `use function` ·
  typed class constants.
- `make cs-fix` rewrites files rather than merely checking them; run it before the gate.

## Verification

- A bare `php bin/console …` dies building the container
  (`Allowed memory size of 134217728 bytes exhausted`). Always
  `php -d memory_limit=1G bin/console …`, as every Makefile target does.
- Run the narrowest useful check first, widening as the blast radius grows: `make cs-fix`,
  `make phpstan`, `make deptrac`, `make lint`, `make phpunit-fast`, then `make test`
  (`cs-lint phpstan deptrac lint openapi-check schema-check phpunit-parallel`).
- Two of those are easy to forget and have both drawn blood: `make openapi-check` fails when
  `openapi.json` is stale after an endpoint change (regenerate with
  `api:openapi:export --output=openapi.json`), and `make schema-check` validates the mapping
  against **both** entity managers on the test databases. Run them whenever you touch an
  endpoint or a Doctrine Record.
- If validation cannot be run, state the reason clearly in the final response.
- Do not fix unrelated failures unless the user asks for that work.
