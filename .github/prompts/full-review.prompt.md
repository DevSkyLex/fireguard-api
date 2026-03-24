---
name: "Full Review"
description: "Run a broad review by selecting the right specialized review agents for backend, security, API contract, tests, workflows, or module exploration."
argument-hint: "Describe the diff, module, PR, or area to review"
agent: "agent"
---

Run a broad but focused review of the requested change.

Before responding:

1. Identify the dominant risk areas in the request.
2. Use [choose-review-agent.prompt.md](./choose-review-agent.prompt.md) logic to determine which specialized review agents apply.
3. Start with the highest-risk review angle first.
4. If the change clearly spans multiple risk areas, combine up to three review agents in this order:
   - backend architecture and scoping
   - security and fail-closed behavior
   - API contract drift
   - test coverage gaps
   - workflow or CI regressions
5. Do not include a specialized review angle that is not relevant to the described change.

Return:

1. the selected review plan
2. which agent should run first
3. which additional agents should run afterward, if any
4. one tailored prompt per selected agent
5. a short rationale for the order

If the user request is too vague, ask for the smallest missing detail needed to choose the right review path.
