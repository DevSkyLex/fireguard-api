# Security Guide

This document summarizes security-sensitive configuration and operational practices for Fireguard Auth Server.

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
- `oauth_token`
- `oauth_introspection`
- `oauth_revocation`

Tune limits to match threat models and expected traffic. In test environments, limits may be overridden for determinism.

## CORS and network controls

- Restrict `CORS_ALLOW_ORIGIN` to trusted origins.
- Always serve OAuth and Auth endpoints over TLS in production.

## Logging, monitoring, and audit

- Avoid logging access tokens, refresh tokens, or raw credentials.
- Use domain events to build audit trails where required by compliance.
- Monitor for failed logins, token revocations, and abuse patterns.
- Security logs hash PII by default (email/ip); enable full PII with `SECURITY_LOG_INCLUDE_PII`.

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
