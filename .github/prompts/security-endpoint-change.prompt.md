---
name: "Security Endpoint Change"
description: "Implement or review a security-sensitive endpoint change with fail-closed checks and regression coverage."
argument-hint: "Area + change, for example: OAuth token refresh hardening"
agent: "agent"
---

Handle a security-sensitive backend change in this repository.

Before doing any implementation work:

1. Load [security-sensitive-change](../skills/security-sensitive-change/SKILL.md).
2. Load [add-use-case](../skills/add-use-case/SKILL.md) or [api-platform-resource](../skills/api-platform-resource/SKILL.md), depending on whether the change is primarily application-flow or API-surface work.
3. Load [module-tests](../skills/module-tests/SKILL.md).

Then:

- inspect the full request-to-handler-to-persistence flow before editing
- preserve or strengthen permission checks, scope isolation, denial paths, and audit behavior
- avoid exposing tokens, OTPs, secrets, session identifiers, or unnecessary PII in DTOs, logs, errors, or responses
- keep processors and providers thin, with real business and security invariants enforced in handlers or domain logic
- add or update regression tests for both success and refusal paths, including `401`, `403`, `429`, or scope denial when relevant
- update related public documentation when the implemented security contract changes

Make the change directly in the workspace unless the request is explicitly a review-only task.
