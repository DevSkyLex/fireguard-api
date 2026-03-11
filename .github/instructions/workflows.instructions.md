---
applyTo: ".github/workflows/**/*.yml"
---

Keep GitHub Actions workflows conservative and readable.

When changing workflows:

- preserve the existing branch strategy around `main` and `develop` unless explicitly changing release policy
- prefer least-privilege `permissions`
- keep CI checks aligned with the actual repository commands in `Makefile` and current tooling in `composer.json`
- avoid adding steps that require secrets unless strictly necessary
- prefer official actions and stable major versions

For security-related workflows, fail clearly and avoid noisy or duplicate checks.
