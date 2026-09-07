---
name: fg-api-port
description: "Add a port and its adapter — the Application/Port interface, the Infrastructure implementation, the config alias, the entity-manager wiring, and the test."
---

# fg-api-port

Read `AGENTS.md`, `.codex/workflow.md`, `.codex/rules/application.md`,
`.codex/rules/infrastructure.md` and the owning `MODULE.md`.

Classify the capability as outbound or inbound and create `<Capability>Port` under the matching
Application folder. Implement it in Infrastructure as an Adapter, or Repository for persistence.
Keep vendor and Doctrine types behind the boundary; cross-module ports exchange Application
contracts, never Domain types.

For Doctrine, confirm auth/main ownership from `doctrine.yaml`, add both the port alias and the
explicit entity-manager argument. Decline needless ports that isolate nothing. Add focused tests,
then run PHPStan, Deptrac, container/YAML lint and `debug:container` for the port FQCN.
