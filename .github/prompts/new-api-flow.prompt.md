---
name: "New API Flow"
description: "Create a new API flow in an existing module: use case, API Platform wiring, and tests."
argument-hint: "Module + action, for example: Equipment assign to facility endpoint"
agent: "agent"
---

Create a new API-facing backend flow in an existing module.

Before doing any implementation work:

1. Load [add-use-case](../skills/add-use-case/SKILL.md).
2. Load [api-platform-resource](../skills/api-platform-resource/SKILL.md).
3. Load [module-tests](../skills/module-tests/SKILL.md).
4. Load [security-sensitive-change](../skills/security-sensitive-change/SKILL.md) as well if the target module touches Auth, OAuth, Session, Otp, TrustedDevice, Authorization, Audit, or security-related config.

Then:

- inspect the closest existing module pattern before editing
- implement the command or query handler and explicit result DTO
- add or update the API Platform resource, operation, DTOs, processor or provider, OpenAPI metadata, and exception mapping
- preserve tenant, organization, ownership, and permission boundaries explicitly
- add the minimum useful regression coverage, usually handler unit tests plus functional API tests
- update module documentation if the public contract changes

Do the work end-to-end in the current workspace instead of only describing it, unless the request is explicitly review-only.
