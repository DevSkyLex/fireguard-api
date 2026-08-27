---
description: Security-review changes touching auth, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC, the audit ledger, tenant scoping, secrets, or the Stripe webhook. Read-only.
argument-hint: '[area or diff scope — e.g. "OAuth token endpoint" or "organization permissions"]'
---

Delegate to the **fg-security-auditor** subagent: $ARGUMENTS

If no scope is given, review the working-tree changes.

The threat model to hold: **not an unauthenticated stranger, but an authenticated legitimate user of organization A reading organization B's records** through an endpoint that checked they were logged in and nothing else.

Require it to check:

1. **Authorization per operation, not per route family** — a role check with an id in the path and no ownership check is an IDOR. It must trace the path from the Resource security expression through the processor into the handler, and name where ownership is verified, or that it is not.
2. **Tenant and organization isolation** — every query on business data scoped; a repository method taking only an entity id from a handler that ignored its organization id is the leak shape.
3. **Fail closed** — a missing permission, unparseable token, expired grant, or unreachable authorization service must all deny. Hunt default-allow branches.
4. **Token, cookie, session hygiene** — rotation, refresh reuse detection, PKCE, **exact-match** redirect-URI allowlists, `HttpOnly`/`Secure`/`SameSite`, session fixation on privilege change, full revocation on logout.
5. **OTP/MFA** — rate limit and lockout, constant-time comparison, single use, no account-existence leak.
6. **The audit ledger** — chain intact, entries actually written, no spoofed actor.
7. **Secrets** — nothing committed. Report location, **never the value**.
8. **The Stripe webhook** — signature verified before parsing, idempotent on replay, amounts re-read from Stripe.
9. **Rate limiting** on login, OTP, password reset, registration, token endpoints.

Substantiate with `composer audit` and `php -d memory_limit=1G bin/console debug:firewall`. That command lists **firewalls**, not `access_control` — firewalls match first-wins and match *before* access control runs, so read it for the pattern that swallows more than it looks like, then resolve `access_control` by hand against `security.yaml`.

Ask for risks ranked **critical → high → medium → low**, each with the attacker path in one sentence, the fix, and **the regression test that must accompany it**. Require an explicit statement of what could not be verified — silence there reads as "safe".
