---
name: fg-api-security-checklist
description: "What to verify when a change touches auth, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC, the audit ledger, tenant scoping, secrets, or the Stripe webhook in fireguard-sso-api — the crown-jewel paths, fail-closed rules, and the denial-path tests each finding needs. Use before and after editing any of those."
---

# fg-api-security-checklist

Read `AGENTS.md`, `SECURITY.md`, `.codex/workflow.md` and the affected module contract.
Apply this checklist to auth, OAuth/OIDC, sessions, OTP/MFA, RBAC, tenant scoping, audit,
secrets and Stripe changes:

- verify bearer signatures before trusting claims; rotate and revoke sessions correctly;
- enforce exact redirect URI allowlists, PKCE and state handling;
- scope every business query to tenant/organization and fail closed on missing context;
- distinguish 403 from non-disclosing 404 and test both;
- rate-limit login, registration, reset and OTP; use single-use values and constant-time checks;
- protect cookie flags and session fixation boundaries;
- preserve audit actor and chain integrity without logging secrets;
- verify Stripe signatures before parsing and make event replay idempotent.

Trace each control from Resource/security configuration through handler and repository.
Every finding states an attacker path, impact, fix and regression test. Run relevant denial
tests, `composer audit`, firewall/config inspection and static gates. State what could not
be verified. Reviews remain read-only unless fixes were explicitly requested.
