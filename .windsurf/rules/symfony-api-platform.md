---
trigger: model_decision
description: Used only during controller/resource/processor/provider generation.
---

# Symfony & API Platform Rules

## Symfony 7.3
- Use attributes over YAML/XML for routing/config when possible.
- Use constructor promotion, typed properties, `readonly` for immutability.
- Use Messenger for async/outbox if needed; keep use-case sync by default.

## API Platform 4+
- Resources are thin metadata. No business logic in resource classes.
- Use State Providers/Processors instead of controllers.
- Separate Input vs Output DTOs. Hide internal IDs if not needed.
- Filters are optional: add only when required by queries.

## Security
- Use voters/checkers in Presentation + Application policies.
- Validate input with Symfony Validator on Input DTOs.
- Do not expose exceptions directly; map to Problem+JSON if desired.

## Serialization
- Define groups on Output DTOs, not on domain/persistence models.
