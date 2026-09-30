# FireGuard API — Claude Code tooling

This checkout ships **22 native agents**, with the same names and responsibilities as
its Codex catalog. Claude definitions are standalone Markdown files: they do not import,
link to or depend on Codex agent definitions. Existing slash-command names are preserved.

## Shared API + web catalog

Start from this checkout and attach the intended peer:

```powershell
claude --add-dir ../fireguard-web
```

Current Claude versions discover `.claude/agents/` in added directories, giving the same
**47 roles** from API or web. Agent prompts resolve their owning checkout explicitly;
read its AGENTS.md, CLAUDE.md, rules and module/feature contract. Additional-directory agent
discovery does not imply every skill, hook or setting of the peer is loaded: use the owning
skill file directly when registration is unavailable.

Both project settings disable Fast (`fastMode: false` and
`CLAUDE_CODE_DISABLE_FAST_MODE=1`), including the main session. Models and efforts are
explicit native frontmatter; verify client/provider support and the actually selected model.
Do not silently lower effort or substitute a model when unavailable.

Local source discovery is the shared-catalog mode. Do not load the same agents again through
cached plugins. The existing plugin manifest remains available for plugin-only use, with a
patch version bump after catalog changes; root marketplace/cache activation is separate.
The absent parent marketplace is not recreated by this migration. Do not modify personal settings.

## Agents

Each agent has assigned ownership, conditional local skills, explicit model/effort and a
bounded report. Reviewers, auditors and explorers exclude editing/delegation tools and request
parent evidence for checks that write files, caches or state. Writers inherit permissions and
preserve concurrent work. No agent automatically spawns a challenge or extra specialist.

