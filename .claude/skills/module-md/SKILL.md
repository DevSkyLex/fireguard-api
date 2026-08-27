---
name: module-md
description: How to write and keep current a src/<Module>/MODULE.md in fireguard-sso-api — the seven required sections, what belongs in each, and the changes that make an update mandatory in the same commit. Use whenever a module gains an endpoint, a flow, an error code, or a configuration requirement.
---

# MODULE.md

`ARCHITECTURE.md`, Documentation Standard: *"Each module must have `src/<Module>/MODULE.md`"* and *"Keep MODULE.md current with code changes."* It is a required deliverable, not a courtesy — the Module Completion Checklist lists it alongside the tests.

There are 27 of them in `src/`. **Read a sibling before writing one**; matching a neighbour beats matching this description.

## The seven sections

```markdown
# <Module>

## Overview
What this module owns, and — as importantly — what it deliberately does not.
Which database backs it (auth or main). A paragraph, not a page.

## API Endpoints
A table: route · method · operation constant · processor/provider · security.
This is the section readers actually use.

## Flows
Sequence diagrams (mermaid) for the flows that are not obvious from the endpoint
list — multi-step, cross-module, or with a compensating path.

## Architecture
The use cases, the ports and what fulfils them, the aggregates, and the
cross-module dependencies with the contract types they go through.

## Configuration
The `config/modules/<module>.yaml` entries that matter — especially **which
entity manager** each repository, processor, and provider is wired to — plus any
security.yaml rule, rate limiter, or environment variable the module needs.

## Testing
Where the tests live, what level covers what, and how to run just this module's
suite.

## Error Codes
Domain exception → HTTP status → what the consumer sees. Only when the module
defines its own.
```

## The update triggers — same commit, not "later"

A `MODULE.md` **must** be updated in the change that:

- adds, moves, or removes an **endpoint**,
- changes a **flow** — a new step, a new compensating path, a changed order,
- introduces or retires a **port** or a **contract type** consumed by another module,
- adds an **error code** or changes an exception→status mapping,
- changes a **configuration** requirement — a new entity-manager wiring, a security rule, a rate limiter, an env var,
- changes an **invariant** a reviewer must preserve.

"I'll document it after" is how the 27 files drift apart. The architecture reviewer checks for it.

## Keep it normative, not a catalog

*"Keep MODULE.md current with code changes"* — current, not exhaustive. The test: if a line would still be true after someone renames a file, it belongs. If it merely restates what the folder tree already shows, delete it.

A `MODULE.md` that lists every class is worse than none: it looks authoritative, it rots on the first refactor, and nobody notices.

## Record the *why*

The sections that age well explain decisions, not just facts:

- **which database and why** — the single most consequential decision about a module, and the one a newcomer cannot infer,
- why a cross-module dependency is allowed, and through which contract,
- why an endpoint that looks missing is deliberately absent,
- any approved deviation from `ARCHITECTURE.md`, with its justification. An exception recorded in a code comment is invisible to reviewers; here it is part of the contract.

## Say "none" explicitly

A module with no published port, no error codes, or no cross-module dependency should say so:

```markdown
## Error Codes
None. This module raises only the shared domain exceptions mapped centrally.
```

An omitted section reads as an oversight; an explicit "none" with a reason is a decision the next reader can trust.
