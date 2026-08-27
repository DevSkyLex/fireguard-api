---
description: Review backend changes against the hexagonal Module Architecture Standard — layer direction, logic placement, ports, cross-module boundaries, dual-database wiring, MODULE.md currency. Read-only.
argument-hint: '[path, module, or diff scope — defaults to the working-tree changes]'
---

Delegate to the **fg-architecture-reviewer** subagent: $ARGUMENTS

If no scope is given, review the working-tree changes (`git status` + `git diff`).

Require it to check, worst first:

1. **Layer direction** — Presentation → Application → Domain, Infrastructure → Application, Domain → nothing but `SharedDomain`. Prove it with `make deptrac`.
2. **Business logic in handlers, not processors** — the most common real violation, and the one deptrac cannot see. It has to read the code.
3. **Ports, not concrete classes** — a handler constructor taking a Doctrine repository, an adapter, or an `EntityManagerInterface` is a violation even when it compiles.
4. **Cross-module boundaries** — only `Application\Port\` and `Application\Contract\` may cross. `Domain\` and `Infrastructure\` may not.
5. **The dual-database wiring** — every repository, processor, and provider touching Doctrine carries an explicit `$entityManager`. Cross-check the module's `Record` namespace against `config/packages/doctrine.yaml`; "it autowires" is not an acceptable answer.
6. **Naming** — the Command/Handler/Result, Port, Adapter, Processor, Provider, Input/Output, Operations scheme, with namespaces mirroring folders.
7. **Endpoint completeness** — the six-item checklist, security included.
8. **`MODULE.md` currency** — updated in the same change that added an endpoint, flow, error code, or configuration requirement.

It must substantiate with `make deptrac`, `make phpstan`, and `make lint`, and defer security to **fg-security-auditor**, contract questions to **fg-contract-reviewer**, and test adequacy to **fg-test-writer**.

Ask for findings ranked **blocker → should-fix → nit**, each citing its section, and a one-line verdict: conforms, or changes required.
