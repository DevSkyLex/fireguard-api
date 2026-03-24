# API Platform Resource Patterns

## Resource structure

- keep `ApiResource` declarations explicit
- centralize operation names in a dedicated `Operations` class
- use input and output DTOs rather than domain objects in the API contract

## Processor pattern

- validate authentication and URI variables first
- enforce coarse permissions before dispatch
- delegate business rules to handlers
- map domain and validation failures to HTTP exceptions deliberately

## Provider pattern

- validate authentication and URI variables first
- parse request filters explicitly
- use `Pagination` and `TraversablePaginator` for paginated collections
- keep output mapping explicit

## Contract alignment

- OpenAPI metadata, DTO fields, query parameters, and runtime behavior must stay aligned
- resource-level `security` is only a coarse gate; real scope checks still belong in provider or processor and handler layers
