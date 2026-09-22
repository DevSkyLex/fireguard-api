---
name: fg-api-security-review
description: "Security-review changes touching auth, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC, the audit ledger, tenant scoping, secrets, or the Stripe webhook. Read-only."
---

# fg-api-security-review

Read `AGENTS.md`, `SECURITY.md`, `.codex/workflow.md`,
[security controls](references/security-checklist.md) and
affected module documentation. Review the requested scope without editing.

Assume the primary attacker is an authenticated user of organization A probing organization B.
Trace authorization from route/Resource through handler and repository. Check fail-closed behavior,
tenant scoping, token verification, session/cookie rotation, PKCE and redirect matching, OTP/MFA,
rate limiting, audit integrity, secret handling and Stripe verification/idempotence as applicable.

Inspect denial-test results, dependency-audit evidence, firewall and ordered security configuration.
Request fresh runtime evidence from the parent when a check would write cache, fixtures or artifacts.
Never print secret values. Rank findings critical to low; each includes attacker path, impact, fix
and regression test. Explicitly list what could not be verified.
