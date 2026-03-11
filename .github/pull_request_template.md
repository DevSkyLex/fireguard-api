# Summary

<!-- What changed in a few lines. -->

## Why

<!-- Why this PR exists. -->

## Scope

### Modules

<!-- Example: Auth, OAuth, Equipment, Inspection, Shared -->

### Main Files Or Flows To Review

<!-- Point Copilot and reviewers to the exact handlers, providers, processors, repositories, resources, configs, or tests that matter. -->

## What Copilot Should Check

Select the areas that matter for this PR.

- [ ] Business logic correctness
- [ ] Authorization / permissions
- [ ] Tenant or organization isolation
- [ ] API contract / serialization changes
- [ ] Database or migration impact
- [ ] Architecture / layer boundaries
- [ ] Performance on collection queries
- [ ] Regression risk
- [ ] Missing tests

## Risk Notes

Call out anything that could easily break.

- [ ] No special risk
- [ ] Security-sensitive change
- [ ] Auth / OAuth / JWT / session / MFA flow changed
- [ ] Cross-tenant or cross-organization scope involved
- [ ] Data model or persistence logic changed
- [ ] Public endpoint or response shape changed

### Details

<!-- Add the exact risk reviewers should validate. -->

## Expected Behavior

### Before

<!-- Optional: short description of the previous behavior. -->

### After

<!-- Optional: short description of the expected new behavior. -->

## Tests

List what you actually ran.

| Command | Result |
| --- | --- |
| `make cs-lint` | <!-- pass / not run / n.a. --> |
| `make phpstan` | <!-- pass / not run / n.a. --> |
| `make deptrac` | <!-- pass / not run / n.a. --> |
| `make lint` | <!-- pass / not run / n.a. --> |
| `make phpunit-fast` | <!-- pass / not run / n.a. --> |
| `make phpunit` | <!-- pass / not run / n.a. --> |
| `make mutation` | <!-- pass / not run / n.a. --> |

### Test Notes

<!-- Mention targeted tests, edge cases covered, or why tests were skipped. -->

## Known Gaps

<!-- Anything intentionally deferred or not covered in this PR. -->

## Copilot Review Request

```text
Review this pull request using the repository Copilot instructions.
Focus only on actionable findings in the changed code.
Prioritize correctness, security, tenant and organization isolation, architecture violations, API contract regressions, persistence risks, and missing tests.
Ignore formatting and non-essential refactoring advice.
```
