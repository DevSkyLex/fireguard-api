# Skills Index

This folder contains project-specific Copilot skills for the Fireguard Symfony backend.

## Recommended Entry Points

- [add-use-case](./add-use-case/SKILL.md): add a new command or query in an existing module, plus the related processor or provider and tests.
- [api-platform-resource](./api-platform-resource/SKILL.md): add or update an API Platform resource, operation, DTO, processor, provider, pagination, or OpenAPI contract.
- [module-tests](./module-tests/SKILL.md): add focused unit, integration, or functional coverage for a changed behavior.
- [new-module](./new-module/SKILL.md): scaffold a brand new module with wiring, persistence, presentation, documentation, and baseline tests.
- [security-sensitive-change](./security-sensitive-change/SKILL.md): review or implement risky changes in auth, OAuth, sessions, authorization, OTP, trusted device, audit, or security config.

## Combination Guide

- Use [new-module](./new-module/SKILL.md) with [add-use-case](./add-use-case/SKILL.md) when the new module needs its first command and query flows.
- Use [add-use-case](./add-use-case/SKILL.md) with [api-platform-resource](./api-platform-resource/SKILL.md) when a use case is exposed through HTTP.
- Use [module-tests](./module-tests/SKILL.md) with any implementation skill whenever behavior changes need regression coverage.
- Use [security-sensitive-change](./security-sensitive-change/SKILL.md) alongside the implementation skill whenever the change touches Auth, OAuth, Session, Otp, TrustedDevice, Authorization, Audit, or security-related config.

## Typical Sequences

### New business module

1. [new-module](./new-module/SKILL.md)
2. [add-use-case](./add-use-case/SKILL.md)
3. [api-platform-resource](./api-platform-resource/SKILL.md)
4. [module-tests](./module-tests/SKILL.md)

### New API operation in an existing module

1. [add-use-case](./add-use-case/SKILL.md)
2. [api-platform-resource](./api-platform-resource/SKILL.md)
3. [module-tests](./module-tests/SKILL.md)

### Security-sensitive endpoint change

1. [security-sensitive-change](./security-sensitive-change/SKILL.md)
2. [add-use-case](./add-use-case/SKILL.md) or [api-platform-resource](./api-platform-resource/SKILL.md)
3. [module-tests](./module-tests/SKILL.md)

## Companion Prompts

- [README.md](../prompts/README.md): index of the available workspace prompts and when to use them.
- [new-api-flow.prompt.md](../prompts/new-api-flow.prompt.md): run the common existing-module sequence for a new API-facing behavior.
- [new-module-stack.prompt.md](../prompts/new-module-stack.prompt.md): scaffold a new module with its first flows and baseline coverage.
- [security-endpoint-change.prompt.md](../prompts/security-endpoint-change.prompt.md): implement or review a risky auth or security-facing endpoint change.
- [choose-review-agent.prompt.md](../prompts/choose-review-agent.prompt.md): route a review request to the most appropriate project-specific agent.
- [full-review.prompt.md](../prompts/full-review.prompt.md): compose a multi-angle review plan across backend, security, API contract, tests, and workflows.

## Companion Agents

- [backend-review.agent.md](../agents/backend-review.agent.md): review backend diffs for architecture, isolation, persistence, API contract, and missing-test issues.
- [security-review.agent.md](../agents/security-review.agent.md): review sensitive changes with a fail-closed security lens.
- [module-explorer.agent.md](../agents/module-explorer.agent.md): map a module before implementing a new change.
- [api-contract-review.agent.md](../agents/api-contract-review.agent.md): review API metadata, DTOs, status codes, pagination, filters, and exception mapping.
- [tests-review.agent.md](../agents/tests-review.agent.md): review whether the chosen tests actually protect the changed behavior.
- [workflow-review.agent.md](../agents/workflow-review.agent.md): review GitHub workflow triggers, permissions, secrets, caches, and CI orchestration.
