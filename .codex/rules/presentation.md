# Presentation layer and API Platform

Presentation translates HTTP contracts into Application messages. It never decides a
business rule or queries a repository directly.

Every endpoint includes: Resource route and security, operation constant/metadata,
Input and Output DTOs, Processor or Provider, validation/error mapping, and functional
tests for success and denial. Existing-resource actions use an explicit success status;
delete uses 204, create uses 201 with Location where applicable.

Check ownership beyond a route-level role. Use 403 for an authenticated caller lacking
permission and 404 for another tenant's hidden record. Output DTOs expose transport
scalars/contracts, never Domain types; enum literals must match consumers byte for byte.

Map domain exceptions centrally to RFC 7807 responses. Do not add local catch/response
logic to new processors. Contextual reference catalogs enforce permission in their
provider and remain module-local. Update the module endpoint table and verify OpenAPI.
