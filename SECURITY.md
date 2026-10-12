# Security Guide

**Reading guide:** [Documentation index](docs/README.md) · [Related guide](docs/operations/security-runbooks.md).

This document summarizes security-sensitive configuration and operational practices for Fireguard API.

## Scope

- This guide focuses on configuration, runtime hardening, and operational safeguards.
- Application-specific flows and endpoints are documented in each module guide.

## Secrets and key management

- Store secrets in environment variables or a secrets manager.
- Never commit production secrets to the repository.
- Protect `APP_SECRET` and `OAUTH_ENCRYPTION_KEY` like credentials.
- Use strong, unique encryption keys per environment.
- HTTP replay fingerprints and encrypted result snapshots use their own keys:
  `HTTP_IDEMPOTENCY_HMAC_KEY`, `HTTP_IDEMPOTENCY_ENCRYPTION_KEYS` and
  `HTTP_IDEMPOTENCY_ENCRYPTION_ACTIVE_VERSION`. Never derive them from `APP_SECRET`,
  OAuth encryption material or MFA keys. Keep the versioned decryption keys needed
  for unexpired responses and the reviewed backup/rollback horizon.

JWT keys:

- Use environment-specific `config/jwt/private.key` and `config/jwt/public.key`.
- Keep private keys encrypted at rest and restrict filesystem permissions.
- Rotate keys and invalidate old tokens if required by policy.

## Token and cookie security

- **Every bearer token is signature-verified before any of its claims is trusted.**
  `OAuth2Authenticator` validates the RSA signature and the expiry first, then branches on
  the token's origin. Both issuance paths sign with `config/jwt/private.key` — the login
  flow through `JwtTokenAdapter`, the OAuth2 flow through League's `AuthorizationServer` —
  so a single verification key covers both. OAuth token-table lookup uses `jti`; interactive session lookup also binds the signed subject, which is why the signature check must not be conditional: a
  branch that skipped it would let a forged token carrying a live `jti` and an arbitrary
  `sub` authenticate as that subject.
- Refresh tokens are issued in HttpOnly cookies with SameSite=Strict.
- In production, cookies are marked Secure and use the `__Host-` prefix.
- Keep short access token lifetimes and use refresh tokens for renewals.
- Revoke tokens on logout and suspicious activity.
- **Revoking a session invalidates its access token on the next request.**
  Login-flow tokens are not rows in the OAuth2 token table, so `OAuth2Authenticator`
  resolves them through `SessionStatusPort` instead. Interactive tokens require
  `activeSessionId(accessTokenId, userId)` to identify a current, non-revoked
  session belonging to the signed subject. Issuance records that anchor before
  returning tokens; missing, rotated or revoked session tokens are rejected.
  `isAccessTokenRevoked` is a diagnostic lookup, not authorization for untracked tokens.
- Access tokens include email/roles/permissions by default for backward compatibility.
  - To minimize token size and reduce data exposure, set `ACCESS_TOKEN_INCLUDE_EMAIL=false` and `ACCESS_TOKEN_INCLUDE_RBAC=false`.

## OAuth2 and OIDC hardening

- Use the authorization code flow with PKCE for browser and public clients.
- Validate redirect URIs strictly and avoid wildcards.
- Limit allowed scopes for each client and rotate secrets regularly.

Generic federated providers are configured server-side in
[`federated_providers.yaml`](config/packages/federated_providers.yaml), using stable
provider identifiers and exact HTTPS issuer, authorization, token and JWKS endpoints.
The browser cannot supply those endpoints. A changed issuer requires a new provider
identity. The [generic OIDC adapter](src/Auth/Infrastructure/Adapter/Federation/GenericOidcProviderAdapter.php)
verifies the RS256 signature before exposing claims, then checks issuer, client
audience, authorized party where applicable, expiry/issuance times and the stored
nonce proof. PKCE and single-use flow state remain mandatory. Optional UserInfo must
return the same subject; provisioning requires verified address possession and follows
the configured signup, email-verification, MFA and trusted-device policy.

## MFA material and recovery responses