| Agent | Responsibility | Model | Effort | Mode |
| --- | --- | --- | --- | --- |
| `fg-api-architecture-reviewer` | Use to review fireguard-api PHP changes against the hexagonal Module Architecture Standard — layer direction, business logic in handlers not processors, ports vs concrete infrastructure, cross-module boundaries, naming, the dual-database wiring, and MODULE.md currency. Invoke after writing or modifying module code. Read-only — reports findings, does not edit. | opus | xhigh | Read-only |
| `fg-api-async-builder` | Implement assigned Messenger/Scheduler flows with transactional outbox, bounded retry, idempotence, leases and recovery. | opus | xhigh | Assigned writes |
| `fg-api-auth-reviewer` | Review OAuth/OIDC, token verification, sessions, MFA, signatures, rotation, revocation and replay boundaries. | opus | high | Read-only |
| `fg-api-authorization-reviewer` | Review RBAC and tenant, organization and object authorization across routes, handlers, repositories and async paths. | opus | high | Read-only |
| `fg-api-comment-maintainer` | Define or maintain assigned comment conventions, correct source docblocks and run scoped documentation formatting and lint without changing behavior. | sonnet | medium | Assigned writes |
| `fg-api-contract-reviewer` | Use to review the API Platform contract in fireguard-api — resource metadata, operation constants, Input/Output DTOs, serialization groups, status codes, filters, pagination, OpenAPI output, and exception-to-HTTP mapping — for regressions and drift. Invoke after changing an endpoint or a DTO. Read-only — reports findings, does not edit. | opus | high | Read-only |
| `fg-api-domain-builder` | Use to add Domain-layer code in fireguard-api — an aggregate or model under Domain/Model/, a value object, a domain event, or a domain exception — with the invariants enforced inside the model rather than in a handler. Invoke for "add an aggregate / value object / domain event / domain exception to <Module>". Writes code. | sonnet | high | Assigned writes |
| `fg-api-endpoint-builder` | Build an API Platform endpoint with Resource, Operation, DTOs, Processor or Provider, validation, security, error mapping and functional tests. Business handlers belong to fg-api-usecase-builder when separately delegated. | sonnet | high | Assigned writes |
| `fg-api-integration-builder` | Implement external service adapters and webhooks behind Application ports with bounded timeouts, errors and tests using doubles. | sonnet | high | Assigned writes |
| `fg-api-migration-builder` | Generate or validate new Doctrine schema/data migrations on the explicit auth or main history. Repository-only changes do not automatically require a migration. | opus | high | Assigned writes |
| `fg-api-module-builder` | Scaffold a backend bounded context with its first vertical slice, four-layer ownership, ports, wiring, persistence when needed, security, MODULE.md and baseline tests. | opus | high | Assigned writes |
| `fg-api-module-documenter` | Update assigned MODULE.md contracts from verified source and configuration evidence, preserving their normative scope. | sonnet | medium | Assigned writes |
| `fg-api-module-explorer` | Use to map an existing module in fireguard-api before changing it — its use cases, ports and adapters, Doctrine records and which database they live on, API resources and routes, config wiring, tests, and the closest implementation anchors to mirror. Invoke before implementing in unfamiliar territory. Read-only — produces a map, not edits. | sonnet | high | Read-only |
| `fg-api-observability-reviewer` | Review assigned logs, correlation, health checks and failure visibility for API and worker flows. | sonnet | high | Read-only |
| `fg-api-persistence-builder` | Implement assigned Doctrine Records, repositories, mappers and locking through Application ports; schema migrations remain with the migration specialist. | sonnet | high | Assigned writes |
| `fg-api-port-builder` | Use to add a port and its adapter in fireguard-api — an Application/Port/Outbound (or Inbound) interface, the Infrastructure adapter or Doctrine repository that fulfils it, the config/modules alias, the explicit entity-manager wiring, and the adapter unit test. Invoke when a use case needs an external dependency, or when a module must publish a capability. Writes code. | sonnet | high | Assigned writes |
| `fg-api-query-performance-reviewer` | Review Doctrine query cost, N+1, pagination, volumes, index coverage and supplied plans without applying changes. | sonnet | high | Read-only |
| `fg-api-security-auditor` | Use to security-review changes touching authentication, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC permissions, the audit ledger, multi-tenant/organization scoping, secrets handling, or the Stripe billing webhook. FireGuard is an identity + fire-safety platform, so these paths are its crown jewels. Read-only — reports risks and fixes, does not edit. | opus | xhigh | Read-only |
| `fg-api-service-wiring-reviewer` | Review aliases, dependency injection, tags, handler registration and explicit entity and transaction managers. | sonnet | medium | Read-only |
| `fg-api-test-writer` | Use to author or repair PHPUnit tests in fireguard-api — unit tests for handlers, adapters, and domain models; integration tests for Doctrine repositories; functional tests for API endpoint contracts including denial paths; E2E for full flows. Invoke when a change needs coverage. Writes tests; never changes production code to make one pass. | sonnet | high | Assigned writes |
| `fg-api-usecase-builder` | Build a command or query use case with typed message, Handler, Result, ports, wiring and unit tests. HTTP work belongs to fg-api-endpoint-builder when separately delegated. | sonnet | high | Assigned writes |
| `fg-api-workflow-reviewer` | Use to review GitHub Actions changes in fireguard-api — triggers, permissions, secret exposure, pull_request_target risks, caching, matrix and job dependencies, and deployment gating. Invoke when .github/workflows or the composite actions change. Read-only — reports findings, does not edit. | sonnet | high | Read-only |

Forms, overlays and collections have separate owners. Routing/SSR, access and offline sync
use their dedicated roles; ordinary component/service builders retain their narrower scope.
Security, performance, workflows and observability report findings rather than applying fixes.

## Commands

| Builders       | Specialists           | Gate          |
| -------------- | --------------------- | ------------- |
| `/fg-usecase`  | `/fg-arch-review`     | `/fg-quality` |
| `/fg-endpoint` | `/fg-security-review` |               |
| `/fg-port`     | `/fg-contract-review` |               |
| `/fg-domain`   | `/fg-tests`           |               |
| `/fg-module`   | `/fg-explore`         |               |
| `/fg-migrate`  | `/fg-workflow-review` |               |

`/fg-quality` is pure Bash — no agent. It runs `cs-fix` → `phpstan` → `deptrac` → `lint`
→ tests, stopping at the first failure.

## Skills

