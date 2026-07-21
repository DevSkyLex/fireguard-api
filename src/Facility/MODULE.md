# Facility Module

## Overview

Facility manages generic organizational structures such as sites, buildings,
floors, zones, and areas. It is organization-scoped and uses
`OrganizationAuthorizationPort` for permission checks.

Main goals:

- Provide a reusable, generic location hierarchy.
- Keep business-specific logic out of this module.
- Support progressive modularization (modulith today, extract later).

## API Endpoints

| Method | Path | Description |
| --- | --- | --- |
| GET | `/api/facilities/types` | List facility types for selects |
| POST | `/api/organizations/{organizationId}/facilities` | Create a facility |
| GET | `/api/organizations/{organizationId}/facilities` | List facilities (filters: `includeArchived`, `type`, `status`, `parentFacilityId`, `rootsOnly`, `code`) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}` | Get one facility |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/children` | List direct children for lazy tree expansion (paginated) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/descendants` | List all descendants for bulk subtree reads |
| PATCH | `/api/organizations/{organizationId}/facilities/{facilityId}` | Update a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/archive` | Archive a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/move` | Move a facility under another parent |

Lazy tree reads should use `/facilities?rootsOnly=true` for the initial level and
`/facilities/{facilityId}/children` when a node is expanded. The
`/descendants` endpoint is intended for bulk subtree reads and is not the
default tree table expansion contract.

### Attachments (R11b)

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/facilities/{facilityId}/attachments` | Upload a multipart file attachment |
| GET | `/api/facilities/{facilityId}/attachments` | List a facility's attachments |
| GET | `/api/facility-attachments/{id}` | Get one attachment |
| DELETE | `/api/facility-attachments/{id}` | Delete an attachment (requires `If-Match: "revision-N"`) |

Generalized file attachments on a facility, mirroring the proven
`Equipment\...\EquipmentAttachment` slice and the shared attachment kernel
(`src/Shared/MODULE.md`): `Facility\Domain\Model\Attachment\FacilityAttachment`
aggregate, `FacilityAttachmentRepositoryPort`/`FacilityAttachmentRepository`,
`AddFacilityAttachment`/`DeleteFacilityAttachment`/`ListFacilityAttachments`
use cases, and a multipart `FacilityMediaProcessor`/`FacilityMediaProvider`
pair (`FacilityAttachmentResource`, no serialization-group-filtered JSON body
— `deserialize: false`). Storage key:
`facility/{facilityId}/attachments/{attachmentId}_{fileName}` via
`Shared\Domain\Attachment\StoragePathScheme`. MIME/size validated by
`Shared\Presentation\Api\Attachment\MultipartAttachmentGuard` before any
bytes are read. Write-then-persist with storage rollback on DB failure
(mirrors `AddAttachmentHandler`); delete removes the stored object then the
row. No new permissions: reuses `organization.facilities.read` /
`organization.facilities.write`.

## Permission Model

This module relies on Organization-scoped permissions:

- `organization.facilities.read`
- `organization.facilities.write` (also covers attachments — see above)

## Domain Model

Aggregate:

- `Facility`

Main fields:

- `id`
- `organizationId`
- `parentFacilityId` (optional)
- `hasChildren` (read-only, indicates whether the node has visible direct children)
- `equipmentCount` (read-only, active non-decommissioned published equipment assigned to
  the facility). The module owns no equipment data: the figure is read through
  `FacilityEquipmentDependencyPort::countActiveEquipmentByFacility`, batched once per query
  rather than once per row. A facility absent from the port's answer counts as zero.
- `type` (`site`, `building`, `floor`, `zone`, `area`)
- `name`
- `code` (optional)
- `status` (`active`, `archived`)
- `address` (optional)
- `latitude` (optional, decimal degrees, range [-90, 90]; required together with `longitude`)
- `longitude` (optional, decimal degrees, range [-180, 180]; required together with `latitude`)
- `metadata` (JSON object)
- `createdAt`, `updatedAt`

## Persistence

- Table: `facilities` (main database)
- Doctrine mapping: `src/Facility/Infrastructure/Persistence/Doctrine/Record`
- Migration: `migrations/main/Version20260212120000.php`
- Migration (coordinates): `migrations/main/Version20260708120000.php`
- Repository: `Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityRepository`
- Table: `facility_attachments` (main database) — `facility_id` FK `ON DELETE
  CASCADE`, unique `storage_path`, `revision` (ETag optimistic concurrency,
  never bumped in place). Migration: `migrations/main/Version20260717111309.php`
  (R11b, shared across the three new attachment tables). Repository:
  `Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityAttachmentRepository`.
  A hard `facilities` row delete cascades the FK at the DB level, but does
  **not** delete the stored object — parents are expected to be archived, not
  hard-deleted, in normal operation; a scheduled orphan-object sweep is
  deferred (same accepted gap as `equipment_attachments`).

