---
name: security-sensitive-change
description: 'Review or implement a security-sensitive change in this Symfony backend. Use for Auth, OAuth, Session, Otp, TrustedDevice, Authorization, Audit, security.yaml, rate_limiter.yaml, token and cookie flows, permission checks, tenant or organization isolation, secret handling, denial paths, and regression tests for fail-closed behavior.'
argument-hint: 'Area + change, for example: OAuth token issuance change or Session logout hardening'
---

# Security Sensitive Change

## When to Use

- Modify code in `Auth`, `OAuth`, `Session`, `Otp`, `TrustedDevice`, `Authorization`, or `Audit`
- Change permission checks, auth gates, token issuance, refresh, revocation, MFA, trusted-device flows, or security event handling
- Change `config/packages/security.yaml`, `config/packages/rate_limiter.yaml`, `config/packages/test/rate_limiter.yaml`, or security-related operational behavior
- Review a pull request that could weaken isolation, secret handling, or denial behavior

## Required Inputs

Before editing code, identify:

- exact module and flow being changed
- expected happy path
- all denial or failure paths that must remain intact
- scope boundary involved: `tenantId`, `organizationId`, user ownership, client ownership, or system-only access
- sensitive artifacts involved: passwords, tokens, OTPs, cookies, redirect URIs, client secrets, audit payloads, PII
- expected HTTP or API outcome for both success and failure

If any of these are unclear, inspect the current processor, provider, handler, DTOs, domain events, tests, and `SECURITY.md` before changing anything.

## Procedure

## Assets

Use these checklists and matrices when the change is risky or review-heavy:

- [Security review checklist](./assets/security-review-checklist.md)
- [Denial-path matrix](./assets/denial-path-matrix.md)
- [Sensitive-data checklist](./assets/sensitive-data-checklist.md)
- [Config review checklist](./assets/config-review-checklist.md)
- [Reference notes](./references/patterns.md)

### 1. Classify the Security Surface

Determine which category the change touches:

- authentication or session establishment
- OAuth or OIDC token issuance, refresh, revocation, introspection, or redirect validation
- MFA or OTP challenge and verification
- trusted-device issuance or bypass behavior
- authorization and permission enforcement
- audit logging or security event emission
- security configuration such as cookies, rate limiting, security headers, or transport assumptions

This determines which checks must be preserved and which denial paths must be tested.

### 2. Trace the Full Flow Before Editing

Read the full chain for the target behavior:

- request entry point in Presentation
- command or query handler in Application
- domain methods or exceptions
- persistence or adapters in Infrastructure
- emitted events, logs, or audit records
- existing unit, functional, or integration tests

Do not patch only the visible symptom. Security regressions often come from changing one layer and forgetting the denial or audit behavior in another.

### 3. Preserve Thin Presentation, Strong Handler Enforcement

Presentation code should:

- parse and validate request or route inputs
- obtain the authenticated user when required
- enforce coarse permission gates before dispatch
- map domain or validation failures to HTTP exceptions
- never contain business rules or ownership logic that handlers rely on for safety

Application handlers should:

- enforce scoping and ownership checks needed for correctness
- validate invariants and fail closed
- consume rate limits early when relevant
- call ports, not infrastructure classes directly
- return explicit result objects or throw clear domain exceptions

If a check only exists in a processor or provider but the handler can be reused elsewhere, that check is too shallow.

### 4. Enumerate and Preserve Denial Paths

For the target flow, write down the denial and failure paths before changing code.

Typical cases in this repo:

- unauthenticated request
- missing permission
- wrong tenant or organization scope
- wrong owner or client
- invalid credentials or invalid OTP
- invalid redirect URI or disallowed scope
- revoked, expired, or malformed token
- rate limit exceeded
- immutable or read-only security resource modified
- audit read attempted without `audit.read`

Prefer fail-closed behavior. If a branch is ambiguous, deny rather than allow.

### 5. Protect Secrets and Sensitive Data

Never log, return, or expose unnecessarily:

- raw passwords
- raw access or refresh tokens
- raw OTP values or MFA secrets
- raw client secrets
- session identifiers beyond what the existing API intentionally exposes
- unnecessary PII in logs, errors, or audit payloads

Follow repo conventions:

- refresh tokens belong in `HttpOnly` cookies rather than JSON bodies when the existing flow does so
- PII in security logs should stay sanitized or hashed by default
- audit and security events must contain enough information for investigation without leaking secrets

### 6. Preserve Token, Cookie, and Redirect Guarantees

