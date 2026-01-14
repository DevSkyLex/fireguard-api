# Security Guide

This document summarizes security-sensitive configuration and operational practices for Fireguard Auth Server.

## Secrets and Keys

- Store secrets in environment variables or a secrets manager.
- Never commit production secrets to the repository.
- Protect `APP_SECRET` and `OAUTH_ENCRYPTION_KEY` like credentials.
- Use strong, unique encryption keys per environment.

JWT keys:
- Use environment-specific `config/jwt/private.key` and `config/jwt/public.key`.
- Keep private keys encrypted at rest and restrict filesystem permissions.
- Rotate keys and invalidate old tokens if required by policy.

## Token and Cookie Security

- Refresh tokens are issued in HttpOnly cookies with SameSite=Strict.
- In production, cookies are marked Secure and use the `__Host-` prefix.
- Keep short access token lifetimes and use refresh tokens for renewals.
- Revoke tokens on logout and suspicious activity.

## OAuth2 and OIDC Hardening

- Use the authorization code flow with PKCE for browser and public clients.
- Validate redirect URIs strictly and avoid wildcards.
- Limit allowed scopes for each client and rotate secrets regularly.

## Rate Limiting

Rate limiters are defined in `config/packages/rate_limiter.yaml`:
- `login`
- `oauth_token`
- `oauth_introspection`
- `oauth_revocation`

Tune limits to match threat models and expected traffic.

## CORS and Network Controls

- Restrict `CORS_ALLOW_ORIGIN` to trusted origins.
- Always serve OAuth and Auth endpoints over TLS in production.

## Logging and Auditing

- Avoid logging access tokens, refresh tokens, or raw credentials.
- Use domain events to build audit trails where required by compliance.
- Monitor for failed logins, token revocations, and abuse patterns.
