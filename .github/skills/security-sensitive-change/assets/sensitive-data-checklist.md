# Sensitive-Data Checklist

- No raw password returned or logged
- No raw access or refresh token returned or logged unexpectedly
- No raw OTP or MFA secret exposed
- No raw client secret exposed
- Cookie attributes remain correct when cookies are involved
- PII remains hashed or sanitized where the existing flow expects it
