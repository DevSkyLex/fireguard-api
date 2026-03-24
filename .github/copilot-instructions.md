This repository is a modular Symfony backend for authentication, authorization, OAuth2/OIDC, tenant-scoped business modules, and audit-sensitive workflows.

Use repository guidance before generic Symfony conventions. Start with [ARCHITECTURE.md](../ARCHITECTURE.md), [SECURITY.md](../SECURITY.md), [OPERATIONS.md](../OPERATIONS.md), and the relevant module guide under [src/](../src/). For prompt, skill, agent, and hook selection, use [.github/AGENTS.md](./AGENTS.md).

Follow the repository architecture strictly:
- Presentation -> Application -> Domain
- Infrastructure -> Application
- Domain stays framework-agnostic

Keep business rules in application use case handlers, not in API Platform processors, providers, controllers, serializers, or subscribers.

Preserve tenant and organization isolation. When changing reads or writes, verify `tenantId`, `organizationId`, ownership checks, and permission names still match the operation.

Respect the two-database split: auth and identity concerns use the auth migration and persistence paths, while business modules use the main migration and persistence paths.

Treat Auth, OAuth, Session, Otp, TrustedDevice, Authorization, and Audit code as security-sensitive. Avoid exposing secrets, tokens, OTP values, internal identifiers, or unsafe logs. Prefer fail-closed behavior over fail-open behavior.

When changing API behavior, also consider validation, status codes, serialization, filters, OpenAPI impact, and regression tests.

Use the Makefile targets as the default command surface:
- `make phpunit-fast` for quick tests
- `make phpstan`, `make deptrac`, and `make lint` for validation
- `make test` before finishing broader changes

During code review, report only actionable findings. Prioritize correctness, security, tenant isolation, architecture violations, persistence risks, and missing tests. Ignore formatting and non-essential refactor suggestions.
