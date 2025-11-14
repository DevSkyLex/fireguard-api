---
trigger: model_decision
description: Triggered when test scaffolding or quality enforcement is requested.
---

# Tests & Quality
**Activation Mode:** Model Decision

## Pyramid
- Unit tests for Domain and Application Handlers (fast, pure).
- Integration tests for Repositories and Adapters.
- E2E tests for API Platform operations (minimal happy paths).

## Fixtures
- Build aggregates via factories, not ORMs.
- Use test doubles for Ports (mocks/stubs), not for Domain.

## Static Analysis
- PHPStan level max; baseline for legacy only.
- Psalm optional; Rector for automated upgrades.
- Enforce no forbidden deps (deptrac) between layers.

## CI
- Lint (php-cs-fixer), phpstan, unit, integration, e2e, coverage threshold.
