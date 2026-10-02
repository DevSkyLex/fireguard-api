---
name: fg-api-plan
description: 'Prepare or revise an implementation-ready FireGuard api plan when the user asks to plan a change, clarify implementation decisions, or review an existing plan. Read-only; guides the principal agent without automatic delegation.'
---

# fg-api-plan

Resolve the checkout from this skill (three directories above this folder). Read AGENTS.md,
.codex/workflow.md and the matching .codex/rules.md entries. This workflow is read-only:
inspect sources and existing evidence; do not edit files, generate contracts, install packages,
prepare databases, run checks that write caches, or implement the proposal. Describe the
checks that implementation will need. Do not create a dedicated planning agent or launch
sub-agents automatically. Explicit user authorization governs any separate delegation.

## Ground the decisions

Start from the requested result and prior decisions. Inspect enough of the current implementation
to identify the owner, closest supported pattern and affected contracts. Distinguish verified
behavior, proposed changes, assumptions and unavailable evidence. Cite concrete repository
sources; do not invent existing behavior from a diagram or a previous plan.

Clarify decisions that materially change scope, privacy, security or public behavior and cannot
be inferred from the request. Continue independent read-only exploration while waiting.
Resolve routine implementation choices using repository patterns and the user's constraints.
Make important alternatives and tradeoffs concrete, then name the selected option. An unanswered
required choice remains open; elapsed time is not approval.

## Respect the owning contracts

Read ARCHITECTURE.md and each affected src/<Module>/MODULE.md; read SECURITY.md for
account security, authorization, secrets, tenant boundaries or audit. Trace the concrete
entry point, use case, ports, adapters, config/modules wiring and existing tests. Consult
composer.json and Makefile for actual versions and checks. Identify auth versus main ownership
from config/packages/doctrine.yaml: separate managers, histories and no database joins.

Place business decisions in handlers, invariants in Domain, external dependencies behind
Application ports, and cross-module contracts in Application/Port or Application/Contract.
Describe the API Platform resource/operation/DTO/provider or processor contract, validation,
permissions and denial behavior when HTTP changes. A schema change names the correct new
migration history; an endpoint change includes OpenAPI regeneration. Do not copy transitional
boundary breaks. Plan MODULE.md changes with changes to flows, errors or configuration.

Read fg-api-explore for unfamiliar modules, fg-api-security-review for sensitive flows,
fg-api-endpoint/fg-api-port/fg-api-usecase for the matching boundary, and fg-api-quality for
check selection only when relevant. Plan deterministic unit tests, PostgreSQL persistence
and denial paths; both Deptrac gates, explicit entity-manager wiring, container lint,
OpenAPI and both schema gates apply according to the affected scope.

## Make the plan ready to implement

Explain the intended result, chosen approach, affected boundaries, implementation order and
validation that demonstrates success. Include relevant failure, concurrency, privacy,
retention, operational and rollout considerations. Name concrete files or owning areas when
known, without turning the plan into an exhaustive file catalog. Separate optional bonuses
from necessary behavior, and record any activation prerequisite that depends on an operator.

Review an existing plan against current sources and retain accepted decisions unless new
evidence warrants a change. Revise the prose and diagrams together. State unresolved choices
and material validation limits rather than claiming readiness or observed runtime discovery.
AI-facing procedures belong in .agents or the appropriate tooling directory, outside docs;
human product, architecture and operating documentation retains its own legitimate location.

## Start with the reader's question

Choose the presentation that makes the subject easier to understand, with detail proportional
to the task. A reader should understand the intended result, the decisions and their reasons,
the changes needed, and how success will be checked. These are useful information, not
mandatory headings or a fixed outline.

Use the user's language. Explicit requests about format, length or detail take priority.
Keep a small correction compact; give a complex flow enough explanation to make its
dependencies and important failure cases understandable. Distinguish verified current
behavior from proposed behavior and assumptions. Resolve decisions that block the plan
before presenting it as ready for implementation.

## Select a format for a purpose

| Reader's need | Useful presentation |
| --- | --- |
| Understand components, dependencies or data movement | Mermaid flowchart |
| Follow exchanges between actors over time | Mermaid sequence diagram |
| Understand transitions and a lifecycle | Mermaid state diagram |
| Compare options, contracts or current and proposed behavior | Markdown table |
| Follow steps whose order matters | Numbered list |
| Understand an interface or a concrete behavior | Short code, input/output or scenario example |
| Find supporting evidence or navigate an explanation | Links, descriptive headings and focused emphasis |

These are examples, not an exhaustive menu or mandatory mapping. Plain prose is a complete
option. There is no diagram or table quota, fixed section count, or requirement to explain
why a visual was omitted. A table helps when rows share comparison dimensions; a list or
paragraph can be clearer when each item needs a different explanation.

Use checkboxes for an actual execution checklist when useful, rather than implying that a
proposed action has been completed. Use fenced code blocks with a language label for concrete
interfaces or examples. Keep headings and emphasis focused on the decisions that matter.
Prefer portable Markdown; the explanation should still be understandable if a diagram
does not render in the reader's client.

## Keep the explanation and visuals aligned

Place each illustration beside the explanation it supports. Introduce its purpose, clarify
the meaning and direction of arrows, and provide a short prose equivalent. Label whether
it shows current behavior, a proposed target, or an illustrative example.

Use readable labels and show only the relationships relevant to the decision. When a diagram
becomes difficult to follow, simplify it or split it by concern. Do not add components,
dependencies or decisions merely to complete a diagram. Include a failure path or boundary
when it matters to the proposed change.

Update the prose, tables and diagrams together when revising a plan. Link to precise
repository evidence for claims about current behavior; identify assumptions rather than
drawing them as established facts. A parser or renderer check establishes syntax, while
visual inspection establishes whether the diagram communicates the intended relationship.


## Review proportionally

Check that decisions are concrete, boundaries are respected, and proposed checks cover the
risks. Remove repetition and decorative structure. Optional examples live in
[presentation examples](references/presentation-examples.md); read them only when helpful.
A parser check establishes syntax, not clarity or discovery in a new Codex/Claude session.
