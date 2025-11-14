---
trigger: always_on
---

# Shared Rules
**Activation Mode:** Always On

## Purpose
Provide generic ports and utilities. Do not reference Infrastructure from Domain/Application.

## Contents
- `Application/Port`: `ClockPort`, `TransactionManagerPort`, `EventBusPort`, `UuidGeneratorPort`
- `Domain/ValueObject`: generic `Uuid`, `Email` (optional)
- `Infrastructure/*`: adapters (SystemClock, DoctrineTx, SymfonyEventBus)

## Rules
- Modules depend on ports, not adapters.
- Adapters are wired in container configuration; never imported into Domain/Application.
- Prefer immutability and readonly types.

## Example (ClockPort)
```php
interface ClockPort { public function now(): \DateTimeImmutable; }
final class SystemClockAdapter implements ClockPort {
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable('now'); }
}
```
