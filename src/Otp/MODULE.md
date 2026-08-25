# OTP Module

## Overview

The OTP module provides challenge-based verification for MFA and other
secure flows. It exposes challenge endpoints only (no direct OTP CRUD
endpoints) and offers a clean inbound port for other modules.

All OTP endpoints are protected by RBAC permissions (`otp_*`) and are
intended for authenticated user flows or internal service usage.

## API Endpoints

| Method | Path | Description | Handler |
| --- | --- | --- | --- |
| POST | `/api/otp/challenges` | Create OTP challenge | `CreateChallengeProcessor` |
| GET | `/api/otp/challenges/{token}` | Get challenge status | `GetChallengeStatusProvider` |
| POST | `/api/otp/challenges/{token}/verify` | Verify challenge code | `VerifyOtpProcessor` |
| POST | `/api/otp/challenges/{token}/resend` | Resend challenge code | `ResendChallengeProcessor` |
| GET | `/api/otp/purposes` | List purposes | `ListPurposesProvider` |
| GET | `/api/otp/channels` | List channels | `ListChannelsProvider` |
| POST | `/api/otp/totp/setup` | Setup TOTP (generates a PENDING secret) | `SetupTotpProcessor` |
| POST | `/api/otp/totp/confirm` | Confirm TOTP (activates the PENDING secret) | `ConfirmTotpProcessor` |
| POST | `/api/otp/totp/disable` | Disable TOTP (requires a valid current code) | `DisableTotpProcessor` |

`/api/otp/purposes` and `/api/otp/channels` have **no first-party web consumer**
today. They are retained deliberately as public-API discovery affordances for
external integrators driving the OTP challenge flow, on the same reasoning that
keeps `/api/webhooks/event-types` (see `src/Webhook/MODULE.md`) — a client
composing a challenge needs to know which purposes and channels the server will
accept, and hard-coding them into every integration is what a reference catalog
exists to avoid.

This is a **recorded decision, not an oversight**: the check that surfaced it
counted zero consumers under `src/` in the web application, and the answer was
to write the reason down rather than to delete the endpoints.

## Flows

### Challenge (Email/SMS)

```mermaid
sequenceDiagram
  participant Client
  participant API
  participant App
  participant Domain

  Client->>API: POST /api/otp/challenges
  API->>App: GenerateOtpCommand
  App->>Domain: Otp::generate
  App-->>API: GenerateOtpResult
  API-->>Client: ChallengeOutput

  Client->>API: POST /api/otp/challenges/{token}/verify
  API->>App: VerifyOtpCommand
  App->>Domain: Otp::verify
  App-->>API: VerifyOtpResult
  API-->>Client: VerifyOtpOutput
```

### TOTP Setup / Confirm / Disable

```mermaid
sequenceDiagram
  participant Client
  participant API
  participant App
  participant Totp
  participant Repo as TotpEnrollmentRepositoryPort

  Client->>API: POST /api/otp/totp/setup
  API->>App: SetupTotpCommand
  App->>Totp: generateSecret / provisioningUri
  App->>Repo: save (PENDING secret)
  App-->>API: SetupTotpResult
  API-->>Client: SetupTotpOutput (secret + qrCodeUri)

  Client->>API: POST /api/otp/totp/confirm {code}
  API->>App: ConfirmTotpCommand
  App->>Repo: findByUserId / verify code against PENDING secret
  App->>Repo: save (PENDING -> ACTIVE)
  App-->>API: ConfirmTotpResult
  API-->>Client: ConfirmTotpOutput

  Client->>API: POST /api/otp/totp/disable {code}
  API->>App: DisableTotpCommand
  App->>Repo: findByUserId / verify code against ACTIVE secret
  App->>Repo: save (ACTIVE cleared)
  App-->>API: DisableTotpResult
  API-->>Client: DisableTotpOutput
```

TOTP enrollment state is stored server-side per user (`totp_enrollments`, auth
database) with an optional ACTIVE (confirmed) secret and an optional PENDING
(unconfirmed) secret, tracked independently. Re-calling setup only replaces
the PENDING secret and leaves any existing ACTIVE secret usable for login
until the new one is confirmed. The client never supplies the secret back to
the server for confirm/disable — only the current authenticator code.

## Architecture

