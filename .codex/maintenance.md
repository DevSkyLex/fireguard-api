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

Keep one desktop project with API, web and landing attached. API or web can be primary;
Codex automatically discovers configuration from the primary folder. Its peer role declarations
make the same 47 agents available from either primary checkout. Read the owning repository's
AGENTS.md and workflow explicitly when working in a secondary folder.

The shared [configuration example](config.example.toml) contains the peer role declarations
and optional MCP setup. `.codex/config.toml` is local and ignored: copy the example on a new
checkout and replace `<API_CHECKOUT>` / `<WEB_CHECKOUT>` only in the local MCP entries. Preserve
existing local server settings when updating agent declarations. MCP `cwd` binds Angular,
Spartan, Playwright and each Serena server to its intended checkout. Both Serena servers stay
disabled unless needed. Configuration presence does not prove a live connection or trusted hooks.
Do not change personal settings, project trust or approval policy.

Keep machine-specific paths and overrides in ignored local or user-level configuration.
Review the `config.example.toml` diff before sharing. Do not add `model`,
`model_reasoning_effort`, `approval_policy`, `sandbox_mode`, or a `projects`
section. This repository does not manage personal or session policies. Review
hooks in the client that supports them; their presence does not prove they
are active. A declared MCP configuration likewise does not prove a live
connection.

## Native agent settings

Each role defines `model`, `model_reasoning_effort`, `service_tier` and `features.fast_mode`
in its own native TOML. Select the actual custom agent type exposed by the runtime, not merely
a task name: naming a generic spawn does not load the role. The native file takes precedence
over inherited or explicit spawn model/effort. Luna roles use Fast; Sol roles explicitly
request Standard. Do not alter the main session's model, speed, trust or approval policy.

Before dispatch, check that the exact configured model and effort are available in the current
callable catalog. If using app-server `model/list`, request `includeHidden: true` for the full
catalog and collect every page until `nextCursor` is null; read `model` and
`supportedReasoningEfforts[].reasoningEffort`, not display labels or a cache.
An unavailable model/effort or runtime without native-role selection is an explicit limitation,
not permission to silently inherit, substitute a model or lower effort. Continue independent
work in the parent. User-requested changes to a role require an explicit supported configuration.

The primary project's local configuration registers the peer's agents using relative
`agents.<name>.config_file` paths. Native definitions remain in their owning checkout:
22 API and 25 web roles form one 47-role catalog. Keep the intended `fireguard-api` and
`fireguard-web` checkouts beside each other, including when using a worktree. Read the owning
repository's instructions explicitly for a secondary folder. Missing peers fail explicitly;
an isolated clone can use its local standalone agents without the peer declarations.

The parent assigns workspace, objective, exact files, authoritative contracts, relevant
observations, allowed checks and required result. Writers inherit session permissions and
coordinate overlap. Reviewers/auditors/explorers are read-only, including generated files,
caches and database preparation; ask the parent for evidence when a check would write.
Do not automatically launch additional agents, Serena processes or nested Codex challenges.

