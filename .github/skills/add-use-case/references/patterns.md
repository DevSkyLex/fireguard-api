# Add Use Case Patterns

## Command-side pattern

- `CommandMessage` for write requests
- `CommandHandler` for handlers
- `ResultMessage` for explicit application results
- convert domain or value-object validation errors to `InvalidArgumentException` at the handler boundary

## Query-side pattern

- `QueryMessage` for read requests
- use `Pagination` when the provider exposes paginated collection endpoints
- keep provider-side filter parsing explicit and map query params deliberately
- return explicit item result objects and wrap paginated collections in shared pagination contracts where the module already does so

## Handler responsibilities

- build value objects and aggregates
- enforce business invariants and scope
- save or fetch through ports only
- return explicit result DTOs instead of arrays

## Presentation handoff

- processors dispatch commands and map results to output DTOs
- providers parse filters and pagination, then ask the query bus
- permission names should stay explicit in Presentation and aligned with module conventions
