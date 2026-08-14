---
paths:
  - 'src/**/*.php'
  - 'tests/**/*.php'
---

# Use the language server

Intelephense is running on every `.php` file in this app. It is wired for you; the reflex is
not.

**Ask the LSP a question about a symbol. Grep a question about text.**

- `findReferences` before `Grep` for "who uses this". Grep matches a string, the LSP matches
  the symbol: no aliased import missed, no comment or unrelated same-named class counted.
- `goToDefinition` before guessing a path. `workspaceSymbol` before globbing for a class file.
- `documentSymbol` before reading a long handler or provider whole, when you only need its shape.
- Grep stays right for string literals, comments, migrations, and **`config/modules/*.yaml`** —
  the port→adapter alias lives there and the server does not index YAML.

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
