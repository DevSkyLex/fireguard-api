# Domain layer

Domain depends on no framework or project layer other than `SharedDomain`.

- Enforce invariants inside aggregates and value objects through named constructors
  and intent-revealing methods. Avoid public setters.
- Immutable types are `final readonly`. Domain events describe completed facts in
  the past tense and carry identifiers rather than behaviour.
- Value objects throw `Shared\Domain\Exception\InvalidValueException`. Domain
  exceptions expose named constructors and contain no HTTP status.
- Never add Doctrine attributes, API Platform metadata, transport DTOs or services
  to Domain models. Persistence uses separate Infrastructure Records and mappers.
- Unit tests require no container, database or mocks. If a Domain test needs one,
  the model has crossed its boundary.

Run the focused unit tests plus PHPStan and Deptrac after a Domain change.
