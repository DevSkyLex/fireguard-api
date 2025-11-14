---
trigger: always_on
---

## Purpose
Make the AI respect architecture and produce correct files by default.

## Generation Rules
- Default to hexagonal structure per `architecture.md`.
- Never place code in Infrastructure/Presentation from Domain/Application.
- When generating API operations, always create Input/Output DTO + State (Processor/Provider).
- When persistence is requested, create RepositoryPort first; then Doctrine adapter & mapper.

## File Placement Heuristics
- Business rule → Domain.
- Use case orchestration → Application Handler.
- External call / DB / HTTP → Infrastructure adapter.
- API resource/DTO/state → Presentation.

## Naming Heuristics
- `{Aggregate}RepositoryPort`, `{Aggregate}DoctrineRepository`, `{Aggregate}Mapper`.
- `{Action}Command|Handler|Result`, `{Query}|{Query}Handler|{Query}Result`.
- `*Input`, `*Output` DTOs.

## Safety
- Do not generate code that references Infrastructure from Domain/Application.
- Warn if DTOs expose Domain types.
