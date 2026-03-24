---
name: "Choose Review Agent"
description: "Route a review request to the most appropriate project-specific review agent for backend, security, API contract, tests, workflows, or module exploration."
argument-hint: "Describe the change or paste the review request"
agent: "agent"
---

Choose the best project-specific review agent for the request.

Before responding:

1. Check whether the request is mainly about architecture, application flow, scoping, persistence, or broad backend regression risk. If yes, use [backend-review.agent.md](../agents/backend-review.agent.md).
2. Check whether the request is mainly about Auth, OAuth, Session, Otp, TrustedDevice, Authorization, Audit, tokens, cookies, denial paths, rate limits, or fail-closed behavior. If yes, use [security-review.agent.md](../agents/security-review.agent.md).
3. Check whether the request is mainly about API Platform resources, DTOs, status codes, filters, pagination, OpenAPI, serialization, or HTTP exception mapping. If yes, use [api-contract-review.agent.md](../agents/api-contract-review.agent.md).
4. Check whether the request is mainly about missing unit, integration, or functional coverage. If yes, use [tests-review.agent.md](../agents/tests-review.agent.md).
5. Check whether the request is mainly about GitHub Actions, CI jobs, workflow triggers, permissions, caches, or automation safety. If yes, use [workflow-review.agent.md](../agents/workflow-review.agent.md).
6. Check whether the request is exploratory and the user first needs a map of an existing module or flow. If yes, use [module-explorer.agent.md](../agents/module-explorer.agent.md).

If one agent is clearly dominant, use it.

If two concerns are tightly coupled, pick the higher-risk one first and explain which second agent should be used next.

Return:

1. the chosen agent
2. why it is the best fit in one short paragraph
3. an example review prompt tailored to the user request
4. if relevant, the next fallback agent to use after the first pass
