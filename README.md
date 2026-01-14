# Fireguard Auth Server

Modular authentication and authorization server built with Symfony. It provides OAuth 2.0 and OpenID Connect capabilities for APIs and SSO use cases.

## Table of contents
- Overview
- Standards and protocols
- Architecture
- Modules
- API surface
- Project layout
- Configuration
- Operations
- Security
- Requirements
- Getting started
- Development
- Code quality
- Testing

## Overview

Fireguard Auth Server is a modular auth stack designed around clear module boundaries and hexagonal architecture.
Core capabilities include:
- OAuth 2.0 and OpenID Connect provider (authorization code with PKCE, client credentials, refresh token).
- Token issuance, introspection, revocation, refresh, and discovery metadata.
- User authentication with MFA and OTP (email, SMS, TOTP).
- Session and trusted device management.
- Multi-tenant support and RBAC (roles and permissions).
- API Platform integration with rate limiting.

## Standards and protocols

- OAuth 2.0 (RFC 6749)
- OAuth 2.0 Token Revocation (RFC 7009)
- OAuth 2.0 Token Introspection (RFC 7662)
- OAuth 2.0 Authorization Server Metadata (RFC 8414)
- OpenID Connect Core 1.0

## Architecture

The codebase follows a hexagonal architecture (ports and adapters) and enforces one-way dependencies across layers.
See the architecture standard in `ARCHITECTURE.md`.

## Modules

| Module | Purpose | Documentation |
| --- | --- | --- |
| Auth | Authentication flows, MFA, refresh tokens | `src/Auth/MODULE.md` |
| OAuth | OAuth2 and OpenID Connect provider, consent, discovery | `src/OAuth/MODULE.md` |
| Authorization | RBAC roles and permissions | `src/Authorization/MODULE.md` |
| User | User management and profiles | `src/User/MODULE.md` |
| Tenant | Multi-tenant management | `src/Tenant/MODULE.md` |
| Session | Session tracking and revocation | `src/Session/MODULE.md` |
| TrustedDevice | Trusted device management and cookies | `src/TrustedDevice/MODULE.md` |
| Otp | OTP and TOTP challenges | `src/Otp/MODULE.md` |
| Shared | Shared contracts, ports, value objects | `src/Shared/MODULE.md` |

Each module document contains its endpoints, flows, configuration, and testing notes.

## API surface

At a high level, the API is organized around the following groups:
- Auth endpoints under `/api/auth` (login, MFA verify, refresh, logout).
- OAuth endpoints under `/api/oauth2` (authorize, token, introspect, revoke, userinfo, end session).
- Discovery endpoints under `/api/.well-known/openid-configuration` and `/api/.well-known/jwks.json`.
- Admin and resource APIs for users, tenants, sessions, trusted devices, roles, and permissions (see module docs).

For exact routes, request/response payloads, and error codes, use the module documentation.

## Project layout

```
config/
  modules/
  packages/
migrations/
src/
  <Module>/
    Application/
    Domain/
    Infrastructure/
    Presentation/
tests/
```

## Configuration

Configuration is driven by environment variables in `.env` and overrides in `.env.local`:
- `APP_ENV`, `APP_SECRET`
- `DATABASE_URL`
- `OAUTH_ISSUER`, `OAUTH_ENCRYPTION_KEY`
- `ACCESS_TOKEN_TTL`, `TOKEN_CACHE_TTL`
- `REFRESH_TOKEN_COOKIE_NAME`, `REFRESH_TOKEN_LIFETIME_SHORT`, `REFRESH_TOKEN_LIFETIME_LONG`
- `TRUSTED_DEVICE_COOKIE_NAME`, `TRUSTED_DEVICE_LIFETIME`
- `MFA_ENABLED`, `MAILER_DSN`, `MAILER_FROM`, `SMS_DSN`
- `CORS_ALLOW_ORIGIN`, `DEFAULT_URI`

JWT keys are expected at:
- `config/jwt/private.key`
- `config/jwt/public.key`

Module wiring is in `config/modules/*.yaml`, with shared framework configuration in `config/packages/*.yaml`.

## Operations

Production and staging runbooks are documented in `OPERATIONS.md`.

## Security

Security-sensitive configuration and guidance is documented in `SECURITY.md`.

## Requirements

- PHP 8.4+
- Composer
- Database (PostgreSQL by default, configurable)
- Docker and Docker Compose (for SonarQube only)
- Make (recommended)

## Getting started

1. Install dependencies:
   ```bash
   composer install
   ```
2. Copy environment defaults and adjust as needed:
   ```bash
   cp .env .env.local
   ```
3. Configure the database and run migrations:
   ```bash
   php bin/console doctrine:migrations:migrate
   ```
4. Start the application using your preferred Symfony runtime:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

## Development

Common Make targets:
- `make test` runs CS checks, static analysis, architecture rules, linting, and unit tests.
- `make phpunit` runs the test suite.
- `make phpstan` runs static analysis.
- `make deptrac` runs dependency rules.
- `make lint` validates Symfony container and YAML files.
- `make cs-lint` and `make cs-fix` manage coding style.
- `make cache-clear` clears and warms up the Symfony cache.

## Code quality

SonarQube support is wired via Docker Compose:
```bash
make sonar-up
make sonar-scan
```

## Testing

Tests are organized by type and layer under `tests/`. The full suite can be run via:
```bash
make phpunit
```
