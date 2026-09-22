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

## Dynamic profiles and delegation

Delegate only a concrete bounded task that benefits from independent work. Do not launch the
whole catalog or mandatory review cascades. Explicit second opinions use the challenge skill.

`agent-profiles.toml` is a FireGuard convention, not native Codex configuration. Each role has
one stable category (`astra`, `sol`, `terra`, `luna`) and effort. Native agent TOMLs contain
neither `model` nor `model_reasoning_effort`. The parent resolves the profile immediately
before delegation using the complete current callable catalog from the session or app-server
`model/list` ([official API](https://learn.chatgpt.com/docs/app-server#models)). Collect all pages
until `nextCursor` is null before normalizing; a partial page is not a complete catalog.

Normalize the catalog to this JSON contract and pass it on standard input:

```text
{"models":[{"model":"<actual callable identifier>","hidden":false,"supported_reasoning_efforts":["<supported effort>"]}]}
python -B .codex/scripts/resolve_agent.py --agent fg-api-module-explorer
```

The JSON and command above describe input and invocation separately; use a pipeline or the
process tool's stdin field to connect them. The `model` value must be the callable model slug,
not a display label. For app-server data, copy `hidden` and normalize each
`supportedReasoningEfforts[].reasoningEffort` to a string. Do not infer omitted fields.

The resolver selects the highest numerical canonical version in the requested category that
is visible and supports the requested effort. It excludes snapshots, prereleases and other
categories. It reads local profiles and input only: no network, subprocesses or writes.
Incomplete entries, unknown relevant names and ambiguity fail without inventing a model or
silently lowering effort. Resolve catalog errors before delegating.
The parent guarantees catalog completeness; the resolver cannot detect a page omitted upstream.

Success produces `{"model":"<resolved slug>","reasoning_effort":"<profile effort>"}`.
Pass those values to the current delegation tool's corresponding parameters. With the present
runtime, explicit model/effort overrides require `fork_turns="none"` or a bounded numeric turn
count encoded as a string, such as `fork_turns="3"`; full-history inheritance does not accept
overrides. Supply the missing task context:
workspace, objective, assigned files, authoritative docs, relevant observations, allowed checks
and required result. A direct role launch without this step inherits the parent's settings;
profiles are not automatic native aliases. A future naming change may require resolver maintenance.

The parent owns integration and verification. Writers inherit session permissions and receive
explicit file ownership. Tell each writer it is not alone, to preserve others' edits and to
coordinate overlapping files. Reviewers, auditors and explorers are read-only: no generated
artifacts, cache-writing commands, database preparation or edits. Obtain runtime evidence from
the parent when checking would write. Report static inspection separately from executed checks.
Do not start nested `codex exec` or recursively delegate unless the parent assigned a subtask.

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
