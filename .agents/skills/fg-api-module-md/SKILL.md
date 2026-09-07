---
name: fg-api-module-md
description: "How to write and keep current a src/Module/MODULE.md in fireguard-sso-api — the seven required sections, what belongs in each, and the changes that make an update mandatory in the same commit. Use whenever a module gains an endpoint, a flow, an error code, or a configuration requirement."
---

# fg-api-module-md

Maintain `src/<Module>/MODULE.md` as the normative module contract. Read `AGENTS.md`,
`ARCHITECTURE.md` and a comparable module document before editing.

Keep these seven sections: Overview, API Endpoints, Flows, Architecture, Configuration,
Testing and Error Codes. Update the document in the same change as a route, public field,
flow, error code, permission, integration or configuration requirement.

Record ownership, invariants, externally visible contracts and the reason behind unusual
choices. Keep implementation inventories and chronological work logs out. Say explicitly
when a section has no entries rather than deleting it. Endpoint tables include method, path,
security and meaningful response behavior. Error codes include stable identifiers and when
they occur. Keep the prose short enough to remain authoritative.
