---
trigger: model_decision
description: Used only when persistence/adapters are generated or discussed.
---

# Infrastructure Rules
**Activation Mode:** Model Decision

## Purpose
Implement outbound ports. Persistence, HTTP, Mail, Files, Cache. No business decisions.

## Doctrine
- `Infrastructure/Persistence/Doctrine/Entity/*Record.php`: persistence models.
- `Repository/*DoctrineRepository.php`: only implements `*RepositoryPort`.
- Map Domain↔Record in dedicated `Mapper` classes.

## Rules
- No business logic in repositories.
- Keep transactions in `TransactionManagerPort` adapter.
- Use DBAL types for VOs to avoid leaking primitives.

## Example (Repository)
```php
final class UserDoctrineRepository implements UserRepositoryPort {
    public function __construct(private EntityManagerInterface $em, private UserMapper $m) {}
    public function save(User $user): void {
        $this->em->persist($this->m->toRecord($user));
    }
}
```
