---
name: fg-api-review
description: "Review a FireGuard API working-tree or explicitly requested Git diff for actionable regressions, with separate staged, unstaged and untracked coverage. Read-only."
---

# Complete change review

Read `AGENTS.md`, `.codex/workflow.md` and the matching rules. Review the requested
scope in read-only mode, including caches and generated artifacts. Use existing test
results as evidence; report missing checks and the commands needed to obtain them.

## Establish the review states

For uncommitted work, inventory `git status --short`, `git diff --cached --name-status`,
`git diff --name-status` and `git ls-files --others --exclude-standard`. Filter protected
paths before reading contents. Then inspect path-scoped `git diff --cached -- <paths>`
(HEAD to index) and `git diff -- <paths>` (index to working tree).
Keep index and working-tree findings distinct:
a staged regression still matters when an unstaged edit restores the HEAD content.
`git diff HEAD` alone loses that case. Follow both sides of renames/deletions and inspect
the final combined behavior without reporting the same issue twice.

Filter protected paths before reading any diff contents or untracked files, following
AGENTS.md. Never open secret environment files or protected keys. Include relevant
untracked source, configuration and tests; ignored/generated/dependency trees are not
new review scope. If a commit/branch range was requested, use that exact range instead.

## Route by actual impact

Read the owning MODULE.md and affected consumers. Load only the relevant review resources:

- PHP ownership, layer direction, ports, auth/main wiring: [architecture review](../fg-api-arch-review/SKILL.md).
- Routes, DTOs, serialization, errors, OpenAPI and compatibility: [contract review](../fg-api-contract-review/SKILL.md).
- Auth, permissions, tenant isolation, audit or signed webhooks: [security review](../fg-api-security-review/SKILL.md).
- CI triggers, permissions, caches or deployment: [workflow review](../fg-api-workflow-review/SKILL.md).
- Tooling/skills/roles: `.codex/maintenance.md`, active references, catalog consistency and available runtime evidence.

Use [quality checks](../fg-api-quality/SKILL.md) to identify relevant validation evidence,
not to authorize cache writes, database setup or fixes inside this review. Inspect tests
for success and denial/replay paths where applicable. Separate introduced issues from
pre-existing problems. A requested complete review does not automatically authorize
additional agents or recursive challenges; follow the session's delegation rules.

## Report actionable findings

Prioritize P0 (immediate blocker), P1 (urgent), P2 (normal correction), P3 (low priority).
For each finding, give the exact file/line, source state (index/working tree/untracked),
triggering scenario, observed evidence, consequence and smallest useful correction.
Check plausible counterexamples before reporting. Avoid speculative findings and
wording-only preferences. Attach inline feedback only to verified current file lines;
index-only lines must be identified as such in the report.

Finish with a compact coverage/validation account: states and areas examined, actual
checks and their results, checks not run and why, and material uncertainty. Say explicitly
when no actionable finding remains; static inspection does not prove runtime behavior.
