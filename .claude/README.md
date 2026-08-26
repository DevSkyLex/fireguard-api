# FireGuard API — Claude Code tooling

This app ships its own `.claude/`. Open **`fireguard-sso-api/`** as the workspace root to
activate it: 12 agents, 13 commands, 7 skills, 8 rules, 1 MCP server, and 2 hooks. Code
intelligence comes from the user-scope `serena-api` MCP server, not from a plugin.

> **This directory is also a plugin.** The monorepo root installs it as
> `fireguard-api@fireguard` (project scope, via the root `.claude-plugin/marketplace.json`),
> so root sessions load the 12 agents, the commands namespaced as `/fireguard-api:fg-usecase`
> and friends, the skills, and the hooks. It carries neither `.mcp.json` — a plugin reads it
> from the plugin root, standalone needs it at the app root — nor `rules/`, which is not a
> plugin component. Opening this directory as the workspace root remains the only way to get
> everything. The manifest is `.claude-plugin/plugin.json`; plugin-mode hook wiring is
> `hooks/hooks.json`. Nothing is duplicated between the two modes — but the install is a
> **cached copy**: after changing tooling here, bump `version` in
> `.claude-plugin/plugin.json` and run
> `claude plugin update fireguard-api@fireguard --scope project` from the monorepo root.

Cross-cutting and monorepo-level tooling stays at `G:\Projets\fireguard\.claude\` —
`/fg-map` and `/fg-contract-check` (the API↔frontend drift check, the one agent that spans
both apps).

## Agents

Every agent is granted the `Skill` tool and opens with a **Skills to load** table naming which
skills it must load and on what trigger. That is deliberate: the agent prompt states the
_judgment_ (what to decide, in what order, what to hand off), the skill carries the
_operational_ detail (commands, harnesses, decision tables). Neither restates the other, so
neither drifts. From the monorepo root the skill names are namespaced `fireguard-api:<name>`.

**Builders — they create code.** One per kind of unit in the hexagonal standard.

| Agent                  | Creates                                                                                                        |
| ---------------------- | -------------------------------------------------------------------------------------------------------------- |
| `fg-usecase-builder`   | a command or query: Command/Query, Handler, Result, wiring, handler test                                       |
| `fg-endpoint-builder`  | an API Platform endpoint: Resource, Operation, DTOs, Processor/Provider, validators, security, functional test |
| `fg-port-builder`      | a port and its adapter, the alias, and the entity-manager wiring                                               |
| `fg-domain-builder`    | aggregates, value objects, domain events, domain exceptions                                                    |
| `fg-module-builder`    | a whole bounded context, wired across all four config files                                                    |
| `fg-migration-builder` | migrations, routed to the correct database                                                                     |

**Specialists — they enrich or judge.** Called after a builder, or on existing code.

| Agent                      | Does                                                                       | Writes?       |
| -------------------------- | -------------------------------------------------------------------------- | ------------- |
| `fg-test-writer`           | PHPUnit at the right level, denial paths included                          | yes           |
| `fg-architecture-reviewer` | layer direction, logic placement, ports, dual-DB wiring, `MODULE.md`       | **read-only** |
| `fg-security-auditor`      | auth, OAuth, sessions, OTP, RBAC, audit, tenant isolation, secrets, Stripe | **read-only** |
| `fg-contract-reviewer`     | DTOs, status codes, serialization, pagination, OpenAPI drift               | **read-only** |
| `fg-module-explorer`       | maps a module before you change it                                         | **read-only** |
| `fg-workflow-reviewer`     | CI triggers, permissions, `pull_request_target`, action pinning            | **read-only** |

Create ≠ enrich ≠ review. A builder that ships a finished test suite or a security verdict
has taken a specialist's job; each is told to hand those off by name.

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

### Where repetition *is* allowed, and the rule that keeps it honest

The agent/skill split above holds. The layer **below** it does repeat: `rules/` are
path-scoped, so they fire *without* the skill, and a rule that only pointed at one would carry
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

| Rule                | Loads when you touch                    | Carries                                                                                   |
| ------------------- | --------------------------------------- | ----------------------------------------------------------------------------------------- |
| `domain.md`         | `src/*/Domain/**`                       | depends on nothing but `SharedDomain`, invariants inside the model, no ORM attribute      |
| `application.md`    | `src/*/Application/**`                  | logic in handlers, **ports only** in the constructor, events after the save               |
| `infrastructure.md` | `src/*/Infrastructure/**`               | vendor types behind the adapter, no rules in repositories, **the `$entityManager` trap**  |
| `presentation.md`   | `src/*/Presentation/**`                 | the six-item checklist, translate-never-decide, the 403/404 distinction                   |
| `migrations.md`     | `migrations/**`                         | name the database on every command, `-d memory_limit=1G`, never edit an applied migration |
| `tests.md`          | `tests/**`                              | which level covers what, the denial paths, PostgreSQL not SQLite                          |
| `module-config.md`  | `config/modules/*` `config/packages/**` | the explicit `$entityManager`, the port `alias:`, first-match-wins access control         |
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

## Code intelligence (Serena, user scope)

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
