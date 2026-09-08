# Code intelligence: Serena, and why there is no `LSP` tool

**No `paths:` header on purpose.** This has to be in context *before* the first file is
opened and the first subagent is spawned, which is exactly when it matters. How to *use*
the server is in `lsp-usage.md`, which is path-scoped and arrives later.

## The standing answer

**Symbol questions go to Serena over MCP**, `mcp__serena-api__…`. It is the only code
intelligence in this repository, it works in the main session and inside a subagent alike,
and every `fg-*` agent declares it.

**There is no native `LSP` tool.** The `fireguard-api-lsp` plugin was removed from
`enabledPlugins` on 2026-08-26, here and at the monorepo root. Its `.claude/lsp/` directory
is still on disk, inert; re-enabling is one line in each `settings.json`.

**What was given up, honestly:**

- **The call hierarchy.** `incomingCalls` / `outgoingCalls` had no Serena equivalent and now
  have no answer at all. Nil loss here — Intelephense withheld them anyway.
- **Pushed diagnostics.** The plugin injected diagnostics after every `Write`/`Edit`. Serena
  has `get_diagnostics_for_file`, on demand, per file.

Everything else — definitions, references, symbol search, file structure — Serena answers,
and it answers from a subagent, which the native tool never did.

## Why it was removed: the measurements

Three sessions, three verdicts, all 2026-08-26. The main session's `LSP` worked throughout;
subagents never got it.

| Claude Code | Subagent symptom | Main-session control |
| --- | --- | --- |
| 2.1.237 | `LSP` absent whatever the `tools:` line declared; 74 subagent runs, zero calls | `findReferences` correct |
| 2.1.246 (headless) | `ToolSearch select:LSP` → "No matching deferred tools found" | 22 references across 9 files |
| after a restart | `ToolSearch` **returns the full schema**; the call is refused: `Error: No such tool available: LSP. LSP is disabled for this session, in subagents as well as here.` | 22 references across 9 files |

The last row is the nastiest form: the schema loads, so a subagent believes it holds the tool.
Two of three subagents asked "do you have `LSP`?" answered **yes** on that basis alone, and one
pasted the schema as proof. Both were wrong.

**A schema is not a capability. Only a real payload counts.** If you ever re-test this, demand
the raw tool output *and* a `findReferences` result with numbers — that protocol has caught a
fabricating subagent twice.

Serena, run through the same protocol, passed from inside `fg-module-explorer` and
`fg-architecture-reviewer` with no `Grep` fallback and no tool refusal: 8 files for
`AuditExportTooLargeException`, identical across three calls, matching the main session exactly.

## Re-measured head to head, in subagents (2026-08-26, after the removal)

| | Serena, in a subagent | Grep, in a subagent | Main-session control |
| --- | --- | --- | --- |
| `AuditExportTooLargeException` | **8 files**, identical across 3 calls | 10 raw, 8 after manual triage | 10 raw `-w`, 8 true references |
| tool calls | 3 | 10 | — |
| wall clock | 33 s | 104 s | — |
| subagent tokens | 47k | 68k | — |

**Serena is exact at the file.** Its 8 paths are the control set minus the declaration itself
and one `{@see}` docblock. Noise rate for a raw `grep -w` on this symbol: **20 %**, and the
triage is what costs the 3× wall clock.

**This repository's `tests/` are indexed normally** — four of those eight files are test files.
The frontend does not have that property; if you are working there, read its own copy of this
file.

### Also sweep `*.md` — Serena is blind to prose, and prose rots

The only genuine defect the whole exercise surfaced was in documentation:
`src/Intervention/MODULE.md` cited `Audit\Domain\Exception\AuditExportTooLargeException`, a
namespace that no longer exists (the class lives at `Audit\Application\Contract\`). No symbol
index can find that. **Fixed 2026-09-03** — and note that the citation carried a line number
which had already drifted from 1664 to 1725 before anyone corrected the namespace itself, which
is the second lesson: a line number in prose rots faster than the prose around it. **Add `Grep -w "<Symbol>" --include="*.md"` to any rename** — it is cheap
and it is the one thing Serena structurally cannot answer.

### `Glob` and `Grep` do not agree on scope

`Grep` honours `.gitignore` and `.git/info/exclude`; `Glob` does not. On the same symbol,
`Glob` surfaced four extra hits `Grep` never showed — two inside a stale `.claude/worktrees/`
copy and two build artifacts under `var/coverage` and `var/infection`, all on the dead
namespace. Serena's `ignored_paths` covers the worktrees; it does not cover `var/`.
**Never take a `Glob` hit count as a reference count.**

## The cold index answers wrong, not empty

A cold Intelephense does not answer empty, it answers **short**. Repeated identical calls right
after server start returned 0, 0, 0, 0, 3, 4, 7, then 8 files. Same rule the native tool had:
**never record "no consumers" from a first call** — repeat it once and take the larger answer.
Every `fg-*` agent carries this instruction.

The head-to-head above **did not reproduce it**: three identical calls, warm, returned
byte-identical results. That neither refutes the rule nor tests it — the server had already
been queried in the session. The rule stands; it simply costs one extra call to honour.

## How the server is set up

[Serena](https://github.com/oraios/serena) 1.7.0, installed globally with
`uv tool install -p 3.13 serena-agent`, registered at **user** scope, pinned to this
repository:

```
serena start-mcp-server --context ide --project G:/Projets/fireguard/fireguard-sso-api
```

A second server, `serena-web`, is pinned to the frontend. Two servers rather than one on
purpose: a single server holds **one** active project, so two subagents working on the two
apps at once would fight over `activate_project`. Pinning removes the call entirely — the
repository is chosen by the tool name.

**Context `ide`, not `claude-code`.** The `claude-code` context ships a prompt that forbids
`Read` and `Edit` on code files outright, which contradicts how the `fg-*` agents work. `ide`
keeps `single_project: true` — what pins the project — without that prompt, and drops the
Serena tools that merely duplicate Claude's own.

Only the read-only code-intelligence tools are declared on the agents: `find_symbol`,
`get_symbols_overview`, `find_declaration`, `find_referencing_symbols` and
`get_diagnostics_for_file`. **`find_implementations` is deliberately absent** — Intelephense's
free edition does not answer it and returns `[]`, which reads exactly like "nothing implements
this". Serena's editing and memory tools are left out too: edits go through this repo's own
discipline.

| Check | Result |
| --- | --- |
| index size | 5 145 files |
| `find_symbol` on a class that does not exist | `[]` — it does not invent |

### Two traps hit during setup

- **Serena ignores `.git/info/exclude`.** `.claude/worktrees/` is excluded there, and Serena
  indexed both stale worktrees anyway — half the index was duplicate classes. Fixed with
  `ignored_paths: [".claude/worktrees"]` in `.serena/project.yml`; 10 000 files fell to 5 145.
- **Serena pins Intelephense 1.14.4**, four minors behind the 1.18.5 the removed
  `fireguard-api-lsp` plugin ran. Upgraded in place with `npm install intelephense@latest` in
  `~/.serena/language_servers/static/Intelephense/php-lsp`. **A Serena upgrade may re-pin the
  old version** — re-check it after one.

`.serena/` (config, index cache, memories) is gitignored.
