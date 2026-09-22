# Explicit rule routing

This index is read by the agent; its glob patterns do not activate native Codex rules automatically.
Before editing, read the entries matching touched paths and relevant risks. Paths are relative
to the API root. `AGENTS.md`, `ARCHITECTURE.md`, `SECURITY.md` and the owning `MODULE.md`
remain authoritative.

| Paths or risk | Read |
| --- | --- |
| New `src/<Module>/` files, ownership or cross-module dependency | [hexagonal layout](references/hexagonal-layout.md), owning `MODULE.md` |
| `src/*/Application/**/*.php`, use cases, bus handlers, async orchestration | [application](rules/application.md); [async patterns](../.agents/skills/fg-api-usecase/references/usecase-patterns.md) for outbox/retry |
| `src/*/Domain/**/*.php` | [domain](rules/domain.md) |
| `src/*/Infrastructure/**/*.php`, repositories, Records, vendor adapters | [infrastructure](rules/infrastructure.md) |
| PHP signature/port/DTO/enum changes; code-intelligence availability | [LSP use and fallback](rules/lsp-usage.md) |
| `migrations/**/*.php`, `config/migrations/*.yaml`, schema or persisted-data changes | [migrations](rules/migrations.md), [dual databases](../.agents/skills/fg-api-migrate/references/dual-database.md) |
| `config/modules/*.yaml`, `config/services*.yaml`, `config/packages/**/*.yaml`, `config/packages/*.yaml` | [module configuration](rules/module-config.md); database reference for Doctrine wiring |
| `src/*/Presentation/**/*.php`, `config/routes*.yaml`, `config/routes/**/*.yaml`, OpenAPI contracts | [presentation](rules/presentation.md), [endpoint contract](../.agents/skills/fg-api-endpoint/references/api-platform-contract.md) |
| Authentication, sessions, permissions, audit, tenant isolation, signed webhooks | `SECURITY.md`, [security checklist](../.agents/skills/fg-api-security-review/references/security-checklist.md) and layer rules |
| `tests/**/*.php`, `phpunit*.xml`, test bootstrap or fixtures | [tests](rules/tests.md), [test procedures](../.agents/skills/fg-api-tests/references/testing.md) |
| `src/*/MODULE.md`, public flows, errors or configuration contracts | [module docs](../.agents/skills/fg-api-module/references/module-docs.md) |
| `.github/workflows/**`, `.github/actions/**`, CI/deployment changes | [workflow review skill](../.agents/skills/fg-api-workflow-review/SKILL.md), migration rules for dual-database deployment |
| `Makefile`, PHP tooling config, quality commands | [quality skill](../.agents/skills/fg-api-quality/SKILL.md), [test procedures](../.agents/skills/fg-api-tests/references/testing.md) |
| `AGENTS.md`, `.codex/**`, `.agents/**` | [workflow](workflow.md), [maintenance](maintenance.md); validate tooling without running business suites |

When several rows match, combine their constraints; naming a specialist does not transfer ownership
automatically. Select roles through the README matrix and resolve their model profile before delegation.
