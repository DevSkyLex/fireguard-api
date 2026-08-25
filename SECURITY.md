# Security Guide

This document summarizes security-sensitive configuration and operational practices for Fireguard API.

## Scope

- This guide focuses on configuration, runtime hardening, and operational safeguards.
- Application-specific flows and endpoints are documented in each module guide.

## Secrets and key management

- Store secrets in environment variables or a secrets manager.
- Never commit production secrets to the repository.
- Protect `APP_SECRET` and `OAUTH_ENCRYPTION_KEY` like credentials.
- Use strong, unique encryption keys per environment.

JWT keys:
- Use environment-specific `config/jwt/private.key` and `config/jwt/public.key`.
- Keep private keys encrypted at rest and restrict filesystem permissions.
- Rotate keys and invalidate old tokens if required by policy.

## Token and cookie security

- **Every bearer token is signature-verified before any of its claims is trusted.**
  `OAuth2Authenticator` validates the RSA signature and the expiry first, then branches on
  the token's origin. Both issuance paths sign with `config/jwt/private.key` — the login
  flow through `JwtTokenAdapter`, the OAuth2 flow through League's `AuthorizationServer` —
  so a single verification key covers both. The database lookup keys on `jti` and never
  binds it back to `sub`, which is why the signature check must not be conditional: a
  branch that skipped it would let a forged token carrying a live `jti` and an arbitrary
  `sub` authenticate as that subject.
- Refresh tokens are issued in HttpOnly cookies with SameSite=Strict.
- In production, cookies are marked Secure and use the `__Host-` prefix.
- Keep short access token lifetimes and use refresh tokens for renewals.
- Revoke tokens on logout and suspicious activity.
- Access tokens include email/roles/permissions by default for backward compatibility.
  - To minimize token size and reduce data exposure, set `ACCESS_TOKEN_INCLUDE_EMAIL=false` and `ACCESS_TOKEN_INCLUDE_RBAC=false`.

## OAuth2 and OIDC hardening

- Use the authorization code flow with PKCE for browser and public clients.
- Validate redirect URIs strictly and avoid wildcards.
- Limit allowed scopes for each client and rotate secrets regularly.

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
- `invitation_resend` (per user)
- `invitation_accept` (per user)

Tune limits to match threat models and expected traffic. In test environments, limits may be overridden for determinism.

## HTTP security headers

Security headers are automatically added to all responses via `SecurityHeadersSubscriber`.

Headers applied:

| Header | Value | Purpose |
|--------|-------|---------|
| `X-Content-Type-Options` | `nosniff` | Prevent MIME type sniffing |
| `X-Frame-Options` | `DENY` | Prevent clickjacking (legacy) |
| `X-XSS-Protection` | `0` | Disabled (rely on CSP instead) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` | Control referrer information |
| `Content-Security-Policy` | `default-src 'none'; frame-ancestors 'none'` | Restrictive CSP for API |
| `Permissions-Policy` | Restrictive | Block sensitive browser features |
| `Strict-Transport-Security` | `max-age=31536000; includeSubDomains` | HSTS (production only) |

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

| Event | Fields |
| --- | --- |
| `auth.user_logged_in_event` | `user_id`, `email` (sanitized), `email_hash`, `ip` (sanitized), `ip_hash` |
| `auth.login_failed_event` | `email` (sanitized), `email_hash`, `ip` (sanitized), `ip_hash`, `reason` |
| `oauth.token_issued_event` | `grant_type`, `client_id`, `user_id` (optional), `ip` |
| `oauth.token_issue_failed_event` | `grant_type`, `client_id`, `ip`, `reason` |
| `oauth.token_refreshed_event` | `user_id`, `ip` |
| `oauth.token_refresh_failed_event` | `user_id`, `ip`, `reason` |

## Incident response

- Revoke active tokens and rotate keys when credentials are suspected to be compromised.
- Audit recent authentication and token-issuance events.
- Increase rate limits only after abuse investigations are complete.

## Vulnerability reporting

Use your organization’s standard security reporting process for disclosures and incident handling.

## Data retention

- Periodic cleanup of expired/revoked auth data is available:
  - Command: `php bin/console app:cleanup:auth-data --days=90`
  - Dry run: `php bin/console app:cleanup:auth-data --days=90 --dry-run`
- Default retention is set by `DATA_RETENTION_DAYS`.
