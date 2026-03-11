---
applyTo: "tests/**/*.php"
---

Tests in this repository should validate behavior, not just implementation details.

When adding or editing tests:

- cover both success and failure paths
- cover permission failures and tenant or organization isolation when relevant
- use PHPUnit 12 attributes consistently
- keep tests deterministic and avoid unnecessary framework bootstrapping in unit tests
- mirror module structure and naming so the intent of the test is obvious

For bug fixes, add a regression assertion that would have failed before the fix.

For API-facing changes, verify serialization, filters, status codes, or mapped output when relevant.
