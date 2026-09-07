---
name: fg-api-security-review
description: "Security-review changes touching auth, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC, the audit ledger, tenant scoping, secrets, or the Stripe webhook. Read-only."
---

# fg-api-security-review

Read `AGENTS.md`, `SECURITY.md`, `.codex/workflow.md`, the security-checklist skill and
affected module documentation. Review the requested scope without editing.

Assume the primary attacker is an authenticated user of organization A probing organization B.
Trace authorization from route/Resource through handler and repository. Check fail-closed behavior,
tenant scoping, token verification, session/cookie rotation, PKCE and redirect matching, OTP/MFA,
rate limiting, audit integrity, secret handling and Stripe verification/idempotence as applicable.

Use relevant denial tests, `composer audit`, firewall and ordered security configuration inspection.
Never print secret values. Rank findings critical to low; each includes attacker path, impact, fix
and regression test. Explicitly list what could not be verified.
