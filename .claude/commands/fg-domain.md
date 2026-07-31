---
description: Add Domain-layer code — an aggregate, a value object, a domain event, or a domain exception — with the invariants enforced inside the model.
argument-hint: '<Module> <kind> <Name> — e.g. "Facility value-object FacilityCode"'
---

Delegate to the **fg-domain-builder** subagent: $ARGUMENTS

Require it to:

1. Import **nothing** from Application, Infrastructure, or Presentation. Deptrac allows the Domain exactly one edge — to `SharedDomain` — and a hook blocks the import before deptrac ever sees it.
2. Enforce the invariant **inside** the model: named constructors, `final readonly` where the type is immutable, intent-revealing mutators (`$facility->archive()`), no public setters.
3. Throw `Shared\Domain\Exception\InvalidValueException` from a value object. Give domain exceptions named constructors (`FacilityNotFoundException::withId($id)`) and **no HTTP status** — the Presentation layer maps those centrally in an `EventSubscriber`.
4. Name events in the past tense for the fact that happened, carrying identifiers and no behaviour. The Domain **defines** the event; the handler dispatches it.
5. Never put a Doctrine attribute or a framework type on a Domain model — the `Record` in `Infrastructure/Persistence/Doctrine/Record/` is a separate shape, and a mapper translates between them.
6. Write unit tests needing no container, no database, and **no mocks**. A domain test that needs a mock means the model is reaching outside itself.
7. Run `make cs-fix phpstan deptrac` and the targeted `phpunit`.
