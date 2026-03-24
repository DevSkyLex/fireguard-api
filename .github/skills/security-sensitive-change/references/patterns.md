# Security Change Patterns

## Denial-first review

- enumerate unauthenticated, forbidden, wrong-scope, invalid-token, and rate-limited cases before editing code
- verify the denial path after every meaningful code change

## Data handling

- keep raw secrets, tokens, OTPs, and client secrets out of logs and API DTOs
- keep cookies aligned with the security guide
- keep PII sanitized where the current flow expects it

## Runtime guarantees

- preserve redirect URI and scope validation
- preserve revocation and refresh semantics
- preserve audit and security event emission

## HTTP contract checks

- keep unauthenticated and forbidden responses explicit
- keep throttling responses distinct when the current flow uses a rate limiter
- verify protected routes still exist behind authentication and do not regress to `404`
