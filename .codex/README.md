# FireGuard API in Codex

The repository configuration includes **15 skills and 20 specialist agents**. Agent roles
do not pin model versions: the parent resolves the model category and effort at invocation
time from the available catalog. Profiles are in [agent-profiles.toml](agent-profiles.toml),
with the protocol in the [workflow](workflow.md).

## Getting started

Open `fireguard-sso-api/` in Codex and start a new task. Read `AGENTS.md`, the
[workflow](workflow.md), and the [rule routing](rules.md), then the module contract.
Invoke a skill by `$name` or through the selector. Rules are instructions to read,
not an automatic activation mechanism.

The local tooling uses Python 3.11+ and Node.js. API development and testing require
the PHP/Composer versions declared in `composer.json`, Make, and the PostgreSQL
infrastructure described in `OPERATIONS.md`. Serena is optional: check whether its
tools are available in the session and fall back to `rg` if it is not connected.

After cloning or moving the checkout, run from the repository root:

```powershell
python -B .codex/scripts/configure.py
python -B .codex/scripts/configure.py --check
python -B .codex/scripts/validate.py
```

The project must be trusted to load its local configuration. Review hooks with
`/hooks` in the CLI before activation: their presence does not prove they are active.
`codex mcp list` checks the declared configuration, not the live connection. None
of these files should change trust, approvals, or personal settings.
The [maintenance guide](maintenance.md) covers checks, diagnostics, and older invocations.

## Choose a workflow

Every name below is complete. Reviewers, auditors, and explorers are read-only;
their validation entries identify evidence to inspect or request from the parent
if a command writes files. Builders receive a precise file scope and inherit the
session's permissions. The matrix does not require systematic delegation or
invocation of every role.

| Task | Skill or reference | Agent | Relevant validation |
| --- | --- | --- | --- |
| Understand a module | `fg-api-explore` | `fg-api-module-explorer` | Sources, auth/main mapping, routes, and contract |
| Create a module | `fg-api-module` | `fg-api-module-builder` | Targeted tests, PHPStan, Deptrac, lint |
| Domain model or invariant | `fg-api-domain` | `fg-api-domain-builder` | Domain tests, PHPStan, Deptrac |
| Command or query | `fg-api-usecase` | `fg-api-usecase-builder` | Handler tests, failures/replay, lint |
| Port and adapter | `fg-api-port` | `fg-api-port-builder` | Port tests, aliases, managers, lint |
| Endpoint and DTO | `fg-api-endpoint` | `fg-api-endpoint-builder` | Success/denial, OpenAPI, route, lint |
| Schema/data migration | `fg-api-migrate` | `fg-api-migration-builder` | SQL, configuration/history, auth/main schemas |
| PHPUnit coverage | `fg-api-tests` | `fg-api-test-writer` | Targeted file or filter, PostgreSQL |
| Architecture and boundaries | `fg-api-arch-review` | `fg-api-architecture-reviewer` | Two Deptrac analyses, observed invariants |
| API contract | `fg-api-contract-review` | `fg-api-contract-reviewer` | DTOs, statuses, OpenAPI, compatibility |
| Cross-cutting security | `fg-api-security-review` | `fg-api-security-auditor` | Abuse scenarios and denial evidence |
| CI and deployment | `fg-api-workflow-review` | `fg-api-workflow-reviewer` | Permissions, triggers, secrets, jobs, and both databases |
| Records, repositories, mappers, locks | `fg-api-port` → `references/persistence.md` | `fg-api-persistence-builder` | PostgreSQL integration, mapping, atomicity |
| Query cost | `fg-api-arch-review` → `references/query-performance.md` | `fg-api-query-performance-reviewer` | Available volumes/plans, pagination, N+1 |
| Handler injection and registration | `fg-api-arch-review` → `references/service-wiring.md` | `fg-api-service-wiring-reviewer` | Aliases, tags, transports, explicit managers |
| Authentication, sessions, MFA | `fg-api-security-review` → auth checklist | `fg-api-auth-reviewer` | Signatures, rotation, revocation, replay |
| RBAC and object access | `fg-api-security-review` → authorization checklist | `fg-api-authorization-reviewer` | Tenant/organization, object, bulk and async paths |
| Messenger, Scheduler, outbox | `fg-api-usecase` → `references/usecase-patterns.md` | `fg-api-async-builder` | Commit/rollback, retry, receipts, leases, recovery |
| External services and webhooks | `fg-api-port` → `references/integrations.md` | `fg-api-integration-builder` | Test doubles, timeouts, errors, signatures, duplicates |
| Assigned module documentation contract | `fg-api-module` → `references/module-docs.md` | `fg-api-module-documenter` | Seven sections, verified facts, local links |
| Quality gate | `fg-api-quality` | Parent or already assigned specialist | Checks proportional to the change |
| SonarQube issue triage and remediation | `fg-api-sonarqube` | Parent or already assigned specialist | Exact analyzed SHA, issue decisions, focused checks |
| Requested second opinion | `fg-api-codex-challenge` | Reviewer suited to the question | Bounded independent opinion, no recursion |

## Models and effort

Profiles associate Luna with bounded research, Terra with routine tasks, Sol with
structural implementation, and Astra with high-stakes reviews. Effort belongs to
the role, not a model number. The resolver chooses the newest visible version
that supports the effort in that category; it does not silently lower effort.

The parent supplies a real catalog and passes the result to the delegation tool
with bounded context. A direct invocation without resolution inherits the parent's
model and effort. There is no native FireGuard alias `astra-latest` or `sol-latest`.

## Tooling checks

```powershell
python -B .codex/scripts/validate.py
python -B .codex/scripts/configure.py --check
python -B -m unittest discover -s .codex/scripts -p 'test_*.py'
node --test .codex/hooks/adapter.test.mjs
```

These checks cover agents, profiles, simulated resolution, skills, references, and
guards. Application suites are unnecessary for changes limited to this tooling.
Counts are derived from files rather than hard-coded in the validators.
A new session is still needed to verify actual discovery of the updated catalog.

## Reference points

MCP settings are in `config.toml`; guards and formatting are in `hooks.json` and
`hooks/`. They complement the sandbox and approvals. Local URLs documented in
`OPERATIONS.md` are attachment targets, not services started by Codex.

Official sources: [skills](https://developers.openai.com/codex/skills),
[agents](https://developers.openai.com/codex/subagents),
[hooks](https://developers.openai.com/codex/hooks),
[MCP](https://developers.openai.com/codex/mcp), and the
[model catalog](https://learn.chatgpt.com/docs/app-server#models).
