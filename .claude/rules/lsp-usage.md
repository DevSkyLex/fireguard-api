---
paths:
  - 'src/**/*.php'
  - 'tests/**/*.php'
---

# Use the language server

Code intelligence on this app comes from **Serena over MCP**, tool prefix
`mcp__serena-api__`, backed by Intelephense. The native `LSP` tool is gone — its plugin was
removed on 2026-08-26 because it never reached subagents and Serena covers the same ground
from both. See `.claude/rules/lsp-availability.md`.

**Ask Serena a question about a symbol. Grep a question about text.** A symbol question
answered by grep alone is a review finding, not a shortcut.

## The reflexes that are not optional

| Situation | First tool |
| --- | --- |
| Changing a signature, constant, enum case, port, or DTO field | `find_referencing_symbols` on the symbol — the change is complete when every reference in the list has been visited, not when grep stops matching |
| "Who implements / consumes this port" | `find_referencing_symbols` on the port interface — the adapter surfaces as its `implements` clause |
| Creating a file that mirrors an exemplar | `find_symbol` to find the exemplar, `get_symbols_overview` to read its shape |
| "Where does X live" | `find_declaration` / `find_symbol` — never globbing for the class file |
| Long handler or provider, only its structure needed | `get_symbols_overview` |
| What is broken in a file you just edited | `get_diagnostics_for_file` — **it is not pushed to you, you must ask** |
| String literal, comment, migration, **`config/modules/*.yaml`** (the port→adapter alias — the server does not index YAML) | Grep — that is its lane |

Serena addresses symbols by **name path** and **relative path**, not by line/character
position: `find_referencing_symbols(name_path: "TagRepositoryPort", relative_path: "src/…/TagRepositoryPort.php")`.
Paths come back with Windows backslashes and are accepted either way.

## The cold index answers wrong, not empty

Right after the server starts, repeated identical calls returned 0, 0, 0, 0, 3, 4, 7, then 8
files for the same symbol. **Never record "no consumers" from a first call** — repeat it once
and take the larger answer. This is the single most expensive trap in this tooling.

`.claude/worktrees/` is excluded through `ignored_paths` in `.serena/project.yml`, because
Serena ignores `.git/info/exclude` and would otherwise index every stale worktree as
duplicate classes. If a symbol shows implausible duplicates, check that file first.

## `Grep` over-reports here, it does not under-report

Measured on `AuditExportTooLargeException`: Serena returns **8 files**, `Grep` returns **12**.
The four extras are the declaration itself, two `MODULE.md` files, and a `{@see}` docblock in
an unrelated exception class. Nothing real was missed — `Grep`'s failure mode on this side is
prose, not blindness.

**This app's `tests/` are indexed normally** — four of those eight files are test files. The
frontend does *not* have that property: its specs are excluded from the language server's
project entirely, so a frontend rename needs a `Grep` pass that a backend one does not.

## Where the server stops

**`find_implementations` is not declared on the `fg-*` agents, and answers `[]` in the main
session.** Measured on `OrganizationAuditFeedPort`, which `OrganizationAuditFeedService`
genuinely implements: the result is an empty list, not an error. Intelephense's free edition
does not implement the operation, and `[]` reads exactly like "nothing implements this".

**Use `find_referencing_symbols` on the port interface instead.** The adapter surfaces as its
`implements` clause, ahead of the handlers that inject it. That is the whole of what
`find_implementations` would have given.

**There is no call hierarchy** — no tool answers "who calls this method". `find_referencing_symbols`
on the method is the nearest thing.

**Diagnostics no longer arrive on their own.** The removed plugin pushed them after every
`Write`/`Edit`; `get_diagnostics_for_file` is on demand only. They remain earlier than the
gate, not a substitute for it: `make test` still decides when a task is done, and it sees the
deptrac violation that no language server does.

The **`.mjs` hooks and launcher** under `.claude/` have no server at all: no diagnostics, no
navigation. Read them normally.

> Triplicated by design — the monorepo root and `fireguard-sso-web` each carry their own copy,
> because rules are not a plugin component and do not travel to another session root.
> **Change one, change all three.**
