# Maintaining Codex API tooling

This guide covers repository files. It changes neither personal settings,
project trust, nor another client's configuration. `AGENTS.md`,
`ARCHITECTURE.md`, `SECURITY.md`, and module contracts remain authoritative.

## Installation and relocation

Skills are versioned in `.agents/skills/` and roles in `.codex/agents/`.
No third-party design package is required for the API. Python 3.11+ and
Node.js suffice for tooling checks; application prerequisites are in
`composer.json`, `Makefile`, and `OPERATIONS.md`. Serena is optional, with
local search as a fallback.

After cloning or creating a worktree, check and update only the local MCP paths:

```powershell
python -B .codex/scripts/configure.py --check
python -B .codex/scripts/configure.py
python -B .codex/scripts/configure.py --check
```

Review the `config.toml` diff before activation. Do not add `model`,
`model_reasoning_effort`, `approval_policy`, `sandbox_mode`, or a `projects`
section. This repository does not manage personal or session policies. Review
hooks in the client that supports them; their presence does not prove they
are active. A declared MCP configuration likewise does not prove a live
connection.

## Profiles and resolution

[agent-profiles.toml](agent-profiles.toml) is a FireGuard convention. Each
`[agents."role-name"]` table contains `category` and `effort`. Every role has
exactly one profile and there are no orphaned profiles. Categories are
`astra`, `sol`, `terra`, and `luna`; the efforts used are `medium`, `high`,
and `xhigh`. The actual model must also support the requested effort.

Native TOML files contain neither `model` nor `model_reasoning_effort`. The
parent normalizes the session catalog or all pages of `model/list`, then
runs, for example:

```powershell
Get-Content -Raw -LiteralPath 'normalized-catalog.json' | python -B .codex/scripts/resolve_agent.py --agent fg-api-module-explorer
```

The example file represents a temporary catalog supplied by the parent;
do not commit a copy that would become stale. The exact format and bounded
context invocation are in the [workflow](workflow.md). The resolver locates
profiles relative to its own location, independently of the current
directory, and contacts no service.

Pass the returned `model` and `reasoning_effort` to the delegation tool.
On error, fix the catalog or profile: do not invent a `latest` alias, switch
categories, or silently lower effort. Handle a naming change in the resolver
and its tests. A direct invocation inherits from the parent without
automatic resolution.

## Checks and integrity

From the repository root:

```powershell
python -B .codex/scripts/validate.py
python -B .codex/scripts/configure.py --check
python -B -m unittest discover -s .codex/scripts -p 'test_*.py'
node --test .codex/hooks/adapter.test.mjs
```

The validator checks manifests, links, skills, roles, profiles, and forbidden
global settings. Resolver tests use simulated catalogs, never a real model.
Reviewers, auditors, and explorers remain read-only; other agents inherit
session permissions. Counts are dynamic: the current target is **14 skills
and 20 agents**, with no hard-coded assertion in the validator.

Preserve guards for secrets, historical migrations, generated trees, and
destructive Git operations. Inspect both source and destination when moving
a file. Hooks do not replace the sandbox; test protections with their
fixtures without opening a secret file.

Codex work branches follow `codex/<description-kebab>`: lowercase letters,
digits, and words separated by single hyphens. The Codex guard, Git
`pre-push` hook, and CI accept this prefix while rejecting invalid
descriptions. `codex` is not a commit type: keep the existing Conventional
Commit types.

After changing a skill, verify calls and conditional references with a
bounded realistic walkthrough. After changing the catalog, start a new
session and verify actual discovery of roles and skills; the current
session may retain the old catalog. Static checks do not prove reload.

## Migration from former invocations

Direct migration: the entries below are removed, without aliases. Historical
names serve only to locate the current owner and its conditional reference.

| Former skill | Current path |
| --- | --- |
| `fg-api-api-platform-contract` | `fg-api-endpoint` → [api-platform-contract.md](../.agents/skills/fg-api-endpoint/references/api-platform-contract.md) |
| `fg-api-dual-database` | `fg-api-migrate` → [dual-database.md](../.agents/skills/fg-api-migrate/references/dual-database.md) |
| `fg-api-hexagonal-layout` | Cross-cutting reference [hexagonal-layout.md](references/hexagonal-layout.md) |
| `fg-api-module-md` | `fg-api-module` → [module-docs.md](../.agents/skills/fg-api-module/references/module-docs.md) |
| `fg-api-module-testing` | `fg-api-tests` → [testing.md](../.agents/skills/fg-api-tests/references/testing.md) |
| `fg-api-security-checklist` | `fg-api-security-review` → [security-checklist.md](../.agents/skills/fg-api-security-review/references/security-checklist.md) |
| `fg-api-usecase-patterns` | `fg-api-usecase` → [usecase-patterns.md](../.agents/skills/fg-api-usecase/references/usecase-patterns.md) |

LSP availability is covered in [lsp-usage.md](rules/lsp-usage.md). All roles
use the `fg-api-` prefix, including `fg-api-endpoint-builder` and
`fg-api-usecase-builder`. Specialists reuse the retained skills; they do not
each need their own skill.

## Troubleshooting

| Symptom | Action |
| --- | --- |
| Missing skill or agent | Check the name/file, run the validator, test in a new session |
| Incomplete or ambiguous catalog, or model not found | Fetch every page and actual field; preserve the requested effort |
| Delegation rejects explicit parameters | Use `fork_turns="none"` or a bounded number of turns with the missing context |
| MCP declared but unavailable | Check exposed tools, continue with `rg`, and report the fallback |
| Reference missing after consolidation | Fix the consumer without recreating an alias |
| PHP container runs out of memory | Use `php -d memory_limit=1G bin/console`, as in the Makefile |
| PostgreSQL test databases not prepared | Read the [testing procedures](../.agents/skills/fg-api-tests/references/testing.md); `make test-db` replaces the templates |
| Formatting outside the intended scope | `make cs-fix` is global; prefer explicit paths or `make cs-lint` |

Documentation and tooling changes use these checks without running
application suites. If a check is unavailable, report why and the evidence
obtained.