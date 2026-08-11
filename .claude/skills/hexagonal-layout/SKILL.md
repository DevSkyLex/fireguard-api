---
name: hexagonal-layout
description: Where each kind of backend file goes and what it may import — the four-layer module tree, the deptrac dependency rules, the naming scheme, and the house code style (two-space indent, regions, PHPDoc). Use before creating any file under src/.
---

# Hexagonal layout

`ARCHITECTURE.md` is the Module Architecture Standard; `deptrac.yaml` is its machine-checkable half. This skill is the fast lookup.

## Dependency direction — one way, always

```text
Presentation ──> Application ──> Domain
Infrastructure ──> Application        (it implements the ports)
Domain ──> nothing but SharedDomain
```

Deptrac's ruleset, verbatim: `Domain: [SharedDomain]` · `Application: [Domain, SharedDomain, SharedApplication]` · `Infrastructure: [Domain, Application, Shared*]` · `Presentation: [Application, Domain, Infrastructure, Shared*]` · `SharedDomain: ~` (nothing).

A PreToolUse hook blocks a Domain file importing `Application\`/`Infrastructure\`/`Presentation\`, and an Application file importing `Infrastructure\`/`Presentation\` — before `make deptrac` ever runs.

## Where things go

| You are creating | Path under `src/<Module>/` |
| --- | --- |
| command use case | `Application/UseCase/Command/<Area>/<UseCase>/` — Command, Handler, Result |
| query use case | `Application/UseCase/Query/<Area>/<UseCase>/` — Query, Handler, Result |
| outbound port (module calls out) | `Application/Port/Outbound/<Area>/<Capability>Port.php` |
| inbound port (module is called) | `Application/Port/Inbound/<Area>/<Capability>Port.php` |
| cross-module type | `Application/Contract/<Area>/` |
| aggregate / entity | `Domain/Model/<Aggregate>/` |
| value object | `Domain/ValueObject/` |
| domain event | `Domain/Event/<Area>/` |
| domain exception | `Domain/Exception/` |
| adapter | `Infrastructure/Adapter/<Capability>Adapter.php` |
| Doctrine repository | `Infrastructure/Persistence/Doctrine/Repository/<Entity>Repository.php` |
| Doctrine record | `Infrastructure/Persistence/Doctrine/Record/<Entity>Record.php` |
| a big integration | `Infrastructure/<Context>/Adapter/` — never a global `External/` |
| API resource | `Presentation/Api/Resource/<Name>Resource.php` |
| operation constants | `Presentation/Api/Operation/<Module>Operations.php` |
| processor (write) | `Presentation/Api/Processor/<Area>/<Action>Processor.php` |
| provider (read) | `Presentation/Api/Provider/<Area>/<Resource>Provider.php` |
| DTOs | `Presentation/Api/Dto/{Input,Output}/<Area>/` |
| custom validator | `Presentation/Api/Validator/<Rule>/` — the constraint **and** its validator |
| module doc | `MODULE.md` — **required** |

Namespaces mirror folders exactly.

## Naming

`<Action>Command` · `<Action>Query` · `<Action>Handler` · `<Action>Result` · `<Capability>Port` · `<Capability>Adapter` or `<Entity>Repository` · `<Action>Processor` · `<Resource>Provider` · `<Action>Input` / `<Action>Output` · `<Module>Operations`.

Contract types take names **distinct from use case Results**, so the two never blur at a call site.

## What may cross a module boundary

Allowed: `<Sibling>\Application\Port\…` and `<Sibling>\Application\Contract\…`.
Forbidden: `<Sibling>\Domain\…` and `<Sibling>\Infrastructure\…`.

To check a module in one command:

```bash
grep -rnE '^use (SiblingA|SiblingB)[\](Domain|Infrastructure|Presentation)[\]' src/<Module> --include=*.php
```

List the forbidden layers **positively** rather than excluding the two allowed ones with a
second `grep -v`: `Domain`, `Infrastructure` and `Presentation` are exactly the complement of
`Application\{Port,Contract}`, and one grep that can be wrong beats two.

Write the namespace separator as the bracket class `[\]`, in single quotes. It is not
decoration: on this Windows/Git Bash setup a `\\`-style pattern loses a backslash before grep
sees it. The BRE form that used to be here died with `grep: Unmatched ) or \)` and returned
**nothing** — which, for a check whose empty result means "clean", is the worst possible
failure. If this command ever prints a `grep:` error, its silence means nothing.

**Expect a non-empty result.** The rule is the target state, not the current one: the
repository carries 135 cross-module `Domain\` imports across 75 files, 44 of them in
Application. Read the count as a baseline — what matters is a *new* row attributable to your
change, not the backlog.

## Optional means optional

`ARCHITECTURE.md` marks `Contract/`, `Service/`, `Cache/`, `Console/`, `DataFixtures/`, `EventSubscriber/`, `Mapper/`, and the `<Context>/` folders as optional. Create none of them speculatively — an empty `Cache/` outlives whoever made it and teaches the next reader a rule that does not exist.

## House code style

- `declare(strict_types=1);` at the top of every file,
- **two-space indentation** — `.php-cs-fixer.dist.php` sets `setIndent('  ')`, not PSR-12's four,
- `// #region Constructor` / `// #region Methods` / `// #endregion`,
- `final readonly class` for handlers and value objects,
- PHPDoc on the class (`@category`, `@version`, `@author Valentin FORTIN <contact@valentin-fortin.pro>`) and on every method (`@since`, `@param`, `@return`),
- grouped imports — `use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};` — and explicit function imports — `use function sprintf;`,
- typed class constants: `public const string CREATE_FACILITY = 'facility_create';` (PHP 8.4).

A PostToolUse hook runs `php-cs-fixer` on every PHP file you write, so style is corrected for you — but matching it by hand keeps diffs small.

## The gate

```bash
make cs-fix      # apply style
make phpstan     # static analysis
make deptrac     # THE layer-direction proof
make lint        # lint:container + lint:yaml — catches a port with no alias
make test        # cs-lint phpstan deptrac lint phpunit-parallel
```

`make lint` is the one that catches a port aliased to nothing — invisible to both phpstan and deptrac.
