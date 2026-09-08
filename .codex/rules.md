# Rules to read for touched paths

Before editing, read each local rule whose patterns match a touched file. Patterns
are relative to the API checkout. `AGENTS.md`, `ARCHITECTURE.md`, `SECURITY.md` and
the owning `MODULE.md` remain authoritative.

| Source | Matching paths |
| --- | --- |
| [application](rules/application.md) | `src/*/Application/**/*.php` |
| [domain](rules/domain.md) | `src/*/Domain/**/*.php` |
| [infrastructure](rules/infrastructure.md) | `src/*/Infrastructure/**/*.php` |
| [lsp-availability](rules/lsp-availability.md) | Read when investigating tool availability |
| [lsp-usage](rules/lsp-usage.md) | `src/**/*.php`, `tests/**/*.php` |
| [migrations](rules/migrations.md) | `migrations/**/*.php` |
| [module-config](rules/module-config.md) | `config/modules/*.yaml`, `config/packages/*.yaml`, `config/packages/**/*.yaml` |
| [presentation](rules/presentation.md) | `src/*/Presentation/**/*.php` |
| [tests](rules/tests.md) | `tests/**/*.php` |
