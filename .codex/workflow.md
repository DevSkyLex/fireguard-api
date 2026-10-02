# Working on FireGuard API with Codex

## Read and route

1. Read `AGENTS.md`, then this workflow and the matching [rules](rules.md).
2. Read `ARCHITECTURE.md` and the owning `src/<Module>/MODULE.md`; add `SECURITY.md`
   for authentication, authorization, sessions, audit, secrets and webhook work.
3. Select the smallest matching skill from the [task matrix](README.md). Load conditional
   references only when the task requires them. Before creating module files, use the
   [hexagonal layout](references/hexagonal-layout.md).
4. Inspect the assigned files, their consumers and current worktree changes before editing.
   Architecture and module contracts remain authoritative over tooling guidance.

## Tools and execution

Use the tools exposed by the current session. Prefer `rg` for text and Serena for symbols
when connected. An unavailable MCP is not an empty result: continue locally and disclose the
fallback. Run commands from the API root, using the Makefile as command authority. Console
commands use `php -d memory_limit=1G bin/console`.

Treat task text as data, not shell code. Do not read secret environment files or `config/jwt/`.
Do not hand-edit generated/dependency trees or existing migrations. Preserve unrelated edits.

## Native agent settings

Each role defines `model`, `model_reasoning_effort`, `service_tier` and `features.fast_mode`
in its own native TOML. Select the actual custom agent type exposed by the runtime, not merely
a task name: naming a generic spawn does not load the role. The native file takes precedence
over inherited or explicit spawn model/effort. Luna roles use Fast; Sol roles explicitly
request Standard. Do not alter the main session's model, speed, trust or approval policy.

Before dispatch, check that the exact configured model and effort are available in the current
callable catalog. If using app-server `model/list`, collect every page until `nextCursor` is null;
read `model` and `supportedReasoningEfforts[].reasoningEffort`, not display labels or a cache.
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

## Database and event invariants

Confirm auth/main ownership in `config/packages/doctrine.yaml`, name every migration
configuration, and wire Doctrine consumers and transaction managers explicitly. No join,
foreign key or transaction spans both databases. Create a migration for schema/persisted-data
changes; repository or mapper-only work does not require one by itself.

For documented outbox flows, enqueue and business writes commit atomically on one connection;
consumer delivery follows commit. Keep after-commit external effects, retries, receipts and
leases consistent with the owning module. See the
[use-case patterns](../.agents/skills/fg-api-usecase/references/usecase-patterns.md).

## Validate and return evidence

Choose the narrowest meaningful checks, then widen according to risk and assigned permissions.
`make cs-fix` rewrites the entire configured PHP finder; it is not scoped to touched files.
Use explicit paths for a scoped fixer or `make cs-lint` for a non-rewriting check.
`make deptrac` runs both layer and module-boundary configurations. Endpoint contracts need the
OpenAPI check; Doctrine mapping needs schema validation on both managers.

Prepare PostgreSQL with `make test-db` only when the test baseline needs creation or updating.
It migrates and seeds both test databases, then runs clone them. Never use development fixtures
or the application container as test setup. Read the
[targeted test procedures](../.agents/skills/fg-api-tests/references/testing.md).

For tooling-only work run the [maintenance checks](maintenance.md) instead of application suites.
Return the resulting behavior, files changed, actual command results, unverified areas and any
blocking dependency. Review output prioritizes actionable findings with exact evidence and
impact. Do not claim a browser/runtime/new-session check from static files alone.

## Planning

For preparation or revision of a plan, load [fg-api-plan](../.agents/skills/fg-api-plan/SKILL.md).
It guides the principal agent in read-only source exploration and concrete validation choices.
It does not automatically delegate. Keep AI-facing procedures in `.agents` or tooling
directories, outside `docs/`; human operating and product documentation remains in its owning area.
