---
applyTo: "src/Auth/**/*.php,src/OAuth/**/*.php,src/Session/**/*.php,src/Otp/**/*.php,src/TrustedDevice/**/*.php,src/Authorization/**/*.php,src/Audit/**/*.php,config/packages/security.yaml,config/packages/rate_limiter.yaml,config/packages/test/rate_limiter.yaml,SECURITY.md"
---

These paths are security-sensitive.

Review and generate changes defensively:

- preserve strict permission checks
- preserve tenant and organization scoping
- preserve redirect URI, scope, token, cookie, session, MFA, and trusted-device validation
- never weaken JWT, refresh-token, revocation, or audit behavior without an explicit reason
- avoid logging or returning secrets, raw tokens, OTPs, credentials, or unnecessary PII

Prefer fail-closed behavior over fail-open behavior.

If a change affects auth or OAuth flows, verify both the happy path and the denial or failure path.

If a change affects audit or security events, ensure the event still captures the necessary information without leaking sensitive data.
