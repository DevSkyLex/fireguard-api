# Context

- Issue / ticket:
- Goal:
- Out of scope:

## Change Summary

- TODO
- TODO

## Review Guide

### Modules / Areas

<!-- Example: Auth, OAuth, Organization, Inspection, Shared, .github -->
- TODO

### Main Files / Entry Points

<!-- Point reviewers to the exact handlers, processors, repositories, resources, configs, migrations, or tests that matter. -->
- TODO

### Reviewer Focus

Select only what really matters for this PR.

- [ ] Business logic correctness
- [ ] Authorization / permissions
- [ ] Tenant / organization isolation
- [ ] API contract / serialization
- [ ] Persistence / migrations / Doctrine mapping
- [ ] Architecture / layer boundaries
- [ ] Query / collection performance
- [ ] CI / workflow / release impact
- [ ] Regression risk
- [ ] Missing tests

## Functional Impact

- [ ] No functional impact
- [ ] User-visible behavior changed
- [ ] Public API or response shape changed
- [ ] Database schema or migration impact
- [ ] Config / environment impact
- [ ] CI / delivery impact
- [ ] Breaking change

### Before

<!-- Optional: short description of the previous behavior. -->
Needs manual confirmation.

### After

<!-- Optional: short description of the expected new behavior. -->
Needs manual confirmation.

## Risk And Rollback

- [ ] No special risk
- [ ] Security-sensitive change
- [ ] Auth / OAuth / JWT / session / MFA flow changed
- [ ] Cross-tenant or cross-organization scope involved
- [ ] Persistence / transaction / data lifecycle changed
- [ ] Release or deployment process changed

### Risk Notes

<!-- Call out the exact edge cases or failure modes reviewers should validate. -->
- TODO

### Rollback Plan

<!-- How to revert safely if this goes wrong in production. -->
- TODO

## Validation

### Local Validation

<!-- Only list what you actually ran locally. Example: targeted phpunit suite, manual API check, smoke test. -->

- TODO
- TODO

### CI Validation

<!-- GitHub Actions is the source of truth for automated validation on this PR. -->

- [ ] I expect the standard PR checks to cover this change
- [ ] This PR needs an extra manual verification outside standard CI

### Additional Evidence

<!-- Optional: screenshots, API payloads, SQL diff, benchmark notes, logs. -->

## Deployment Notes

- [ ] No special deployment step
- [ ] Migration order matters
- [ ] New env var / secret required
- [ ] Manual post-deploy check required

### Details

<!-- Add exact deployment or verification steps if needed. -->

## Known Gaps

<!-- Anything intentionally deferred or not covered in this PR. -->
- TODO

## Copilot Review Request

```text
Review this pull request using the repository Copilot instructions.
Focus only on actionable findings in the changed code.
Prioritize correctness, security, tenant and organization isolation, architecture violations, API contract regressions, persistence risks, workflow impact, and missing tests.
Ignore formatting and non-essential refactoring advice.
```
