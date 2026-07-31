---
description: Add a port and its adapter — the Application/Port interface, the Infrastructure implementation, the config alias, the entity-manager wiring, and the test.
argument-hint: '<Module> <Capability> [inbound|outbound] — e.g. "Facility FacilityArchivalGuard inbound"'
---

Delegate to the **fg-port-builder** subagent: $ARGUMENTS

Require it to:

1. State whether the port is **outbound** (the module calls out — persistence, cache, a vendor API) or **inbound** (another module calls in), and place it under `Application/Port/{Outbound,Inbound}/<Area>/` accordingly.
2. Name it `<Capability>Port`, always an interface. The adapter is `<Capability>Adapter`, or `<Entity>Repository` for persistence.
3. Add **both** entries to `config/modules/<module>.yaml` — the `alias:` binding port to adapter, **and** the explicit `$entityManager` argument on anything touching Doctrine.
4. **Confirm which database owns the module** by finding the `dir:`/`prefix:` pair in `config/packages/doctrine.yaml`, and say how it confirmed it. Autowiring silently resolves to the default manager.
5. Keep vendor types behind the adapter, and business rules out of repositories.
6. For a cross-module port, take `Application/Contract/` types — never expose a Domain type outside the module.
7. Decline to create a port for behaviour that never crosses a boundary; that is indirection without isolation.
8. Run `make cs-fix phpstan deptrac lint` **and** `php -d memory_limit=1G bin/console debug:container` on the port FQCN — the only check that proves the alias actually resolves.