Canonical recovery codes are stored as hashes; verification atomically consumes a
matching unused code. TOTP secrets use authenticated versioned encryption. Enrollment
and regeneration create one recovery-code set for the committed operation.

The plaintext recovery response may be returned again for that same authorized
operation through the encrypted HTTP result receipt for at most five minutes from
the first commit. This is a bounded delivery retry, not another generation or an
unrestricted recovery-code read API. Recheck the current actor, credential/flow proof
and MFA revision before decrypting; a revoked, changed or expired proof denies replay.
Do not log the codes, tokens, plaintext response or ciphertext. Expiry never slides on
retry, and an expired receipt continues to prevent duplicate execution.

## Durable HTTP idempotency

All client mutations, including authentication writes, require `Idempotency-Key`
after the documented compatibility cutover. A key identifies a logical request;
it is not an authentication credential. Current authentication, tenant/organization
scope and operation authorization are checked before execution and before replay.
External callbacks use a verified provider-native event identity after their normal
signature checks. Replay never bypasses bearer-token verification or session revocation.

Receipts belong to the mutation's explicit auth or main database. Business state,
the primary application result snapshot and the completed receipt share the final
owning transaction. Media retains its root transaction. Changed input, a competing
owner, uncertain commit or expired result fails closed instead of repeating a write.

Canonical input and security proof fingerprints use purpose-separated HMACs; raw
credentials are never persisted in the receipt. A secret result uses authenticated,
versioned encryption for at most five minutes from its first commit. Bind the scope,
operation/key identity, input fingerprint, verified proof metadata and absolute expiry
as authenticated data before accepting ciphertext. Verify that proof and deadline
before decryption, including during reconciliation. Ordinary response bodies have a
24-hour replay window. Replays cannot extend either deadline.