- Hexagonal module with strict layer boundaries.
- Inbound ports: `Otp\Application\Port\Inbound\Challenge\OtpChallengePort`, `Otp\Application\Port\Inbound\Totp\TotpStatusPort` (used by the User module for `/api/me` and, via a small adapter, by the Auth module for login MFA channel selection).
- Outbound ports: `Otp\Application\Port\Outbound\Challenge\OtpRepositoryPort`, `Otp\Application\Port\Outbound\Challenge\OtpNotifierPort`, `Otp\Application\Port\Outbound\Totp\TotpServicePort`, `Otp\Application\Port\Outbound\Totp\TotpEnrollmentRepositoryPort`.
- Cross-module contracts are defined in `Otp\Application\Contract`.
- Cross-module adapter: `Otp\Infrastructure\Adapter\Auth\TotpEnrollmentCheckAdapter` implements `Auth\Application\Port\Outbound\Mfa\TotpEnrollmentCheckPort` (Auth-owned port), delegating to `TotpStatusPort`.
- `Otp\Application\UseCase\Command\Challenge\VerifyOtp\VerifyOtpHandler` checks the OTP's channel: for `totp`, the submitted code is verified against the user's ACTIVE TOTP secret (via `TotpEnrollmentRepositoryPort` + `TotpServicePort`) instead of the challenge's own random code; challenge-level expiry/attempt bookkeeping is unchanged (`Otp::verifyExternal()`).

## Configuration

- Services: `config/modules/otp.yaml`.
- Notification sender: `MAILER_FROM` (used by `OtpNotifierAdapter`).
- Email template: `templates/otp/email/code.html.twig` (rendered by Twig in `OtpNotifierAdapter`).
- OTP code length: `OTP_CODE_LENGTH` (applies to challenge OTPs like SMS/email).
- TOTP code length: `TOTP_DIGITS` (applies to authenticator app codes). Changing this will invalidate existing TOTP enrollments.
- TOTP confirmation max attempts: fixed at 5 (`SetupTotpHandler::DEFAULT_MAX_ATTEMPTS`), reset each time setup is called again.
- Rate limits (`config/packages/rate_limiter.yaml`): `otp_totp_confirm`, `otp_totp_disable` (5/minute per user, mirrors `otp_challenge_verify`).
- **Disable has a second, persistent brake.** The rate limiter throttles bursts
  but resets every minute, so on its own it left the disable endpoint an
  unbounded oracle: 7 200 guesses a day against a six-digit code. After
  `TotpEnrollment::MAX_DISABLE_ATTEMPTS` (5) wrong codes the enrollment freezes
  for `DISABLE_LOCK_DURATION` (15 minutes), stored on the row
  (`disable_attempts`, `disable_locked_until`) so it survives across requests
  and processes. That is ~480 attempts a day instead of 7 200.

  Two properties are deliberate and should not be "simplified" away:

  - **The freeze refuses the correct code too.** Letting a valid code through
    would leave the freeze no obstacle to the only caller it exists for — the
    one who eventually guesses right.
  - **The freeze is temporary, unlike `confirmPending()`'s permanent lock.**
    Confirmation guards a *pending* secret, and its lock is escaped by
    restarting enrollment. Disabling guards the *active* secret: a permanent
    lock would leave the user unable to turn TOTP off **and** unable to
    re-enroll around it — a dead end only support could open.

  The counter is separate from `attempts`, which belongs to confirmation. One
  shared counter would let a failed disable eat the enrollment's confirmation
  budget, and the two reset on different events.
- Permissions: `otp_totp.setup`, `otp_totp.confirm`, `otp_totp.disable` (see `Authorization\Infrastructure\Catalog\PermissionCatalog`); granted to the default `user` and `admin` roles.
- Secret storage: the base32 TOTP secret is stored as plain text in `totp_enrollments.active_secret` / `.pending_secret` — this mirrors how the existing `otps.code_hash`-adjacent `recipient` and other OTP module fields are stored (no column-level encryption elsewhere in this module or `Session`/`TrustedDevice`). Unlike email/SMS OTP codes (which are Argon2id-hashed via `OtpCode`, since only a one-way equality check is needed), a TOTP secret cannot be hashed because the server must recompute codes from it at verification time; this is standard for TOTP implementations (e.g. Google Authenticator servers). Treat the DB as sensitive and protect it at the infrastructure level (encryption at rest, restricted access) — see `SECURITY.md`.

## Testing

- Unit: `tests/Unit/Otp` (handlers, domain, adapters).
- Functional: `tests/Functional/Api/OtpTotpApiTest.php`.
- E2E: `tests/E2E/OtpChallengeFlowTest.php`, `tests/E2E/OtpConfigFlowTest.php`, `tests/E2E/TotpFlowTest.php`.

## Error Codes

- `404 Not Found`: challenge token not found; no pending TOTP setup for confirm; TOTP not enabled for disable.
- `422 Unprocessable Entity`: invalid/expired TOTP code on confirm or disable.
- `429 Too Many Requests`: resend cooldown not elapsed; TOTP confirm/disable rate limit exceeded; **TOTP disable frozen after 5 wrong codes** (`Retry-After` carries the remaining seconds).
- `400 Bad Request`: invalid input or unauthenticated user where required.
