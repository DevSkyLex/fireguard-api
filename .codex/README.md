# FireGuard API in Codex

The repository includes **17 skills and 22 specialist agents**. Native role files
define their model, effort and service tier directly. The primary configuration also
registers the 25 frontend roles, forming a shared **47-agent catalog**. See the
[workflow](workflow.md#native-agent-settings).

## Getting started

Attach `fireguard-api/` to your Codex project and start a new task. Read `AGENTS.md`, the
[workflow](workflow.md), and the [rule routing](rules.md), then the module contract.
Invoke a skill by `$name` or through the selector. Rules are instructions to read,
not an automatic activation mechanism.

The local tooling uses Python 3.11+ and Node.js. API development and testing require
the PHP/Composer versions declared in `composer.json`, Make, and the PostgreSQL
infrastructure described in `OPERATIONS.md`. Serena is optional: check whether its
tools are available in the session and fall back to `rg` if it is not connected.

Keep one desktop project with API, web and landing attached. API or web can be primary;
Codex automatically discovers configuration from the primary folder. Its peer role declarations
make the same 47 agents available from either primary checkout. Read the owning repository's
AGENTS.md and workflow explicitly when working in a secondary folder.

The shared [configuration example](config.example.toml) contains the peer role declarations
and optional MCP setup. `.codex/config.toml` is local and ignored: copy the example on a new
checkout and replace `<API_CHECKOUT>` / `<WEB_CHECKOUT>` only in the local MCP entries. Preserve
existing local server settings when updating agent declarations. MCP `cwd` binds Angular,
Spartan, Playwright and each Serena server to its intended checkout. Both Serena servers stay
disabled unless needed. Configuration presence does not prove a live connection or trusted hooks.
Do not change personal settings, project trust or approval policy.

Validate tooling with `python -B .codex/scripts/validate.py`.

The project must be trusted to load its local configuration. Review hooks with
`/hooks` in the CLI before activation: their presence does not prove they are active.
`codex mcp list` checks the declared configuration, not the live connection. None
of these files should change trust, approvals, or personal settings.
The [maintenance guide](maintenance.md) covers checks, diagnostics, and older invocations.

## Optional Serena

`serena-api` is configured with `enabled = false` to avoid starting its process
and language server for every task. Use `rg` for routine text and documentation
work. Enable Serena for symbol navigation, reference analysis or refactoring.

For one CLI session, run from this repository root:

```powershell
codex -c 'mcp_servers.serena-api.enabled=true'
```

This override ends with the CLI session. In the desktop app or IDE, temporarily
set `enabled = true` in `.codex/config.toml`, start a new session, then restore
`false` after the task. In a desktop project, change the configuration in the primary folder.
The native `cwd` entry selects the API checkout before Serena uses `--project-from-cwd`.
Install a Serena version supporting this option. The other Serena instance has its own
`cwd` entry and is disabled by default as well. Update these entries when switching checkouts.
Do not start a separate Serena process just because its tools are unavailable.

## Choose a workflow

Every name below is complete. Reviewers, auditors, and explorers are read-only;
their validation entries identify evidence to inspect or request from the parent
if a command writes files. Builders receive a precise file scope and inherit the
session's permissions. The matrix does not require systematic delegation or
invocation of every role.

| Task                                   | Skill or reference                                       | Agent                                             | Relevant validation                                      |
| -------------------------------------- | -------------------------------------------------------- | ------------------------------------------------- | -------------------------------------------------------- |
| Complete requested change review       | `fg-api-review`                                          | Parent; specialists only when delegation is authorized | Separate staged/unstaged/untracked evidence, P0–P3 findings |
| Diagnose the active tooling session    | `fg-api-tooling-diagnose`                                | Parent                                            | Files, discovered catalogs, availability, observed execution |
| Comments and PHPDoc                    | [shared convention](../docs/guides/code-comments.md)     | `fg-api-comment-maintainer` (luna / medium, Fast) | Scoped docs-lint, annotation review and token comparison |
| Understand a module                    | `fg-api-explore`                                         | `fg-api-module-explorer`                          | Sources, auth/main mapping, routes, and contract         |
| Create a module                        | `fg-api-module`                                          | `fg-api-module-builder`                           | Targeted tests, PHPStan, Deptrac, lint                   |
| Domain model or invariant              | `fg-api-domain`                                          | `fg-api-domain-builder`                           | Domain tests, PHPStan, Deptrac                           |
| Command or query                       | `fg-api-usecase`                                         | `fg-api-usecase-builder`                          | Handler tests, failures/replay, lint                     |
| Port and adapter                       | `fg-api-port`                                            | `fg-api-port-builder`                             | Port tests, aliases, managers, lint                      |
| Endpoint and DTO                       | `fg-api-endpoint`                                        | `fg-api-endpoint-builder`                         | Success/denial, OpenAPI, route, lint                     |
| Schema/data migration                  | `fg-api-migrate`                                         | `fg-api-migration-builder`                        | SQL, configuration/history, auth/main schemas            |
| PHPUnit coverage                       | `fg-api-tests`                                           | `fg-api-test-writer`                              | Targeted file or filter, PostgreSQL                      |
| Architecture and boundaries            | `fg-api-arch-review`                                     | `fg-api-architecture-reviewer`                    | Two Deptrac analyses, observed invariants                |
| API contract                           | `fg-api-contract-review`                                 | `fg-api-contract-reviewer`                        | DTOs, statuses, OpenAPI, compatibility                   |
| Cross-cutting security                 | `fg-api-security-review`                                 | `fg-api-security-auditor`                         | Abuse scenarios and denial evidence                      |
| CI and deployment                      | `fg-api-workflow-review`                                 | `fg-api-workflow-reviewer`                        | Permissions, triggers, secrets, jobs, and both databases |
| Records, repositories, mappers, locks  | `fg-api-port` → `references/persistence.md`              | `fg-api-persistence-builder`                      | PostgreSQL integration, mapping, atomicity               |
| Query cost                             | `fg-api-arch-review` → `references/query-performance.md` | `fg-api-query-performance-reviewer`               | Available volumes/plans, pagination, N+1                 |
| Handler injection and registration     | `fg-api-arch-review` → `references/service-wiring.md`    | `fg-api-service-wiring-reviewer`                  | Aliases, tags, transports, explicit managers             |
| Authentication, sessions, MFA          | `fg-api-security-review` → auth checklist                | `fg-api-auth-reviewer`                            | Signatures, rotation, revocation, replay                 |
| RBAC and object access                 | `fg-api-security-review` → authorization checklist       | `fg-api-authorization-reviewer`                   | Tenant/organization, object, bulk and async paths        |
| Messenger, Scheduler, outbox           | `fg-api-usecase` → `references/usecase-patterns.md`      | `fg-api-async-builder`                            | Commit/rollback, retry, receipts, leases, recovery       |
| External services and webhooks         | `fg-api-port` → `references/integrations.md`             | `fg-api-integration-builder`                      | Test doubles, timeouts, errors, signatures, duplicates   |
| Assigned module documentation contract | `fg-api-module` → `references/module-docs.md`            | `fg-api-module-documenter`                        | Seven sections, verified facts, local links              |
| Quality gate                           | `fg-api-quality`                                         | Parent or already assigned specialist             | Checks proportional to the change                        |
| SonarQube issue triage and remediation | `fg-api-sonarqube`                                       | Parent or already assigned specialist             | Exact analyzed SHA, issue decisions, focused checks      |
| Logs, health and worker visibility | `OPERATIONS.md`, `SECURITY.md`, Shared module contract | `fg-api-observability-reviewer` | Redacted logs/metrics, failure signals, static limits |
| Requested second opinion               | `fg-api-codex-challenge`                                 | Reviewer suited to the question                   | Bounded independent opinion, no recursion                |

All seven Luna agents explicitly request Fast; Sol agents explicitly request Standard.
Speed is separate from reasoning effort. See [OpenAI speed documentation](https://learn.chatgpt.com/docs/agent-configuration/speed).

## Models and effort

Native TOMLs are the source of truth for each role. Invoke the custom role rather than
naming a generic task. Read [the workflow](workflow.md#native-agent-settings) for availability
and inheritance checks; configuration validity does not prove runtime behavior.

| Agent | Responsibility | Model | Effort | Mode |
| --- | --- | --- | --- | --- |
| `fg-api-architecture-reviewer` | Use to review fireguard-api PHP changes against the hexagonal Module Architecture Standard — layer direction, business logic in handlers not processors, ports vs concrete infrastructure, cross-module boundaries, naming, the dual-database wiring, and MODULE.md currency. Invoke after writing or modifying module code. Read-only — reports findings, does not edit. | gpt-6.1-sol · Standard | high | Read-only |
| `fg-api-async-builder` | Implement assigned Messenger/Scheduler flows with transactional outbox, bounded retry, idempotence, leases and recovery. | gpt-6.1-sol · Standard | xhigh | Assigned writes |
| `fg-api-auth-reviewer` | Review OAuth/OIDC, token verification, sessions, MFA, signatures, rotation, revocation and replay boundaries. | gpt-6.1-sol · Standard | high | Read-only |
| `fg-api-authorization-reviewer` | Review RBAC and tenant, organization and object authorization across routes, handlers, repositories and async paths. | gpt-6.1-sol · Standard | high | Read-only |
| `fg-api-comment-maintainer` | Define or maintain assigned comment conventions, correct source docblocks and run scoped documentation formatting and lint without changing behavior. | gpt-6-luna · Fast | medium | Assigned writes |
| `fg-api-contract-reviewer` | Use to review the API Platform contract in fireguard-api — resource metadata, operation constants, Input/Output DTOs, serialization groups, status codes, filters, pagination, OpenAPI output, and exception-to-HTTP mapping — for regressions and drift. Invoke after changing an endpoint or a DTO. Read-only — reports findings, does not edit. | gpt-6.1-sol · Standard | high | Read-only |
| `fg-api-domain-builder` | Use to add Domain-layer code in fireguard-api — an aggregate or model under Domain/Model/, a value object, a domain event, or a domain exception — with the invariants enforced inside the model rather than in a handler. Invoke for "add an aggregate / value object / domain event / domain exception to <Module>". Writes code. | gpt-6.1-sol · Standard | medium | Assigned writes |
| `fg-api-endpoint-builder` | Build an API Platform endpoint with Resource, Operation, DTOs, Processor or Provider, validation, security, error mapping and functional tests. Business handlers belong to fg-api-usecase-builder when separately delegated. | gpt-6.1-sol · Standard | high | Assigned writes |
| `fg-api-integration-builder` | Implement external service adapters and webhooks behind Application ports with bounded timeouts, errors and tests using doubles. | gpt-6.1-sol · Standard | high | Assigned writes |
| `fg-api-migration-builder` | Generate or validate new Doctrine schema/data migrations on the explicit auth or main history. Repository-only changes do not automatically require a migration. | gpt-6.1-sol · Standard | xhigh | Assigned writes |
| `fg-api-module-builder` | Scaffold a backend bounded context with its first vertical slice, four-layer ownership, ports, wiring, persistence when needed, security, MODULE.md and baseline tests. | gpt-6.1-sol · Standard | high | Assigned writes |
| `fg-api-module-documenter` | Update assigned MODULE.md contracts from verified source and configuration evidence, preserving their normative scope. | gpt-6-luna · Fast | medium | Assigned writes |
| `fg-api-module-explorer` | Use to map an existing module in fireguard-api before changing it — its use cases, ports and adapters, Doctrine records and which database they live on, API resources and routes, config wiring, tests, and the closest implementation anchors to mirror. Invoke before implementing in unfamiliar territory. Read-only — produces a map, not edits. | gpt-6-luna · Fast | high | Read-only |
| `fg-api-observability-reviewer` | Review assigned logs, correlation, health checks and failure visibility for API and worker flows. | gpt-6.1-sol · Standard | high | Read-only |
| `fg-api-persistence-builder` | Implement assigned Doctrine Records, repositories, mappers and locking through Application ports; schema migrations remain with the migration specialist. | gpt-6.1-sol · Standard | high | Assigned writes |
| `fg-api-port-builder` | Use to add a port and its adapter in fireguard-api — an Application/Port/Outbound (or Inbound) interface, the Infrastructure adapter or Doctrine repository that fulfils it, the config/modules alias, the explicit entity-manager wiring, and the adapter unit test. Invoke when a use case needs an external dependency, or when a module must publish a capability. Writes code. | gpt-6.1-sol · Standard | medium | Assigned writes |
| `fg-api-query-performance-reviewer` | Review Doctrine query cost, N+1, pagination, volumes, index coverage and supplied plans without applying changes. | gpt-6.1-sol · Standard | high | Read-only |
| `fg-api-security-auditor` | Use to security-review changes touching authentication, OAuth2/OIDC, sessions, trusted devices, OTP/MFA, RBAC permissions, the audit ledger, multi-tenant/organization scoping, secrets handling, or the Stripe billing webhook. FireGuard is an identity + fire-safety platform, so these paths are its crown jewels. Read-only — reports risks and fixes, does not edit. | gpt-6.1-sol · Standard | xhigh | Read-only |
| `fg-api-service-wiring-reviewer` | Review aliases, dependency injection, tags, handler registration and explicit entity and transaction managers. | gpt-6.1-sol · Standard | medium | Read-only |
| `fg-api-test-writer` | Use to author or repair PHPUnit tests in fireguard-api — unit tests for handlers, adapters, and domain models; integration tests for Doctrine repositories; functional tests for API endpoint contracts including denial paths; E2E for full flows. Invoke when a change needs coverage. Writes tests; never changes production code to make one pass. | gpt-6.1-sol · Standard | high | Assigned writes |
| `fg-api-usecase-builder` | Build a command or query use case with typed message, Handler, Result, ports, wiring and unit tests. HTTP work belongs to fg-api-endpoint-builder when separately delegated. | gpt-6.1-sol · Standard | high | Assigned writes |
| `fg-api-workflow-reviewer` | Use to review GitHub Actions changes in fireguard-api — triggers, permissions, secret exposure, pull_request_target risks, caching, matrix and job dependencies, and deployment gating. Invoke when .github/workflows or the composite actions change. Read-only — reports findings, does not edit. | gpt-6.1-sol · Standard | high | Read-only |

## Tooling checks

```powershell
python -B .codex/scripts/validate.py
python -B -m unittest discover -s .codex/scripts -p 'test_*.py'
node --test .codex/hooks/adapter.test.mjs
```

These checks cover native agents, peer registration, skills, references, and
guards. Application suites are unnecessary for changes limited to this tooling.
Counts are derived from files rather than hard-coded in the validators.
A new session is still needed to verify actual discovery of the updated catalog.

## Reference points

The MCP example is in `config.example.toml`; active settings are in the ignored local
`config.toml`. Guards and formatting are in `hooks.json` and
`hooks/`. They complement the sandbox and approvals. Local URLs documented in
`OPERATIONS.md` are attachment targets, not services started by Codex.

Official sources: [skills](https://developers.openai.com/codex/skills),
[agents](https://developers.openai.com/codex/subagents),
[hooks](https://developers.openai.com/codex/hooks),
[MCP](https://developers.openai.com/codex/mcp), and the
[model catalog](https://learn.chatgpt.com/docs/app-server#models).