See [native agent configuration](https://learn.chatgpt.com/docs/agent-configuration/subagents)
and [role declarations](https://learn.chatgpt.com/docs/config-file/config-reference).

## Checks and integrity

From the repository root:

```powershell
python -B .codex/scripts/validate.py
python -B .github/scripts/check_agent_parity.py
python -B -m unittest discover -s .codex/scripts -p 'test_*.py'
python -B -m unittest discover -s .github/scripts -p 'test_agent_parity.py'
node --test .codex/hooks/adapter.test.mjs
```

The validator checks manifests, links, skills, native roles, peer registrations, and forbidden
global settings. Native-policy tests use fixtures; they do not invoke a real model.
Reviewers, auditors, and explorers remain read-only; other agents inherit
session permissions. Counts are dynamic: the current target is **18 skills
and 22 agents**, with no hard-coded assertion in the validator.

Both validators check the shared catalog by default. On an API-only checkout, add
`--local-only` to both commands. This verifies API definitions, local vendor parity and
the structure of frontend declarations without reading the web checkout. Reports mark
`scope: local` and `peer_definitions: not_checked`; that result does not validate the web
definitions. CI runs this mode in the `agent-tooling` job with Python 3.13 and Node.js 22,
without a database, peer checkout or model call. Cross-repository tests use temporary fixtures
and include invalid declarations that must make the CLI exit unsuccessfully.

Active instructions in first-party skills, native prompts and entry documents are checked
against skill/role catalogs and explicit repository resources, including inline code.
Failures identify the file, line and reference. Markdown links and client boundaries retain
their separate checks. Placeholder paths containing `<...>` or `*` and ignored local
configuration are excluded from active-reference checks. Fenced commands remain active;
exclude examples explicitly. To retain historical
names in prose, surround only that passage with `<!-- tooling-references: historical -->`
and `<!-- /tooling-references -->`; `example` is also accepted. These exclusions do not
disable Markdown link checks. Unclosed or nested exclusion markers fail validation.

Preserve guards for secrets, historical migrations, generated trees, and
destructive Git operations. Inspect both source and destination when moving
a file. Hooks do not replace the sandbox; test protections with their
fixtures without opening a secret file.

Codex follows the [Git naming conventions](../AGENTS.md#git-naming):
`<type>/<description-kebab>` branches and Conventional Commit headers.
The Codex guard, Git `pre-push` hook, and CI reject the `codex/` branch prefix.
Keep their accepted types and description rules aligned; test the guards without
creating branches, committing, or contacting a remote.

After changing a skill, verify calls and conditional references with a
bounded realistic walkthrough. After changing the catalog, start a new
session and verify actual discovery of roles and skills; the current
session may retain the old catalog. Static checks do not prove reload.

## Review and current-session diagnosis

Invoke `$fg-api-review` for a read-only review of uncommitted changes. It inventories the
index, working tree and relevant untracked files separately before selecting specialized
architecture, security, contract or workflow resources. Findings include priority, exact
location, triggering scenario and consequence, followed by actual validation evidence and
checks not run. A staged regression restored by an unstaged edit still belongs in the review.

Invoke `$fg-api-tooling-diagnose` in the current chat to compare declared settings with
available evidence. It reports file validity, role/skill discovery, model/effort availability
and observed execution independently. It does not start agents or servers. If `model/list`
is exposed, request `includeHidden: true` and collect every page through a terminal
`nextCursor: null`; incomplete or unsourced lists never prove availability. Picker-only lists
cannot establish absence. Only request/execution metadata can prove applied parameters;
configuration or an agent's own assertion cannot.

The optional [session evidence helper](scripts/session_evidence.py) accepts sanitized JSON
through standard input. Its input contract is documented in that file; it checks pagination
and compares supplied snapshots without querying a client or writing files. Missing evidence
is reported as `non observable`, separately from a demonstrated mismatch. After reloading
in a new session, check discovery of both new skills again and qualify available runtime
observations separately.

## Migration from former invocations

Direct migration: the entries below are removed, without aliases. Historical
names serve only to locate the current owner and its conditional reference.

<!-- tooling-references: historical -->

| Former skill                   | Current path                                                                                                                  |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------- |
| `fg-api-api-platform-contract` | `fg-api-endpoint` → [api-platform-contract.md](../.agents/skills/fg-api-endpoint/references/api-platform-contract.md)         |
| `fg-api-dual-database`         | `fg-api-migrate` → [dual-database.md](../.agents/skills/fg-api-migrate/references/dual-database.md)                           |
| `fg-api-hexagonal-layout`      | Cross-cutting reference [hexagonal-layout.md](references/hexagonal-layout.md)                                                 |
| `fg-api-module-md`             | `fg-api-module` → [module-docs.md](../.agents/skills/fg-api-module/references/module-docs.md)                                 |
| `fg-api-module-testing`        | `fg-api-tests` → [testing.md](../.agents/skills/fg-api-tests/references/testing.md)                                           |
| `fg-api-security-checklist`    | `fg-api-security-review` → [security-checklist.md](../.agents/skills/fg-api-security-review/references/security-checklist.md) |
| `fg-api-usecase-patterns`      | `fg-api-usecase` → [usecase-patterns.md](../.agents/skills/fg-api-usecase/references/usecase-patterns.md)                     |

<!-- /tooling-references -->

LSP availability is covered in [lsp-usage.md](rules/lsp-usage.md). All roles
use the `fg-api-` prefix, including `fg-api-endpoint-builder` and
`fg-api-usecase-builder`. Specialists reuse the retained skills; they do not
each need their own skill.

## Troubleshooting

| Symptom                                             | Action                                                                                                                     |
| --------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| Missing skill or agent                              | Check the name/file, run the validator, test in a new session                                                              |
| Incomplete or ambiguous catalog, or model not found | Inspect all catalog pages; preserve the configured model and effort                                                           |
| Delegation rejects explicit parameters              | Select the actual native custom role; a generic task name does not load it                                              |
| MCP declared but unavailable                        | Check exposed tools, continue with `rg`, and report the fallback                                                           |
| Reference missing after consolidation               | Fix the consumer without recreating an alias                                                                               |
| PHP container runs out of memory                    | Use `php -d memory_limit=1G bin/console`, as in the Makefile                                                               |
| PostgreSQL test databases not prepared              | Read the [testing procedures](../.agents/skills/fg-api-tests/references/testing.md); `make test-db` replaces the templates |
| Formatting outside the intended scope               | `make cs-fix` is global; prefer explicit paths or `make cs-lint`                                                           |

Documentation and tooling changes use these checks without running
application suites. If a check is unavailable, report why and the evidence
obtained.

## Shared-catalog validation and runtime acceptance

Run from each checkout:

```powershell
python -B .github/scripts/check_agent_parity.py
python -B -m unittest discover -s .github/scripts -p 'test_agent_parity.py'
```

The parity check reads each vendor's independent source definitions and peer declarations;
it reports 22 API roles, 25 web roles and seven Luna Fast roles without invoking them.
Run in fresh sessions with API and web as primary. Check the native 47-role catalog, exact
models and efforts, Luna Fast under a Standard parent, and Sol Standard under a Fast
parent. Verify actual runtime/request metadata; an agent's assertion is not proof of its tier.
Distinguish parsed configuration, discovered roles, current model availability and observed
execution. Report unsupported clients or unavailable metadata without a silent fallback.

Review only assigned changes; observers ask the parent for cache-writing/runtime evidence.
No catalog check should start every agent or initialize Serena automatically.