When touching auth or OAuth flows, verify:

- redirect URI validation remains strict and fail-closed
- requested scopes remain constrained to allowed scopes
- token refresh and revocation semantics do not weaken
- cookie attributes remain aligned with the repo security guide, including `HttpOnly`, `SameSite=Strict`, and `Secure` in production when applicable
- trusted-device or MFA bypass rules are not widened accidentally

Do not weaken these guarantees to simplify implementation or tests.

### 7. Preserve Audit and Security Events

If the flow emits audit or security events, verify both success and failure behavior:

- important events are still emitted
- emitted payloads still contain the required identifiers or reason codes
- payloads do not leak tokens, OTPs, credentials, or unnecessary PII
- audit behavior stays tamper-evident and permission-protected

Silent removal of security telemetry is a regression even when the main feature still works.

### 8. Update Tests for Success and Refusal

For every security-sensitive change, add or update tests that cover:

- success path
- explicit denial or failure path

Also add targeted tests when relevant:

- 401 or 403 for unauthenticated or forbidden access
- 429 plus retry semantics for rate limits
- tenant or organization isolation
- revoked or expired token handling
- invalid redirect URI or scope rejection
- audit event visibility restrictions
- cookie or response shape expectations for auth endpoints

Keep tests behavior-focused. The point is to prove the flow still denies invalid access and still avoids leaking sensitive data.

### 9. Check Config and Operational Impact

If the change touches config or runtime behavior, verify:

- `security.yaml` changes do not widen access unintentionally
- `rate_limiter.yaml` changes do not weaken brute-force or abuse protection without explicit reason
- test overrides remain deterministic without masking real production behavior
- `SECURITY.md` still matches the implemented guarantees when public behavior changed

### 10. Run a Final Regression Review

Before considering the change safe, re-check:

- could this permit access that was previously denied
- could this leak data previously hidden
- could this skip an audit or security event
- could this remove rate limiting, revocation, or validation guarantees
- could this weaken tenant or organization isolation
- could a reused handler now be called without the checks that used to protect it

If the answer might be yes, the change is not ready.

## Decision Points

### Where Should a Check Live

- keep request parsing and coarse permission gates in Presentation
- keep ownership, scope, lifecycle, and security invariants in handlers or domain logic
- never rely only on client-side behavior or route shape for security

### Error Result vs Exception

- use the pattern already established in the target flow
- preserve explicit error codes or reason fields when the current contract uses them
- do not replace a clear denial path with a generic runtime failure

### 401 vs 403 vs 404 vs 429

- keep status semantics aligned with the existing flow and tests
- unauthenticated and forbidden behavior must remain explicit
- rate-limit responses must remain distinct from invalid credentials when the existing flow distinguishes them

### Config Change vs Code Change

- use config for policy and limits
- use code only when behavior really changes
- when editing config, still test the resulting runtime behavior

## Completion Checklist

The change is complete only if all of the following are true:

- permission checks still exist and remain at least as strict as before
- tenant, organization, ownership, and client scoping were verified where relevant
- denial and failure paths were reviewed explicitly, not assumed
- secrets, tokens, OTPs, cookies, and PII are not newly exposed in logs, errors, or DTOs
- redirect URI, scope, token, cookie, session, MFA, and trusted-device guarantees remain intact when relevant
- audit or security events still emit the required information without leaking sensitive data
- tests cover success and at least one meaningful denial or failure path
- any config change was validated for runtime impact

## Project-Specific Guardrails

- Follow `.github/instructions/security.instructions.md` as the minimum defensive baseline
- Follow `.github/instructions/backend.instructions.md` to keep business rules in handlers rather than processors or providers
- Follow `.github/instructions/tests.instructions.md` when adding regression coverage
- Follow `SECURITY.md` for cookies, token handling, redirect validation, logging, audit, and operational hardening expectations

## Combine With

- Use `add-use-case` when the risky change is primarily a command or query implementation.
- Use `api-platform-resource` when the security-sensitive behavior is exposed through API Platform processors, providers, DTOs, or OpenAPI contracts.
- Use `module-tests` to add explicit success, denial, and fail-closed regression coverage.
- Use `new-module` when the work is introducing an auth-adjacent module or a new security-owned bounded context.

## Example Prompts

- `/security-sensitive-change OAuth token refresh denial path review`
- `/security-sensitive-change Session logout hardening with revocation checks`
- `/security-sensitive-change Auth login processor and handler review for rate limit and audit behavior`
- `/security-sensitive-change Authorization permission enforcement change for organization-scoped resource`
