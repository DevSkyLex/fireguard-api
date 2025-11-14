---
trigger: always_on
---

## Purpose
Centralize only the elements that are truly common to the entire application:
- Contracts (ports) réutilisés par tous les modules (ex: ClockPort, TransactionManagerPort, EventBusPort, UuidGeneratorPort).
- Value Objects génériques partagés (Uuid, Email, etc.).
- Exceptions ou utilitaires transverses (ApplicationException, InfrastructureException).
Rien de spécifique à un sous-domaine ne doit être placé ici.

## Contents
- `Application/Port`: ports génériques partagés (ClockPort, TransactionManagerPort, EventBusPort, UuidGeneratorPort…).
- `Domain/ValueObject`: VOs transverses (Uuid, Email, etc.).
- `Domain/Event`: interfaces/traits communs aux événements de domaine.
- `Application/Exception`: exceptions génériques.
- `core utilities`: seulement s’ils sont réutilisables par tous les modules.

## Rules
- Shared ne contient aucune logique propre à un sous-domaine (SSO, Clients, etc.).
- Les modules métiers dépendent de ces ports/VO mais n’ajoutent rien de spécifique dans Shared.
- Aucune dépendance vers les implémentations Infrastructure dans Domain/Application.
- Favoriser l’immutabilité et les classes `final` par défaut.

## Example (ClockPort)
```php
interface ClockPort { public function now(): \DateTimeImmutable; }
final class SystemClockAdapter implements ClockPort {
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable('now'); }
}
```
