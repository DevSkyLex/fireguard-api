---
name: fg-api-domain
description: "Add Domain-layer code — an aggregate, a value object, a domain event, or a domain exception — with the invariants enforced inside the model."
---

# fg-api-domain

Work from the API root. Read `AGENTS.md`, `.codex/workflow.md`,
`.codex/rules/domain.md`, `ARCHITECTURE.md` and the owning `MODULE.md`.

Add the requested aggregate, value object, event or exception inside Domain and enforce
its invariant there. Import nothing from Application, Infrastructure or Presentation.
Use named constructors, intent-revealing methods and `final readonly` for immutable types.
Domain events use past-tense facts and contain identifiers, not behavior. Domain exceptions
contain no HTTP status; Presentation maps them centrally.

Keep Doctrine and framework metadata on Infrastructure Records. Add container-free unit
tests with no mocks. Run the focused unit tests plus PHPStan and Deptrac. Preserve other
worktree changes and report the exact files and checks.
