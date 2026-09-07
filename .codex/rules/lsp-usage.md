# Using code intelligence

Use symbol-aware navigation for declarations and references when Serena is connected;
use `rg` for literals, YAML service wiring, migrations and documentation.

- Before changing a signature, constant, enum case, port or DTO field, inspect its
  references and visit each affected consumer.
- Find port implementations through references to the interface when implementation
  lookup is unavailable.
- Use a symbol overview before reading a long handler or provider in full.
- Ask for diagnostics on touched PHP files when the tool supports them.
- Treat a first empty result from a cold index as inconclusive. Retry once and compare
  it with a text search.

Language-server results accelerate review; PHPStan, Deptrac, container lint and tests
remain the gates.