Cleanup erases expired result bytes on each owning database while retaining durable
non-reusable receipts. Offline replay tombstones remain until their owning scope is
deleted; body expiry alone must never allow an old offline mutation to execute again.
See [the rollout procedure](OPERATIONS.md#http-idempotency-rollout) and
[cleanup operations](docs/operations/workers-and-scheduler.md#http-idempotency-result-retention).

## Rate limiting

Rate limiters are defined in `config/packages/rate_limiter.yaml`:

- `login`
- `mfa_verify`
- `oauth_token`
- `oauth_authorize`
- `oauth_introspection`
- `oauth_revocation`
- `oauth_consent_check`
- `oauth_consent_grant`
- `token_refresh`
- `password_reset_request`
- `password_reset`
- `password_reset_confirm`
- `mfa_resend`
- `otp_challenge_create`
- `otp_challenge_verify`
- `invitation_preview` (per IP — the endpoint is public)
- `calendar_feed` (per IP — the public `.ics` member feed endpoint)
- `invitation_resend` (per user)
- `invitation_accept` (per user)
- `email_change_request` (per user, 5/min — every accepted call emails an arbitrary address and answers the same email-taken question as `registration`, so the budgets match)
- `email_change_request_ip` (per IP, 5/min — second dimension on the same endpoint: the per-user budget scales with the number of accounts an attacker controls, this one does not)
- `email_change_confirm` (per IP — the confirm endpoint is public, the emailed token is the credential)
- `facility_geocode` (per user, 30/min — each accepted call is proxied to the external geocoding provider, so the budget protects the shared outbound Nominatim channel and us, not a secret; the adapter additionally serializes the aggregate outbound flow to 1 req/s per Nominatim's usage policy)

Tune limits to match threat models and expected traffic. In test environments, limits may be overridden for determinism.

## HTTP security headers

Security headers are automatically added to all responses via `SecurityHeadersSubscriber`.

Headers applied:

| Header                      | Value                                        | Purpose                          |
| --------------------------- | -------------------------------------------- | -------------------------------- |
| `X-Content-Type-Options`    | `nosniff`                                    | Prevent MIME type sniffing       |
| `X-Frame-Options`           | `DENY`                                       | Prevent clickjacking (legacy)    |
| `X-XSS-Protection`          | `0`                                          | Disabled (rely on CSP instead)   |
| `Referrer-Policy`           | `strict-origin-when-cross-origin`            | Control referrer information     |
| `Content-Security-Policy`   | `default-src 'none'; frame-ancestors 'none'` | Restrictive CSP for API          |
| `Permissions-Policy`        | Restrictive                                  | Block sensitive browser features |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains`        | HSTS (production only)           |

Configuration via environment variables:

```env
# Enable/disable security headers (default: true)
SECURITY_HEADERS_ENABLED=true

# Custom CSP (leave empty for default restrictive policy)
SECURITY_HEADERS_CSP=

# HSTS max-age in seconds (default: 31536000 = 1 year)
SECURITY_HEADERS_HSTS_MAX_AGE=31536000
```

For authenticated requests (with `Authorization` header), additional cache headers are set:

- `Cache-Control: no-store, no-cache, must-revalidate, private`
- `Pragma: no-cache`

## CORS and network controls

- Restrict `CORS_ALLOW_ORIGIN` to trusted origins.
- Always serve OAuth and Auth endpoints over TLS in production.

## Logging, monitoring, and audit

- Avoid logging access tokens, refresh tokens, or raw credentials.
- Use domain events to build audit trails where required by compliance.
- Monitor for failed logins, token revocations, and abuse patterns.
- Security logs hash PII by default (email/ip); enable full PII with `SECURITY_LOG_INCLUDE_PII`.

### Audit Ledger

- Security-relevant events are persisted in the Audit module (`audit_events`).
- Audit events are hash-chained (`prev_hash` + payload hash) to detect tampering.
- Audit APIs are read-only and protected by `audit.read`.
- Audit PII handling uses the same PII settings as security logs.
- Producers sanitize an owner-published audit request before enqueueing it in their
  own outbox transaction. Audit appends the ledger entry and its delivery receipt
  atomically on auth after producer commit; retry preserves actor attribution and
  cannot append the same requested record twice. No transaction spans auth and main.

### Audit Event Format (Security Log)

Security events are emitted as structured logs (JSON) on the `security` channel.
Core fields vary by event but follow this common shape:

```json
{
  "message": "OAuth2 token issued",
  "context": {
    "event": "oauth.token_issued_event",
    "user_id": "uuid",
    "client_id": "uuid",
    "grant_type": "client_credentials",
    "ip": "x.x.x.x",
    "reason": "optional"
  }
}
```

Event-specific fields:

| Event                              | Fields                                                                    |
| ---------------------------------- | ------------------------------------------------------------------------- |
| `auth.user_logged_in_event`        | `user_id`, `email` (sanitized), `email_hash`, `ip` (sanitized), `ip_hash` |
| `auth.login_failed_event`          | `email` (sanitized), `email_hash`, `ip` (sanitized), `ip_hash`, `reason`  |
| `oauth.token_issued_event`         | `grant_type`, `client_id`, `user_id` (optional), `ip`                     |
| `oauth.token_issue_failed_event`   | `grant_type`, `client_id`, `ip`, `reason`                                 |
| `oauth.token_refreshed_event`      | `user_id`, `ip`                                                           |
| `oauth.token_refresh_failed_event` | `user_id`, `ip`, `reason`                                                 |

## Incident response

- Revoke active tokens and rotate keys when credentials are suspected to be compromised.
- Audit recent authentication and token-issuance events.
- Increase rate limits only after abuse investigations are complete.

## Vulnerability reporting

Use your organization’s standard security reporting process for disclosures and incident handling.

## Data retention

- Periodic cleanup of expired/revoked auth data is available:
  - Command: `php -d memory_limit=1G bin/console app:cleanup:auth-data --days=90`
  - Dry run: `php -d memory_limit=1G bin/console app:cleanup:auth-data --days=90 --dry-run`
- Default retention is set by `DATA_RETENTION_DAYS`.
- HTTP result retention is independent of auth-data retention. Run
  `php -d memory_limit=1G bin/console app:cleanup:http-idempotency-results` through
  the managed recurring maintenance job; the command erases expired payloads on main
  and auth without deleting replay tombstones or logging their contents.
