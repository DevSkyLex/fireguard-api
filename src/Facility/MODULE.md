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
| GET | `/api/organizations/{organizationId}/facilities` | List facilities (filters: `includeArchived`, `type`, `status`, `parentFacilityId`, `rootsOnly`, `code`, `hasCoordinates`) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}` | Get one facility (includes the ancestor `path` breadcrumb) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/children` | List direct children for lazy tree expansion (paginated) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/descendants` | List all descendants for bulk subtree reads |
| PATCH | `/api/organizations/{organizationId}/facilities/{facilityId}` | Update a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/archive` | Archive a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/move` | Move a facility under another parent |
| GET | `/api/facilities/{id}` | Canonical item read (includes the ancestor `path` breadcrumb) |
| GET | `/api/facilities?organization={iri}` | Canonical collection read, org- or intervention-scoped |

Lazy tree reads should use `/facilities?rootsOnly=true` for the initial level and
`/facilities/{facilityId}/children` when a node is expanded. The
`/descendants` endpoint is intended for bulk subtree reads and is not the
default tree table expansion contract.

`hasCoordinates=true`/`false` filters on whether both `latitude` and `longitude`
are set; omit the parameter for no coordinate filtering. It exists so the
frontend facilities map can fetch its full pin set (`hasCoordinates=true`) and
the "unplaced facilities" list (`hasCoordinates=false`) via this listing
endpoint rather than a dedicated bbox endpoint — organizations are
quota-capped, so a full listAll is cheap.

### Ancestor breadcrumb (`path`)

Both facility detail reads — `GetFacilityProvider` (organization-scoped) and
`CanonicalFacilityProvider` (canonical item route) — populate `FacilityOutput::$path`:
a `list<{id, name, type}>` ordered root first, direct parent last, excluding the
facility itself, and empty for a root facility. It is resolved through
`FacilityRepositoryPort::findAncestors()` (a single upward recursive CTE over
PUBLISHED records, mirroring `findDescendants()` in the opposite direction).

Both the legacy and canonical **list** providers deliberately leave `path` at its
default empty array — populating it per row would be an N+1 ancestor lookup per
page. `FacilitySerializationGroup::READ` is shared across every operation (there
is no detail-only serialization group in this module today), so the split is
enforced by the providers, not by the wire contract.

### Attachments (R11b, floor plans Phase 3)

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/facilities/{facilityId}/attachments` | Upload a multipart file attachment. Optional `kind` field (`document`, the default, or `floor_plan`) |
| GET | `/api/facilities/{facilityId}/attachments` | List a facility's attachments (optional `?kind=document\|floor_plan` filter) |
| GET | `/api/facility-attachments/{id}` | Get one attachment |
| DELETE | `/api/facility-attachments/{id}` | Delete an attachment (requires `If-Match: "revision-N"`) |
| POST | `/api/facility-attachments/{id}/primary` | Promote a `floor_plan` attachment to the facility's primary plan |

Generalized file attachments on a facility, mirroring the proven
`Equipment\...\EquipmentAttachment` slice and the shared attachment kernel
(`src/Shared/MODULE.md`): `Facility\Domain\Model\Attachment\FacilityAttachment`
aggregate, `FacilityAttachmentRepositoryPort`/`FacilityAttachmentRepository`,
`AddFacilityAttachment`/`DeleteFacilityAttachment`/`ListFacilityAttachments`/
`SetPrimaryFacilityAttachment` use cases, and a multipart
`FacilityMediaProcessor`/`FacilityMediaProvider` pair plus
`SetPrimaryFacilityAttachmentProcessor` (`FacilityAttachmentResource`, no
serialization-group-filtered JSON body on upload — `deserialize: false`).
Storage key: `facility/{facilityId}/attachments/{attachmentId}_{fileName}`
via `Shared\Domain\Attachment\StoragePathScheme`. MIME/size validated by
`Shared\Presentation\Api\Attachment\MultipartAttachmentGuard` before any
bytes are read. Write-then-persist with storage rollback on DB failure
(mirrors `AddAttachmentHandler`); delete removes the stored object then the
row. No new permissions: reuses `organization.facilities.read` /
`organization.facilities.write`. A facility may carry at most
`Shared\Domain\Attachment\AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT`
(**25**) attachments — `AddFacilityAttachmentHandler` reads the count through
`FacilityAttachmentRepositoryPort::countByFacilityId()` before writing
anything to storage, and the shared
`AttachmentConstraintExceptionSubscriber` maps the resulting
`InvalidAttachmentException` centrally to **422**, the same status as a
MIME/size rejection.

