# Agents Guide

This repository provides three complementary customization layers:

- `skills` for implementation guidance and reusable assets
- `prompts` for quick workflow orchestration
- `agents` for focused review or exploration with isolated context
- `hooks` for deterministic policy enforcement and runtime safety checks

## When To Use What

Use a skill when you are implementing or changing code and need repo-specific guidance.

Use a prompt when you already know the type of task and want a reusable shortcut that loads the right skills or routes to the right review mode.

Use an agent when you want a specialized read-only reviewer or explorer with a narrow responsibility and a strict output shape.

Use a hook when the behavior must be guaranteed at runtime, for example to block destructive commands or require confirmation for sensitive files.

For future customization work, follow [.github/instructions/customizations.instructions.md](./instructions/customizations.instructions.md).

## Skills

- [add-use-case](./skills/add-use-case/SKILL.md): new command or query flows
- [api-platform-resource](./skills/api-platform-resource/SKILL.md): API Platform resources, DTOs, processors, providers, and OpenAPI contract work
- [module-tests](./skills/module-tests/SKILL.md): unit, integration, and functional test work
- [new-module](./skills/new-module/SKILL.md): new module scaffolding and first flows
- [security-sensitive-change](./skills/security-sensitive-change/SKILL.md): risky auth or security-sensitive implementation work

## Prompts

- [README.md](./prompts/README.md): index of the available workspace prompts and when to use them
- [new-api-flow.prompt.md](./prompts/new-api-flow.prompt.md): implement a new API-facing flow in an existing module
- [new-module-stack.prompt.md](./prompts/new-module-stack.prompt.md): scaffold a new module with representative flows
- [security-endpoint-change.prompt.md](./prompts/security-endpoint-change.prompt.md): implement or review a sensitive endpoint change
- [choose-review-agent.prompt.md](./prompts/choose-review-agent.prompt.md): route a review request to the best specialized agent
- [full-review.prompt.md](./prompts/full-review.prompt.md): run a broad review by combining the most relevant specialized reviewers

## Hooks

- [README.md](./hooks/README.md): index of workspace hooks and how to test them
- [policy.json](./hooks/policy.json): blocks destructive commands, asks before sensitive edits, and injects concise session context

## Agents

- [backend-review.agent.md](./agents/backend-review.agent.md): architecture, scoping, persistence, API regressions, missing tests
- [security-review.agent.md](./agents/security-review.agent.md): fail-closed security review for sensitive modules and flows
- [api-contract-review.agent.md](./agents/api-contract-review.agent.md): HTTP contract and API Platform metadata review
- [tests-review.agent.md](./agents/tests-review.agent.md): regression coverage quality review
- [workflow-review.agent.md](./agents/workflow-review.agent.md): GitHub workflow and CI review
- [module-explorer.agent.md](./agents/module-explorer.agent.md): read-only module mapping before implementation

## Recommended Review Paths

### Backend feature change

1. Use [choose-review-agent.prompt.md](./prompts/choose-review-agent.prompt.md) if the review angle is unclear.
2. Use [backend-review.agent.md](./agents/backend-review.agent.md) for the first pass.
3. Add [api-contract-review.agent.md](./agents/api-contract-review.agent.md) if HTTP behavior changed.
4. Add [tests-review.agent.md](./agents/tests-review.agent.md) if regression risk is non-trivial.

### Security-sensitive change

1. Use [security-review.agent.md](./agents/security-review.agent.md) first.
2. Add [api-contract-review.agent.md](./agents/api-contract-review.agent.md) if the public API contract changed.
3. Add [tests-review.agent.md](./agents/tests-review.agent.md) to confirm fail-closed coverage.

### CI or workflow change

1. Use [workflow-review.agent.md](./agents/workflow-review.agent.md).
2. Add [tests-review.agent.md](./agents/tests-review.agent.md) if the workflow change alters which checks protect the repo.

### Before implementing a change in an unfamiliar module

1. Use [module-explorer.agent.md](./agents/module-explorer.agent.md).
2. Then switch to the relevant skill or prompt for implementation.

## Practical Rule

- If you want to build: start with a skill or implementation prompt.
- If you want to inspect: start with an agent.
- If you do not know which reviewer fits: start with [choose-review-agent.prompt.md](./prompts/choose-review-agent.prompt.md).
