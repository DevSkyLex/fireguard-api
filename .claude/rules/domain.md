---
paths:
  - 'src/*/Domain/**/*.php'
---

# Domain layer

> Abridgement of the Domain half of the `hexagonal-layout` skill — **change one, change both.**

**The Domain depends on nothing.** Deptrac allows it exactly one edge — `SharedDomain`. A PreToolUse hook blocks a `use` of `Application\`, `Infrastructure\`, or `Presentation\` before deptrac ever runs.

No Doctrine, no Symfony, no API Platform, no vendor SDK. That constraint is the point: a model that cannot reach a database cannot hide a query inside a business rule.

- **Models and value objects enforce invariants.** A value object validates in its constructor and is immutable after; a caller holding one may assume it is valid.
- Named constructors (`FacilityId::fromString`) over public constructors taking raw scalars. `final readonly` where the type is genuinely immutable. `__toString()` on identifiers.
- An aggregate exposes **intent-revealing mutators** (`$facility->archive()`), never public setters. The invariant lives inside the method.
- Throw `Shared\Domain\Exception\InvalidValueException` from a value object. Domain exceptions take named constructors (`FacilityNotFoundException::withId($id)`) and **carry no HTTP status** — Presentation maps the class centrally in an `EventSubscriber`.
- Events are **past tense**, named for the fact (`FacilityArchivedEvent`), carrying identifiers and no behaviour. The Domain _defines_ the event; the handler dispatches it.
- **Never put a Doctrine attribute or framework type on a Domain model.** The `Record` in `Infrastructure/Persistence/Doctrine/Record/` is a separate shape; a mapper translates between them.
- A domain test needs no container, no database, and **no mocks**. If it needs a mock, the model is reaching outside itself.

House style: `declare(strict_types=1);` · **two-space indent** · `// #region` blocks · PHPDoc with `@category`, `@version`, `@author Valentin FORTIN <contact@valentin-fortin.pro>`.