Reference material agents load on demand. Each carries the **operational** content —
commands, templates, decision tables, exemplar paths — and cites `ARCHITECTURE.md` for the
_rule_. That split keeps `ARCHITECTURE.md` the single source of truth instead of creating a
second one that drifts.

### Where repetition _is_ allowed, and the rule that keeps it honest

The agent/skill split above holds. The layer **below** it does repeat: `rules/` are
path-scoped, so they fire _without_ the skill, and a rule that only pointed at one would carry
nothing at the moment it is needed. Five of the eight therefore abridge a skill —
`tests.md`→`module-testing`, `application.md`→`usecase-patterns`,
`presentation.md`→`api-platform-contract`, `domain.md`→`hexagonal-layout`,
`infrastructure.md`+`module-config.md`→`dual-database`.

Each opens with **"Abridgement of the `<skill>` skill — change one, change both."** That line
is the whole contract: it is not decoration, it is how the next editor learns a second copy
exists. A restatement without the marker is a bug, and this repo has already paid for one — the
claim that `debug:firewall` resolves `access_control` (it does not) lived in four files and was
corrected in exactly one of them.

The three deliberate dual-database repetitions noted below are a separate and defensible case:
they say the same thing from different angles because the failure they prevent is silent.

| Skill                   | Answers                                                                                         |
| ----------------------- | ----------------------------------------------------------------------------------------------- |
| `dual-database`         | which module lives on which database, the `$entityManager` wiring, migration commands, test DBs |
| `hexagonal-layout`      | where each file goes, what it may import, naming, and the house code style                      |
| `usecase-patterns`      | the Handler template, port-only injection, events after the save, the handler test              |
| `api-platform-contract` | the six-item endpoint checklist, reference catalogs, status codes, security placement           |
| `module-testing`        | which level covers what, the denial paths, the PostgreSQL test databases                        |
| `security-checklist`    | the crown-jewel paths, fail-closed rules, the regression test each finding needs                |
| `module-md`             | the seven required sections and the update triggers                                             |

## Rules (`rules/`)

Path-scoped instructions. Unlike a skill, a rule loads **automatically** whenever Claude reads a
file matching its `paths:` glob — so it carries the few things that must never be got wrong on
that kind of file, not the how-to.

| Rule                | Loads when you touch                    | Carries                                                                                                      |
| ------------------- | --------------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| `domain.md`         | `src/*/Domain/**`                       | depends on nothing but `SharedDomain`, invariants inside the model, no ORM attribute                         |
| `application.md`    | `src/*/Application/**`                  | logic in handlers, **ports only** in the constructor, events after the save                                  |
| `infrastructure.md` | `src/*/Infrastructure/**`               | vendor types behind the adapter, no rules in repositories, **the `$entityManager` trap**                     |
| `presentation.md`   | `src/*/Presentation/**`                 | the six-item checklist, translate-never-decide, the 403/404 distinction                                      |
| `migrations.md`     | `migrations/**`                         | name the database on every command, `-d memory_limit=1G`, never edit an applied migration                    |
| `tests.md`          | `tests/**`                              | which level covers what, the denial paths, PostgreSQL not SQLite                                             |
| `module-config.md`  | `config/modules/*` `config/packages/**` | the explicit `$entityManager`, the port `alias:`, first-match-wins access control                            |
| `lsp-usage.md`      | `src/**/*.php` `tests/**/*.php`         | Serena for symbols / grep for text, the cold index that answers short, the operations Intelephense withholds |

Three of them repeat the **dual-database** warning from a different angle, on purpose: it is the
one defect that passes every static check and still corrupts data.

## MCP server (`../.mcp.json`)

| Server     | Command                        | Tools |
| ---------- | ------------------------------ | ----- |
| `context7` | `npx -y @upstash/context7-mcp` | 2     |

**One, deliberately.** There is no PHP equivalent of the angular/spartan documentation
servers the frontend uses: `symfony/mcp-bundle` exists to _expose_ an app as an MCP server,
not to document Symfony, and the PHPStan MCP servers are unofficial and redundant with
`make phpstan`. Context7 covers Symfony 7.4, Doctrine, API Platform, and PHPUnit
generically, which is honestly the whole of what is available and reliable.

