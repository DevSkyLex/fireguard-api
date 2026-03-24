---
name: "New Module Stack"
description: "Scaffold a new module with wiring, first use cases, API surface, and baseline tests."
argument-hint: "Module name and purpose, for example: Compliance module on main database"
agent: "agent"
---

Create a new backend module in this repository.

Before doing any implementation work:

1. Load [new-module](../skills/new-module/SKILL.md).
2. Load [add-use-case](../skills/add-use-case/SKILL.md).
3. Load [module-tests](../skills/module-tests/SKILL.md).
4. Load [api-platform-resource](../skills/api-platform-resource/SKILL.md) if the module is API-facing.
5. Load [security-sensitive-change](../skills/security-sensitive-change/SKILL.md) if the module belongs to Auth, OAuth, Session, Otp, TrustedDevice, Authorization, or Audit.

Then:

- choose the closest existing module as the structural reference
- scaffold the module with strict Presentation, Application, Domain, and Infrastructure boundaries
- wire config, Doctrine mapping, repositories, handlers, and ports under the correct entity manager
- implement at least one representative command or query flow needed by the request
- add API Platform resource pieces if the module is externally exposed
- create `MODULE.md` and the minimum representative tests for success and failure behavior

Complete the implementation in the workspace rather than returning only a plan, unless the request is explicitly exploratory.
