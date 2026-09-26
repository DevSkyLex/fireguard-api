---
name: fg-api-sonarqube
description: Triage and resolve FireGuard API SonarQube issues with evidence for fixes, false positives, and accepted findings.
---

# FireGuard API SonarQube triage

Use this skill when auditing or resolving SonarQube issues in the API repository.
Read `AGENTS.md`, `.codex/workflow.md`, the matching rules, and the owning
`src/<Module>/MODULE.md` before changing application code. Read `SECURITY.md`
when the finding touches authentication, authorization, sessions, tokens, or audit.

## Establish the current inventory

- Identify the requested branch and Sonar project (for example,
  `fireguard-api-develop`). Record the exact analyzed Git SHA. Refresh the
  inventory after a merge; an older scan cannot validate a newer commit.
- Use the connected SonarQube MCP when available. Otherwise use the SonarQube UI
  or API available to the session. Inspect each candidate's key, rule, status,
  location, message, code context, and impact before deciding.
- Group related findings for efficient code changes, but decide **False Positive**
  and **Accepted** per issue key. Never resolve a whole rule by assumption.

## Choose a disposition

- **Fix:** the rule identifies a real defect. Preserve module boundaries, domain
  invariants, persisted data, and HTTP contracts. Prefer typed restoration over
  private-field reflection; remove genuinely unused parameters; extract cohesive
  behavior when a method or signature is too large.
- **False Positive:** prove that the reported behavior is impossible or that the
  rule missed a real use. Check callable references, actual `expected, actual`
  test assertions, type guards, and framework entry points before commenting on
  the individual Sonar issue.
- **Accepted:** the finding is accurate, but a specific correction would weaken a
  verified invariant or make a boundary less clear. Explain the concrete tradeoff
  and existing tests in the issue comment. A good rating, issue volume, or effort
  alone is insufficient.

For return-count and aggregate-size findings, inspect the ordered guards and
ownership of state transitions before accepting. For signature findings, preserve
the exact persisted restoration contract and patch semantics. Do not treat an
entire class of these findings as false positives.

Do not change rule severity, quality profiles, exclusions, or add suppressions to
clear the count. Keep issue-key evidence in Sonar comments and the PR description
rather than a static issue ledger in the repository.

## Verify and finish

Run focused tests for the changed module first. Use `fg-api-quality` and the
Makefile for checks proportional to the change; include `make test` when the
blast radius justifies the complete gate. After the exact branch commit is
analyzed, recheck active issues, ratings, coverage, and Quality Gate against
the user's target. Report any finding without evidence or scan that has not
completed as unresolved.
