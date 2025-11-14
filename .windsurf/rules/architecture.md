---
trigger: model_decision
description: Must guide reasoning about module boundaries only when architecture context is invoked.
---

# Architecture Rules (Hexagonal, Strict)
**Activation Mode:** Model Decision

## Goal
Enforce strict hexagonal boundaries in Symfony 7.3 + API Platform (PHP 8.4). No inward dependency on Infrastructure/Presentation from Domain/Application.

## Layer Contracts
- Domain: pure business logic. No Symfony/Doctrine/PHP globals beyond SPL. No I/O.
- Application: use-case orchestration. Depends on Domain + Ports (interfaces). No framework.
- Infrastructure: adapters implementing outbound ports, persistence (Doctrine), external I/O.
- Presentation: API Platform facade (Resource/DTO/State). No Domain objects across the boundary.
- Shared: generic ports/utilities; no hard deps from Domain/Application to Infrastructure.

## Allowed Dependencies
- Domain → (none)
- Application → Domain, Application\Port
- Infrastructure → Application\Port, Domain (for mapping only, no side effects)
- Presentation → Application (Handlers/DTO), never Domain or Infrastructure directly
- Shared: Ports only; Adapters wired by DI, never imported directly in Domain/Application.

## Naming & Placement
- One aggregate per folder: `src/Modules/{Module}/Domain/Model/{Aggregate}`.
- Repository interfaces live in `Application/Port/Outbound`. Implementations in `Infrastructure/Persistence/...`.
- DTOs never contain Domain objects. Use dedicated `*Output` types.

## Anti-Patterns (REJECT)
- Injecting EntityManager into Domain/Application.
- Returning Doctrine entities through API Platform.
- Side effects in Domain services.
- Mapping in controllers/processors without dedicated mappers.
- Static clocks/uuids; use `ClockPort`/`UuidGeneratorPort`.

## Transaction Boundary
- Use `TransactionManagerPort` around write UseCases. No transactions in Domain.

## Example: Command Flow
InputDTO → Command → Handler → Domain + Ports → Result → OutputDTO
