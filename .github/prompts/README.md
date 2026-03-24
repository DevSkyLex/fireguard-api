# Prompts Index

This folder contains project-specific Copilot prompts for the Fireguard Symfony backend.

## Implementation Prompts

- [new-api-flow.prompt.md](./new-api-flow.prompt.md): implement a new API-facing flow in an existing module by loading the matching use-case, API Platform, and test skills.
- [new-module-stack.prompt.md](./new-module-stack.prompt.md): scaffold a new module with wiring, representative flows, and baseline tests.
- [security-endpoint-change.prompt.md](./security-endpoint-change.prompt.md): implement or review a security-sensitive endpoint change with the right security and test guidance.

## Review Prompts

- [choose-review-agent.prompt.md](./choose-review-agent.prompt.md): route a review request to the best specialized agent.
- [full-review.prompt.md](./full-review.prompt.md): compose a broad multi-angle review plan across backend, security, API contract, tests, and workflows.

## When To Prefer A Prompt

- use a prompt when the task shape is already known and you want a reusable shortcut
- use a prompt when you want to load the right skills without rewriting the same instructions each time
- use a prompt when you want to route a review request toward the best specialized reviewer

## When Not To Prefer A Prompt

- use a skill instead when the main need is implementation guidance plus reusable assets
- use an agent instead when the main need is a narrow read-only reviewer or module exploration persona

## Suggested Starting Points

### Build a new API feature

1. [new-api-flow.prompt.md](./new-api-flow.prompt.md)

### Scaffold a new module

1. [new-module-stack.prompt.md](./new-module-stack.prompt.md)

### Review a risky change

1. [choose-review-agent.prompt.md](./choose-review-agent.prompt.md)
2. [full-review.prompt.md](./full-review.prompt.md) if the change spans multiple risk areas
