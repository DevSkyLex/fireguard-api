---
trigger: always_on
---

# PHP 8.4 Practices

## Language
- Strict types. No dynamic properties. Typed everywhere.
- `readonly` where possible; prefer immutability.
- `enum` for finite value sets. `match` for branching.
- Final classes by default; open only with intent.

## Error Handling
- Domain errors → DomainException; do not use generic RuntimeException.
- Never swallow exceptions; convert at boundaries (Presentation).

## Performance
- Avoid reflection-heavy patterns in hot paths.
- Prefer value objects over associative arrays.
- Avoid static state/singletons.

## Coding Style
- PSR-12, native types before docblocks.
- Prefer small files and pure functions where possible.
