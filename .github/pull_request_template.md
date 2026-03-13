# Context

<!-- AI-managed sections below may be auto-filled and refreshed by workflow automation. -->
<!-- Add a standalone HTML comment named pr-ai:disable anywhere in this body to opt out. -->

- Issue / ticket:
- Goal:
- Out of scope:

## Change Summary

<!-- pr-ai:change-summary:start -->
- TODO
- TODO
<!-- pr-ai:change-summary:end -->

## Review Guide

### Modules / Areas

<!-- Example: Auth, OAuth, Organization, Inspection, Shared, .github -->
<!-- pr-ai:modules-areas:start -->
- TODO
<!-- pr-ai:modules-areas:end -->

### Main Files / Entry Points

<!-- Point reviewers to the exact handlers, processors, repositories, resources, configs, migrations, or tests that matter. -->
<!-- pr-ai:main-files:start -->
- TODO
<!-- pr-ai:main-files:end -->

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
<!-- pr-ai:before:start -->
Needs manual confirmation.
<!-- pr-ai:before:end -->

### After

<!-- Optional: short description of the expected new behavior. -->
<!-- pr-ai:after:start -->
Needs manual confirmation.
<!-- pr-ai:after:end -->

## Risk And Rollback

- [ ] No special risk
- [ ] Security-sensitive change
- [ ] Auth / OAuth / JWT / session / MFA flow changed
- [ ] Cross-tenant or cross-organization scope involved
- [ ] Persistence / transaction / data lifecycle changed
- [ ] Release or deployment process changed

### Risk Notes

<!-- Call out the exact edge cases or failure modes reviewers should validate. -->
<!-- pr-ai:risk-notes:start -->
- TODO
<!-- pr-ai:risk-notes:end -->

### Rollback Plan

<!-- How to revert safely if this goes wrong in production. -->
<!-- pr-ai:rollback-plan:start -->
- TODO
<!-- pr-ai:rollback-plan:end -->

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
<!-- pr-ai:known-gaps:start -->
- TODO
<!-- pr-ai:known-gaps:end -->

## Copilot Review Request

```text
Review this pull request using the repository Copilot instructions.
Focus only on actionable findings in the changed code.
Prioritize correctness, security, tenant and organization isolation, architecture violations, API contract regressions, persistence risks, workflow impact, and missing tests.
Ignore formatting and non-essential refactoring advice.
```
