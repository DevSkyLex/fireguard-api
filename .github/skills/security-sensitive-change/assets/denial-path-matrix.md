# Denial-Path Matrix

| Case | Expected behavior | Covered by test |
| --- | --- | --- |
| Unauthenticated request | `401` or `403` according to existing contract | `__TEST_NAME__` |
| Missing permission | `403` | `__TEST_NAME__` |
| Wrong tenant or organization | deny or not found according to existing contract | `__TEST_NAME__` |
| Invalid credential, OTP, token, or redirect URI | explicit denial | `__TEST_NAME__` |
| Rate limit exceeded | `429` with retry semantics when applicable | `__TEST_NAME__` |