## Code intelligence (Serena, local scope, optional)

`serena-api` is configured privately for this checkout in `~/.claude.json`,
using the current `fireguard-api` path. It is disabled by default through the
project's `disabledMcpServers` list. Enable it from `/mcp` for symbol navigation,
reference analysis or refactoring, then disable it again after the task; the
toggle is saved per project. Routine text and documentation work uses `rg`.

[Intelephense](https://intelephense.com) on `.php`, reached through the **`serena-api`** MCP
server rather than a language-server plugin, giving `find_declaration` /
`find_referencing_symbols` / `find_symbol` / `get_symbols_overview` /
`get_diagnostics_for_file`. Navigation is the bigger half of the value here: from an
`Application/Port/Outbound` interface to the adapter that fulfils it, the link runs through a
`config/modules/<module>.yaml` alias, so grep lands in YAML while Serena lands in the adapter.

**`find_implementations` answers `[]`, not an error** — an Intelephense premium feature. It is
deliberately not declared on this app's agents. `find_referencing_symbols` on the port is the
substitute; the adapter surfaces as its `implements` clause. There is no call hierarchy either.

**The `fireguard-api-lsp` plugin was removed from `enabledPlugins` on 2026-08-26**, in the
monorepo root and here. It served the main session only — subagents never received the `LSP`
tool — while Serena serves both. Its `lsp/` directory is still on disk, inert; re-enabling is
one line in each `settings.json`. What went with it: diagnostics pushed automatically after
every edit. `mcp__serena-api__get_diagnostics_for_file` is on demand, per file.

Full account, including the measurements that justified the removal:
`.claude/rules/lsp-availability.md`. How to use it day to day: `.claude/rules/lsp-usage.md`.

## Hooks

**`hooks/guard.mjs`** — PreToolUse on `Write|Edit`. Blocks:

- `.env*` (except `.env.example` / `.env.dist`) and `config/jwt/`,
- generated trees: `vendor/` `var/` `node_modules/` `public/bundles/`,
- **hexagonal layer violations** — a `Domain/` file importing `Application\`, `Infrastructure\`,
  or `Presentation\`; an `Application/` file importing `Infrastructure\` or `Presentation\`.
  `make deptrac` enforces the same rule at the gate; this only moves the feedback earlier,
- **editing a migration that already exists** — its checksum and its place in the history are
  fixed. Write a new migration instead.

**`hooks/format.mjs`** — PostToolUse on `Write|Edit`. Runs `php-cs-fixer` on `.php` under
`src/` or `tests/`. The project uses **two-space indentation** (`setIndent('  ')`), not
PSR-12's four, so hand-written PHP nearly always needs this pass.

`ARCHITECTURE.md` and `MODULE.md` stay writable on purpose — the standard requires them
updated in the same change.

## The two traps

**1. The console runs out of memory.** A bare `php bin/console …` dies with
`Allowed memory size of 134217728 bytes exhausted` building the container. Every Makefile
target sets `-d memory_limit=1G` (`PHP_MEMORY_LIMIT ?= 1G`); match them when calling the
console directly.

**2. There are two databases.** `auth` and `main`, separate entity managers, separate
migration histories, separate Docker containers, no joins between them. Every repository,
processor, and provider must name its entity manager explicitly in
`config/modules/<module>.yaml`. Omitting it compiles, passes phpstan, passes deptrac,
passes `lint:container` — and queries the wrong database. See the `dual-database` skill.

## Relationship to `.github/`

`.github/` carries a parallel **Copilot** toolset — its own skills, agents, prompts, hooks,
and instructions. It is deliberately left untouched: the two are independent, and this
`.claude/` was written fresh against `ARCHITECTURE.md` rather than converted. If a rule
changes in one, it does not propagate to the other.

## Comment maintenance

`fg-api-comment-maintainer` uses Sonnet with medium effort for assigned PHP comments,
docblocks and scoped checks. Both clients read the [shared convention](../docs/guides/code-comments.md).
This role does not change behavior or launch another agent.
