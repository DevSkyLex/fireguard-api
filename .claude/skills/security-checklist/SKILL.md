---
name: security-checklist
description: What to verify when a change touches auth, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC, the audit ledger, tenant scoping, secrets, or the Stripe webhook in fireguard-sso-api — the crown-jewel paths, fail-closed rules, and the denial-path tests each finding needs. Use before and after editing any of those.
---

# Security checklist

FireGuard is an identity **and** fire-safety platform. These paths are its crown jewels.

## The threat model that matters here

Not an unauthenticated stranger — **an authenticated, legitimate user of organization A reading organization B's records** through an endpoint that checked they were logged in and nothing else. Assume the caller is hostile and holds a valid token.

## The paths

`src/Auth` · `src/OAuth` · `src/Session` · `src/TrustedDevice` · `src/Otp` · `src/Authorization` · `src/Tenant` · `src/Audit` · `src/Billing` · `config/packages/security.yaml` · `config/packages/rate_limiter.yaml` · `config/jwt/`.

All of these live on the **`auth`** database except Billing — a change crossing that line deserves a second look.

## The checklist

**Authorization per operation, not per route family.** For each endpoint: is there a check that the caller may act on *this* record, not merely that they hold a role? A resource-level `is_granted('ROLE_USER')` with an id in the path and no ownership check is an IDOR. Trace it from the Resource's security expression through the processor or provider into the handler, and name where the ownership check lives — or that it does not.

**Tenant and organization isolation.** Every query on business data is scoped by organization or tenant. A repository method taking only an entity id, called from a handler that received an organization id it never used, is the shape of a cross-tenant leak.

**Fail closed.** A missing permission, an unparseable token, an expired grant, an unreachable authorization service — each must **deny**. Look for default-allow in any disguise: `catch (Throwable) { return true; }`, a null-check falling through to success, a feature flag whose absence grants access.

**Tokens, cookies, sessions.** Lifetimes and rotation · refresh-token reuse detection · PKCE on public clients · redirect-URI validation against an **exact** allowlist, never a prefix match · `HttpOnly`, `Secure`, `SameSite` on every cookie · session fixation on privilege change · complete revocation on logout, trusted devices included.

**OTP and MFA.** Rate limiting **and** lockout on verification · constant-time comparison · single use enforced · sane expiry · no account-existence leak through timing or message wording.

**The audit ledger.** The hash chain stays intact · the security-relevant action actually writes an entry · nothing can write an entry claiming to be another actor.

**Secrets.** Nothing committed. `.env*` and `config/jwt/` are blocked by a hook and denied to Read. A credential found in a fixture, test, migration, or log statement is reported **by location, never by value**.

**The Stripe webhook.** Signature verified **before** parsing · idempotent on replay · amounts and plan ids re-read from Stripe, never trusted from the request body.

**Rate limiting.** Present on login, OTP, password reset, registration, and token endpoints — the cheap-to-hammer ones.

## Verify with the tools

```bash
composer audit
php -d memory_limit=1G bin/console debug:firewall
make deptrac
```

`debug:firewall` shows access-control as Symfony **actually resolves it**, which is often not what `security.yaml` appears to say when patterns overlap — the **first matching** rule wins. Read the resolved output, not the file.

`-d memory_limit=1G` is required; a bare console call runs out of memory building the container.

## Every finding needs its regression test

A security fix without a test will be undone by a later refactor. Pair each confirmed risk with the test that must exist:

| Risk | Test |
| --- | --- |
| missing ownership check | **403** for an authenticated caller without the permission |
| cross-tenant read | **404** for a record in another organization |
| missing rate limit | **429** after the configured threshold |
| replayable webhook | second delivery of the same event rejected |
| default-allow branch | the failure path denies |

## When you cannot verify something, say so

An authorization path you could not trace, a config you could not resolve, a runtime behaviour a static read cannot prove — state it explicitly. Silence reads as "checked and safe", and that is the one thing a security report must never imply falsely.