## Architecture

- Presentation: Api Platform resources, providers, processors, DTOs.
- Application: Use cases (command/query), repository port.
- Domain: Facility aggregate, value objects, domain exceptions.
- Infrastructure: Doctrine record/mapper/repository.

Cross-module contracts and lifecycle invariants:

- `FacilityArchivalGuardPort` (inbound): the shared "no active dependents"
  archival guard applied on every archive surface (archive use case, canonical
  DELETE and PATCH-to-archived, intervention publication) and before the draft
  hard-delete. Refuses with `FacilityHasActiveDependentsException` (HTTP 409)
  while the facility has an active descendant facility, active equipment, or an
  in-progress inspection. The equipment/inspection checks are provided by the
  owning modules through `FacilityEquipmentDependencyPort` /
  `FacilityInspectionDependencyPort` (outbound, adapters in Equipment/Inspection).
- Canonical DELETE = archive — the only REVERSIBLE retirement state (restore is
  refused while the parent is archived). Idempotent: a repeat DELETE is a no-op.
- The descendants listing and the archival probe (`hasActiveDescendants`) run on
  a single recursive CTE over PUBLISHED records: draft intervention scratchpads
  are invisible to both, and archived intermediate nodes are traversed so a live
  descendant beneath them is still found.
- Regulated actions emit domain events (`src/Facility/Domain/Event/`) recorded
  in the audit ledger by Audit's `AuditEventSubscriber`: `facility.archived`,
  `facility.restored`, `facility.moved` (previous/new parent in metadata).
  Emission sites: the Archive/Restore/Move handlers (which load through
  `findPublishedById` — draft scratchpads are unreachable) and the canonical
  processor, which COLLECTS its events during the mutation and dispatches
  them only after `wrapInTransaction` commits (no phantom ledger row on
  rollback). Idempotent repeats and same-parent moves emit nothing. The
  intervention `apply()` path is deferred to the `intervention.published`
  audit action. Note: a dedicated Equipment subscriber for `FacilityArchived`
  (once planned for reconciliation) is deliberately NOT built — the complete
  archival guard already refuses to archive a facility with active equipment,
  so there is never anything to reconcile.
- **Bulk CSV import (R13)**: `Facility\Application\Port\Inbound\FacilityProvisioningPort`
  is a new inbound port, hosted in this module, that lets another module
  (Import's bulk CSV import) provision one facility programmatically. Its
  implementation, `FacilityProvisioningService` (`Application/Service`),
  resolves an optional `parentCode` to a parent facility id via
  `FacilityRepositoryPort::findByOrganizationId(..., code: $parentCode, limit:
  1)` and then dispatches the existing `CreateFacilityCommand` through
  `CommandBusPort` — the same synchronous path the HTTP API uses, so the
  transactional plan-quota check runs intact — translating every failure
  (quota, an unknown parent code, or a domain validation error, each raised
  directly or wrapped in `MessengerRuntimeException`) into a typed
  `ProvisionOutcome` (`CREATED`|`QUOTA_EXCEEDED`|`INVALID`) instead of
  rethrowing. Mirrors `Intervention\Application\Port\Inbound\InterventionDraftFactoryPort`.
  See `src/Import/MODULE.md`.

## Configuration

- Service wiring: `config/modules/facility.yaml`
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`

## Testing

- Unit: `tests/Unit/Facility`
  - `Application/UseCase/Command/Attachment/{Add,Delete}FacilityAttachment`,
    `Application/UseCase/Query/Attachment/ListFacilityAttachments` — handler
    behavior including storage-write-then-persist rollback on DB failure and
    path-traversal-safe file naming.
  - `Presentation/Api/Processor/Attachment/FacilityMediaProcessorTest`,
    `Presentation/Api/Provider/Attachment/FacilityMediaProviderTest` —
    permission enforcement and the `If-Match` revision guard on delete.
- Integration (real database):
  `tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/FacilityAttachmentRepositoryTest`.
- Functional: `tests/Functional/Api/FacilityAttachmentApiTest.php`.
- Run module tests: `make test tests/Unit/Facility/`
