# Agents Index

This folder contains project-specific Copilot agents for the Fireguard Symfony backend.

## Recommended Agents

- [backend-review.agent.md](./backend-review.agent.md): review backend changes for architecture, scoping, persistence risks, API contract regressions, and missing tests.
- [security-review.agent.md](./security-review.agent.md): review Auth, OAuth, Session, Otp, TrustedDevice, Authorization, Audit, cookies, tokens, denial paths, and fail-closed behavior.
- [module-explorer.agent.md](./module-explorer.agent.md): map an existing module before implementation by identifying use cases, DTOs, processors, providers, repositories, tests, and config.
- [api-contract-review.agent.md](./api-contract-review.agent.md): review API Platform resources and HTTP contracts for metadata drift, status code mismatches, pagination and filter regressions, and exception mapping issues.
- [tests-review.agent.md](./tests-review.agent.md): review whether changed behavior has the right unit, integration, or functional coverage.
- [workflow-review.agent.md](./workflow-review.agent.md): review GitHub workflow changes for trigger, permission, secret, cache, and CI orchestration problems.

## When To Prefer An Agent

- use an agent when you want a specialized read-only persona with isolated context and a tightly scoped output
- use a prompt when you want to orchestrate a common implementation workflow
- use a skill when you want domain-specific guidance and assets during a broader implementation task
- use [.github/AGENTS.md](../AGENTS.md) when you need the repository-level guide for choosing between skills, prompts, and agents
- use [prompts/README.md](../prompts/README.md) when you want the prompt-level entry points rather than the agent catalog

## Typical Usage

### Review a backend change

1. Pick [backend-review.agent.md](./backend-review.agent.md)
2. Ask it to review the current diff or a specific module change

### Review a security-sensitive change

1. Pick [security-review.agent.md](./security-review.agent.md)
2. Point it at the target module, endpoint, or diff

### Explore a module before changing it

1. Pick [module-explorer.agent.md](./module-explorer.agent.md)
2. Ask it to summarize flows, boundaries, and likely anchor files

### Review an API contract change

1. Pick [api-contract-review.agent.md](./api-contract-review.agent.md)
2. Point it at the resource, endpoint, or diff affecting the public API contract

### Review test coverage quality

1. Pick [tests-review.agent.md](./tests-review.agent.md)
2. Ask it whether the current diff is under-tested and at which level

### Review a workflow or CI change

1. Pick [workflow-review.agent.md](./workflow-review.agent.md)
2. Point it at the workflow diff, trigger change, or failing CI behavior under review
