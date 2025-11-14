---
trigger: always_on
---

## Principles
- Pure business code. Deterministic, testable, side-effect free.
- PHP 8.4: `readonly` promoted where applicable; enum for finite sets; typed properties; final classes by default.
- No framework imports. No Doctrine annotations/attributes.

## Structure
- `Domain/Model/{Aggregate}/{Aggregate}.php`: aggregate root.
- `Entity.php` (internal entities), `Factory.php`, `Policy.php`, `Specification.php`.
- `ValueObject/*`: immutable, validated in constructor, `equals()` provided.
- `Event/*`: immutable domain events.

## Rules
- No public setters on aggregates; use methods that express intent.
- IDs are VOs (e.g., `{Something}Id`). No raw string UUIDs in signatures.
- Policies/specifications return booleans; no I/O.
- Domain exceptions extend a single base `DomainException`.

## Example (Value Object)
```php
final readonly class Email {
    public function __construct(public string $value) {
        if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Invalid email.');
        }
    }
    public function equals(self $other): bool { return $this->value === $other->value; }
}
```
