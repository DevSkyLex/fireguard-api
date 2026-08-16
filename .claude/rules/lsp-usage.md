---
paths:
  - 'src/**/*.php'
  - 'tests/**/*.php'
---

# Use the language server

Intelephense is running on every `.php` file in this app. It is wired for you; the reflex is
not.

**Ask the LSP a question about a symbol. Grep a question about text.** A symbol question
answered by grep alone is a review finding, not a shortcut.

## The reflexes that are not optional

| Situation | First tool |
| --- | --- |
| Changing a signature, constant, enum case, port, or DTO field | `findReferences` on the symbol — the change is complete when every reference in the list has been visited, not when grep stops matching |
| "Who implements / consumes this port" | `findReferences` on the port interface — the adapter surfaces as its `implements` clause |
| Creating a file that mirrors an exemplar | `workspaceSymbol` to find the exemplar, `documentSymbol` to read its shape |
| "Where does X live" | `goToDefinition` / `workspaceSymbol` — never globbing for the class file |
| Long handler or provider, only its structure needed | `documentSymbol` |
| String literal, comment, migration, **`config/modules/*.yaml`** (the port→adapter alias — the server does not index YAML) | Grep — that is its lane |

## Worktrees: which diagnostics to trust

Intelephense resolves types from the checkout it indexes. A secondary worktree without
`vendor/` installed floods "undefined type" diagnostics that mean nothing — run
`composer install` first, or ignore that worktree's diagnostics entirely and let the gates
decide. Diagnostics arriving for files in a worktree you are **not** currently editing are
stale snapshots of another branch's mid-edit state; never "fix" one without reading the
file first. `make test` remains the decision.

Positions are **1-based on both line and character**, as shown in the editor gutter.

**Diagnostics arrive on their own** after every `Write`/`Edit`, and cost nothing to read.
They are earlier than the gate, not a substitute for it: `make test` still decides when a
task is done. Measured on this repo, Intelephense answers in ~0.7 s warm where `make phpstan`
takes ~31 s — but it is not phpstan, and it does not see a deptrac violation.

## Where the server stops

**`goToImplementation` and the call hierarchy are unavailable** — both are Intelephense
premium features. The first returns "No definition found" even on a real
`Application/Port/Outbound/…Port` interface; `prepareCallHierarchy` answers
`Unhandled method`. Do not read either result as "nothing implements this".

**Use `findReferences` on the port interface instead.** On `TagRepositoryPort` it returns 123
references across 24 files, and the adapter — `Infrastructure/Persistence/Doctrine/Repository/TagRepository.php`,
its `implements` clause — is the first hit after the declaration itself, ahead of the handlers
that inject it. That is the whole of what `goToImplementation` would have given.

The **`.mjs` hooks and launcher** under `.claude/` have no server at all: no diagnostics, no
navigation. Read them normally.

> Triplicated by design — the monorepo root and `fireguard-sso-web` each carry their own copy,
> because rules are not a plugin component and do not travel to another session root.
> **Change one, change all three.**