**Floor plans (Phase 3).** `Facility\Domain\ValueObject\AttachmentKind`
(`document` default | `floor_plan`) extends every attachment with `kind`,
`isPrimaryPlan`, `imageWidth`, `imageHeight`. A `floor_plan` is restricted to
`image/png`, `image/jpeg`, `image/webp`, `image/svg+xml` — a narrower-but-
wider list than the shared `AttachmentCategory::IMAGE` (drops `image/gif`,
adds `image/svg+xml`, which the shared category deliberately excludes for
generic uploads because an SVG can carry active content). The upload
processor resolves `kind` from the multipart request and passes the
kind-specific allow-list into `MultipartAttachmentGuard::fromRequest()`
(`$allowedMimeTypes`, an optional override added for this purpose — `null`
keeps every other module's upload path unchanged). The MIME↔kind invariant
is enforced a second time, defense-in-depth, inside
`FacilityAttachment`'s own constructor (`InvalidAttachmentException`, 422)
— domain state can never exist in an inconsistent combination even if a
future caller bypasses the guard.

Pixel dimensions are probed server-side from the bytes already in memory —
no filesystem access — by `Facility\Domain\ValueObject\ImageDimensions`:
raster formats via `getimagesizefromstring()`; SVG via a regex read of the
`<svg>` tag's `width`/`height` attributes, falling back to `viewBox`,
deliberately NOT a full XML parse (smaller attack surface against untrusted
SVG). An SVG authored with percentage or other CSS-unit dimensions, or with
none of the above, yields `imageWidth`/`imageHeight: null` — never rejected,
just undimensioned. A `document` attachment always carries null dimensions.

**Primary plan.** `POST /facility-attachments/{id}/primary` — a POST
verb-action route on the existing `/facility-attachments/{id}` collection,
mirroring `/facilities/{id}/archive` and `/facilities/{id}/move` rather than
introducing a `PATCH` convention this module does not otherwise use.
`SetPrimaryFacilityAttachmentHandler` validates
`FacilityAttachment::markAsPrimary()` (refuses a `document` attachment with
`FacilityAttachmentNotFloorPlanException`, mapped to **409**) BEFORE opening
a transaction, then atomically — same DB transaction, via
`facility.main_transaction_manager` — clears the previous primary's flag
(`FacilityAttachmentRepositoryPort::clearPrimaryPlan()`) and persists the
new one. A partial unique index, `uniq_facility_attachment_primary_plan
ON facility_attachments (facility_id) WHERE is_primary_plan` (Doctrine's ORM
attributes cannot express a partial index — the same
`uniq_intervention_attachment_signature` precedent), is the schema-level
backstop should the two writes ever land out of order.

## Permission Model

This module relies on Organization-scoped permissions:

- `organization.facilities.read`
- `organization.facilities.write` (also covers attachments — see above)

### Scope versus entitlement (403 vs 404)

Every provider and processor in this module answers a denial in one of two
ways, and which one is not a stylistic choice:

| Caller | Response |
| --- | --- |
| Active member of the owning organization, lacking the permission | `403 Forbidden` |
| No active membership in the owning organization | `404 Not Found`, identical to the route's own not-found response |

The 404 is not a softer 403. These surfaces take their `organizationId` from
the URI — or resolve it from a record they just loaded by path id — **before**
they know whether the caller belongs to that organization, so a 403 at that
point confirms the organization or the record exists to someone who may not
learn even that much. That is an existence oracle: it lets a caller from
another organization enumerate valid identifiers. The out-of-scope 404
therefore reuses the *same* message the route's own "unknown id" branch
produces, so the two responses are indistinguishable.

The distinction is carried by
`Organization\Application\Port\Inbound\OrganizationAuthorizationPort::resolveAccess()`,
which returns `OrganizationAccessDecision` — `GRANTED`, `MISSING_PERMISSION`
or `OUTSIDE_SCOPE`. The membership lookup only runs when the permission is not
granted, so the authorized path costs no extra query. The flat
`hasPermission()` boolean cannot express the middle case and must not be used
for a new check here.

`tests/Architecture/Unit/PresentationAuthorizationEnforcementTest` is the
ratchet that keeps new providers and processors on this path — it fails on any
file under `Presentation/Api` that injects the port and still calls
`hasPermission()`.

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
- `facility_attachments` gains `kind` (`VARCHAR(20) NOT NULL DEFAULT
  'document'`), `is_primary_plan` (`BOOLEAN NOT NULL DEFAULT false`),
  `image_width`/`image_height` (`INT NULL`), plus the partial unique index
  `uniq_facility_attachment_primary_plan ON facility_attachments (facility_id)
  WHERE is_primary_plan` — Migration: `migrations/main/Version20260816110904.php`
  (Facility plan Phase 3).

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
  - `Application/UseCase/Command/Attachment/{Add,Delete,SetPrimary}FacilityAttachment`,
    `Application/UseCase/Query/Attachment/ListFacilityAttachments` — handler
    behavior including storage-write-then-persist rollback on DB failure,
    path-traversal-safe file naming, the atomic primary swap, and the
    document-cannot-be-primary refusal.
  - `Domain/ValueObject/AttachmentKindTest`, `Domain/ValueObject/ImageDimensionsTest`,
    `Domain/Model/Attachment/FacilityAttachmentTest` — the kind↔MIME and
    kind↔primary invariants, and the raster/SVG/no-dimensions probing paths.
  - `Presentation/Api/Processor/Attachment/{FacilityMediaProcessorTest,SetPrimaryFacilityAttachmentProcessorTest}`,
    `Presentation/Api/Provider/Attachment/FacilityMediaProviderTest` —
    permission enforcement, the `If-Match` revision guard on delete, and the
    409/404 mapping on the primary-plan route.
- Integration (real database):
<<<<<<< HEAD
  `tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/FacilityAttachmentRepositoryTest`,
  `FacilityRepositoryTest::testFindAncestorsWalksTheParentChainRootFirstAndExcludesDraftAncestors`
  (root facility, 3-level chain, draft ancestor exclusion),
  `Presentation/Api/Provider/Facility/CanonicalFacilityProviderTest` (item route
  `path` mapping, collection left empty).
- Functional: `tests/Functional/Api/FacilityAttachmentApiTest.php`;
  E2E `tests/E2E/FacilityCoordinatesFlowTest.php` and
  `tests/E2E/FacilityPresentationFlowTest.php` assert the `path` shape on the
  organization-scoped and canonical detail reads.
=======
  `tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/FacilityAttachmentRepositoryTest`
  — round-trips the new columns and proves the partial unique index (a second
  `is_primary_plan = true` row for the same facility is rejected at the DB).
- Functional: `tests/Functional/Api/FacilityAttachmentApiTest.php` — floor
  plan upload (happy path + wrong-MIME 422), `?kind=` list filter, and the
  primary-plan route (happy, swap, document-refusal 409, cross-org 404,
  missing-permission 403).
>>>>>>> 2de9259f (feat(facility): floor plan attachments with kind, dimensions and primary selection)
- Run module tests: `make test tests/Unit/Facility/`
