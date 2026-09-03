# FireGuard API — Claude Code Instructions

> **Source of truth.** This file is the entry point. The normative rules live in the
> documents imported below — read them, do not paraphrase from memory.

@AGENTS.md
@ARCHITECTURE.md
@SECURITY.md

`AGENTS.md` states the architectural rules in vendor-neutral form — any assistant working in
this repository follows it. This file adds what is specific to Claude Code: the tooling, and
the operational digest below.

## TL;DR for every task

1. **Read before writing.** `ARCHITECTURE.md` (the Module Architecture Standard) for any
   structural decision, and the touched `src/<Module>/MODULE.md` before editing a module.
2. **Business logic lives in use-case handlers.** Not in processors, not in providers, not
   in controllers. *"Use cases are the single entry point for business logic."*
3. **Dependencies are one-way.** Presentation → Application → Domain · Infrastructure →
   Application (it implements the ports) · Domain depends on nothing but `SharedDomain`.
   Two caveats worth knowing before you lean on the tooling: `deptrac.yaml` permits
   `Presentation → Infrastructure` outright, and the PreToolUse hook guards only `Domain/`
   and `Application/` files. Neither sees a **cross-module** edge at all — the collectors are
   module-agnostic, so importing a sibling's `Domain\` or `Record` is green. That one is on
   you and on the boundary grep (`hexagonal-layout` skill).
4. **External dependencies go through ports.** A handler injects `…Port` interfaces from
   `Application/Port/`, never a Doctrine repository, an adapter, or an `EntityManagerInterface`.
5. **Cross-module access is through `Application\Port\` and `Application\Contract\` only** —
   never a sibling's `Domain\` or `Infrastructure\`.
6. **Handlers return Result objects**, never raw arrays. Domain events are dispatched
   **after** the durable save.
7. **`MODULE.md` is updated in the same change** that adds an endpoint, a flow, an error
   code, or a configuration requirement.
8. **Match the stack.** PHP 8.4 · Symfony 7.4 · API Platform · Doctrine ORM 3.5 ·
   PHPUnit 12.5 · FrankenPHP. Add no dependency or new pattern unless the task requires it
   and no existing pattern fits.

## Two things that bite

**The console runs out of memory.** A bare `php bin/console …` dies with
`Allowed memory size of 134217728 bytes exhausted` while building the container. Every
Makefile target sets `-d memory_limit=1G` — match them:

```bash
php -d memory_limit=1G bin/console debug:router
```

**There are two databases.** `auth` (OAuth, User, Otp, Authorization, Session, Tenant,
TrustedDevice, Audit) and `main` (the business modules). Separate entity managers, separate
migration histories, separate containers, **no joins between them**.

Every repository, processor, and provider that touches Doctrine must name its entity manager
explicitly in `config/modules/<module>.yaml`:

```yaml
Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository:
  arguments:
    $entityManager: '@doctrine.orm.main_entity_manager'
```

Omit it and autowiring picks the default: the code compiles, phpstan passes, deptrac passes,
`lint:container` passes — and it queries the wrong database. `config/packages/doctrine.yaml`
is the authority on which module lives where.

Every migration command names its database too:

```bash
php -d memory_limit=1G bin/console doctrine:migrations:diff --configuration=config/migrations/main.yaml
make migrate-main
```

**Never edit a migration that already exists.** A hook blocks it; write a new one.

## Code style

`declare(strict_types=1);` · **two-space indentation** (`.php-cs-fixer` sets `setIndent('  ')`,
not PSR-12's four) · `// #region` blocks · `final readonly class` for handlers and value
objects · PHPDoc with `@category`, `@version`, `@author Valentin FORTIN <contact@valentin-fortin.pro>`
on classes and `@since`/`@param`/`@return` on methods · grouped imports and explicit
`use function` · typed class constants.

A PostToolUse hook runs `php-cs-fixer` on every PHP file you write.

## Quality gate — must pass before declaring a task done

Run the narrowest useful check first, widening as the blast radius grows:

```bash
make cs-fix          # PHP-CS-Fixer — rewrites, does not merely check
make phpstan         # static analysis
make deptrac         # the layer-direction proof
make lint            # lint:container + lint:yaml — catches a port aliased to nothing
make phpunit-fast    # the quick suite
```

`make test` runs `cs-lint phpstan deptrac lint phpunit-parallel` in one shot.

Tests need the PostgreSQL test databases — `make test-db` once, then `make seed-fixtures`.
The suite runs on **PostgreSQL because production does**; never substitute SQLite to make a
test pass.

## Tooling — this app ships its own `.claude/`

Open **`fireguard-sso-api/`** as the workspace root to activate it. Full guide in
[.claude/README.md](.claude/README.md).

**Builders:** `fg-usecase-builder` · `fg-endpoint-builder` · `fg-port-builder` ·
`fg-domain-builder` · `fg-module-builder` · `fg-migration-builder`.

**Specialists:** `fg-test-writer` (writes) · `fg-architecture-reviewer`,
`fg-security-auditor`, `fg-contract-reviewer`, `fg-module-explorer`, `fg-workflow-reviewer`
(all read-only).

**Commands:** `/fg-usecase` `/fg-endpoint` `/fg-port` `/fg-domain` `/fg-module` `/fg-migrate`
· `/fg-arch-review` `/fg-security-review` `/fg-contract-review` `/fg-tests` `/fg-explore`
`/fg-workflow-review` · `/fg-quality`.

**Rules** (`.claude/rules/`) are **path-scoped**: 7 files that load automatically when you open a
matching file — `domain`, `application`, `infrastructure`, `presentation`, `migrations`, `tests`,
`module-config`. Each carries the few things that must never be got wrong in that layer. Three of
them repeat the dual-database warning from different angles, on purpose.

**Skills:** `dual-database`, `hexagonal-layout`, `usecase-patterns`, `api-platform-contract`,
`module-testing`, `security-checklist`, `module-md`.

**MCP:** this app's `.mcp.json` declares `context7` only — there is no PHP equivalent of the
frontend's angular/spartan documentation servers. Code intelligence comes from **`serena-api`**,
registered at user scope rather than here, and declared on every `fg-*` agent.

Cross-app tooling stays at the monorepo root (`G:\Projets\fireguard\.claude\`): `/fg-map`
and `/fg-contract-check`. This `.claude/` is also packaged as the **`fireguard-api`
plugin** (manifest `.claude/.claude-plugin/plugin.json`), installed at the monorepo root —
root sessions load the agents, the commands as `/fireguard-api:fg-…`, the skills, and the
hooks. **Code intelligence is Serena over MCP, not a language-server plugin.** The
`fireguard-api-lsp` plugin that carried Intelephense was removed from `enabledPlugins` on
2026-08-26, here and at the monorepo root — it served the main session only, never a
subagent, and `serena-api` serves both. Its `.claude/lsp/` directory is still on disk,
inert; re-enabling is one line in each `settings.json`. What went with it: diagnostics
pushed after every edit, now on demand through `get_diagnostics_for_file`. Full account and
the measurements behind it: `.claude/rules/lsp-availability.md`.
Rules, permissions, and `.mcp.json` are not plugin components: they still load
only when this app is the workspace root. The install is a cached copy — after changing
anything under `.claude/`, bump the plugin `version` and run
`claude plugin update fireguard-api@fireguard --scope project` from the monorepo root.

`.github/` carries a separate **Copilot** toolset. It is deliberately independent of this
one; a rule changed in one does not propagate to the other.
