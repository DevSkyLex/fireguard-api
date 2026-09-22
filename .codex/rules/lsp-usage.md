# Using code intelligence

Serena over MCP with Intelephense is optional. Inspect the active tools before relying on it;
configuration is not proof of a connected server. Use symbol-aware navigation for declarations
and references when connected, and `rg` for literals, YAML service wiring, migrations and docs.

- Before changing a signature, constant, enum case, port or DTO field, inspect its
  references and visit each affected consumer.
- Find port implementations through references to the interface when implementation
  lookup is unavailable.
- Use a symbol overview before reading a long handler or provider in full.
- Ask for diagnostics on touched PHP files when the tool supports them.
- Treat a first empty result from a cold index as inconclusive. Retry once and compare
  it with a text search.

The free Intelephense implementation may have incomplete navigation support while its index
warms. If unavailable, continue with repository search and report that fallback. Empty text
results do not prove absence of consumers: aliases, namespaces and metadata may hide them.
Language-server results accelerate review; PHPStan, both Deptrac configurations, container
lint and tests remain the gates.
