This repository is a modular Symfony backend for authentication, authorization, OAuth2/OIDC, tenant-scoped business modules, and audit-sensitive workflows.

Follow the repository architecture strictly: Presentation -> Application -> Domain, Infrastructure -> Application, and Domain must stay framework-agnostic.

Keep business rules in application use case handlers, not in API Platform processors, providers, controllers, serializers, or subscribers.

Preserve tenant and organization isolation. When changing reads or writes, verify `tenantId`, `organizationId`, ownership checks, and permission names still match the operation.

Treat Auth, OAuth, Session, Otp, TrustedDevice, Authorization, and Audit code as security-sensitive. Avoid exposing secrets, tokens, OTP values, internal identifiers, or unsafe logs.

When changing API behavior, also consider validation, status codes, serialization, filters, OpenAPI impact, and regression tests.

Prefer repository-specific guidance over generic framework patterns. Use `ARCHITECTURE.md`, `SECURITY.md`, and `src/<Module>/MODULE.md` as the source of truth.

During code review, report only actionable findings. Prioritize correctness, security, tenant isolation, architecture violations, persistence risks, and missing tests. Ignore formatting and non-essential refactor suggestions.
