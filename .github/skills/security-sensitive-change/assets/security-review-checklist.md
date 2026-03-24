# Security Review Checklist

- Flow and affected module identified
- Happy path still works
- Denial paths are explicit and still fail closed
- Tenant, organization, ownership, and client scope verified
- No secret, token, OTP, cookie, or PII leakage introduced
- Audit and security events still emitted correctly
- Tests cover one success and one denial path at minimum
