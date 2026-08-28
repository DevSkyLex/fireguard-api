# Equipment Module

## Overview

Equipment manages the fire safety asset inventory of an organization. It tracks
physical fire safety equipment (extinguishers, smoke detectors, sprinklers, fire
alarm panels, hydrants, cameras, etc.) through a full operational lifecycle.

Main goals:

- Maintain a registry of fire safety equipment scoped to an organization.
- Enforce a controlled status machine for operational lifecycle management.
- Support facility assignment, free-form tagging, and file attachments.

## API Endpoints

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/organizations/{organizationId}/equipment` | Create equipment |
| GET | `/api/organizations/{organizationId}/equipment` | List equipment (filters: `facilityId`, `type`, `status`, `brand`, `model`, `subType`, `search`, `maintenanceDueStatus`) |
| GET | `/api/organizations/{organizationId}/equipment/kpis` | Get the four headline equipment KPI counters (L2.11): `totalAssets`, `compliant`, `dueSoon`, `openNonConformities` |
| GET | `/api/organizations/{organizationId}/equipment/{equipmentId}` | Get equipment |
| PATCH | `/api/organizations/{organizationId}/equipment/{equipmentId}` | Update equipment fields |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/assign` | Assign to a facility |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/unassign` | Remove from current facility |
| PUT | `/api/organizations/{organizationId}/equipment/{equipmentId}/plan-position` | Set or clear this equipment's position pinned on a floor plan attachment (Phase 4) |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/commission` | Mark as `operational` |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/maintenance` | Mark as `under_maintenance` |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/decommission` | Permanently decommission |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/tags` | Add (or create) a tag |
| DELETE | `/api/organizations/{organizationId}/equipment/{equipmentId}/tags/{tagId}` | Remove a tag |
| GET | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments` | List attachments |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments` | Upload attachment (base64 JSON — see below) |
| DELETE | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}` | Delete attachment |
| GET | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}/download` | Download an attachment's raw bytes (`Content-Disposition: attachment`, never inline — see below) |
| POST | `/api/media` | Canonical multipart upload, shared with the intervention offline/field-evidence flow (`equipment`/`intervention`/`clientId`/`file`/`label` fields — see below) |
| GET / DELETE | `/api/media/{id}` | Read / delete a canonical media attachment |
| GET | `/api/organizations/{organizationId}/equipment/export` | Streams a bounded CSV export of every equipment item in the organization — see below |
| GET | `/api/organizations/{organizationId}/equipment/{equipmentId}/report` | Streams a PDF equipment sheet (identity, maintenance history, attachment index) — plan-gated, see below |
| GET | `/api/organizations/{organizationId}/equipment/labels` | Streams a printable PDF sheet of QR equipment labels (Avery L7159 grid) — not plan-gated, see below |

Removed 2026-08-20: `GET /api/organizations/{organizationId}/equipment-types` and
`GET /api/organizations/{organizationId}/equipment-statuses` (unconsumed reference
catalogs; the frontend's localized typed registries are the source of these values).

An equipment may carry at most
`Shared\Domain\Attachment\AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT`
(**25**) attachments. `AddAttachmentHandler` reads the count through
`AttachmentRepositoryPort::countByEquipmentId()` before writing anything to
storage; the resulting `InvalidAttachmentException` is mapped centrally to
**422** by the shared `AttachmentConstraintExceptionSubscriber`, covering
both `MediaProcessor` (multipart) and `AddAttachmentProcessor` (base64
JSON) without either mapping it locally.

**Download (closed 2026-08-19).** `DownloadEquipmentAttachmentController`
serves the raw bytes on a dedicated `EquipmentAttachmentContentResource`
(`read`/`write`/`deserialize`/`serialize`/`output` disabled, mirroring
`Facility\...\FacilityAttachmentContentResource` /
`Intervention\...\InterventionAttachmentContentResource`). The coarse
`organization.equipment.read` permission — the same gate
`ListEquipmentAttachmentsProvider` already applies — is checked in the
controller, since the nested route already carries `organizationId` as a URI
variable; the per-record ownership chain (the equipment belongs to that
organization, the attachment belongs to that equipment) is delegated to
`GetEquipmentAttachmentContentHandler`, dispatched through the query bus, so
a resource-level permission check alone can never stand in for it. The bytes
are always handed to the shared `Shared\Presentation\Api\Attachment\AttachmentDownloadResponder`,
which forces `Content-Disposition: attachment` and
`X-Content-Type-Options: nosniff` — never `inline` — so a malicious upload
(e.g. an SVG carrying a `<script>` tag) is downloaded, never rendered/executed
in the app's origin.

**MIME/size validation (closed 2026-08-19).** Equipment now routes both
upload paths through the same shared policy every other generalized
attachment consumer (Facility, Intervention, Inspection, Messaging) enforces
— `Shared\Domain\Attachment\AttachmentConstraints` (10 MiB cap, the shared
image+document MIME allow-list: `image/jpeg`, `image/png`, `image/webp`,
`image/gif`, `application/pdf`):

- **`MediaProcessor`** (`POST /media`, multipart) now injects
  `Shared\Presentation\Api\Attachment\MultipartAttachmentGuard` — the exact
  kernel `FacilityMediaProcessor`/`InterventionMediaProcessor`/
  `InspectionMediaProcessor`/`MessagingMediaProcessor` already route
  through — and calls `fromRequest($request)` to extract AND validate the
  uploaded file (size read from filesystem metadata before any content is
  read into memory), **after** the equipment/intervention resolution and the
  `assertWrite()` permission check, and **after** the `clientId` idempotent-
  retry short-circuit (a replayed upload with an already-persisted client id
  returns the existing record without re-reading or re-validating the file).
  A violation surfaces as the guard's own **422**.
- **`AddAttachmentProcessor`** (`POST .../equipment/{id}/attachments`,
  base64 JSON) carries no multipart `Request` for the guard to extract from,
  so it calls `AttachmentConstraints::validate($data->mimeType,
  strlen($contents))` directly on the decoded payload — the identical policy
  and reason codes (`mime` / `size`), just applied to bytes that arrived
  JSON-encoded instead of multipart-encoded — and maps the resulting
  `InvalidAttachmentException` to **422** itself (the same translation the
  guard performs internally), since this validation runs before the command
  bus dispatch and is never bus-wrapped.

**Wire-shape decision.** `AddAttachmentInput` (`fileName`/`content` base64/
`mimeType`/`label`) was deliberately KEPT rather than aligned to the
multipart shape used by every sibling module: `fireguard-sso-web`'s
`EquipmentService.addAttachment()` (`data-access/services/equipment/
equipment.service.ts`) posts this exact base64 JSON payload today, so
changing the wire shape would be a breaking change requiring a coordinated
frontend release. The base64 path is validated with the same MIME/size
policy instead. **The plan's 2.4 frontend equipment-attachments UI lot
should target this endpoint AS-IS (base64 JSON `AddAttachmentInput`), not
the multipart shape** — `EquipmentService.uploadEvidence()` (`POST
/api/media`) remains the separate canonical/offline-evidence path, already
multipart, unchanged by this work.

**CSV export (added 2026-08-27).** `GET .../equipment/export`
(`EXPORT_EQUIPMENTS`, resolved on a dedicated `EquipmentExportResource` — a
separate resource rather than a fourth operation on `EquipmentResource`,
because that resource's `GET_EQUIPMENT` operation carries no `{equipmentId}`
format requirement and an `export` literal segment would otherwise collide
with it, exactly the reason `EquipmentKpiResource` is already separate)
streams a synchronous CSV (no 202+poll), mirroring
`Intervention\...\ExportInterventionsController`. `ExportEquipmentsController`
resolves `organizationId` off the URI (this module's routes are
organization-path-scoped, unlike Intervention's query-parameter
`organization`), dispatches `ExportEquipmentsQuery` through the query bus, and
streams the CSV via `EquipmentCsvWriter`. `ExportEquipmentsHandler` resolves
`organization.equipment.read` through `OrganizationAuthorizationPort` —
`EquipmentNotFoundException::forOrganizationScope()` (404) when the caller is
outside the organization, `EquipmentAccessDeniedException` (403) when the
permission is missing — then bounds the request with a cheap
`EquipmentRepositoryPort::countEquipments()` before fetching a single row,
rejecting with `EquipmentExportTooLargeException` (422) past
`ExportEquipmentsHandler::MAX_EXPORT_ROWS` (50 000). **Deliberately
unfiltered**: unlike the Intervention export, which replicates the list
endpoint's filter subset, this export always scopes to the whole organization
— it doubles as the full-organization backup/reimport source for the Import
module's bulk CSV import, so filtering it down to whatever the caller has the
list page currently filtered on would silently produce an incomplete backup.
A successful export dispatches `EquipmentsExportedEvent`
(`organizationId`/`actorUserId`/`format`/`rowCount`); the Audit module wires
its own subscriber to turn that into an `equipment.list_exported` ledger
entry — not wired here, matching the layering every other module's own
`*ExportedEvent` follows.

**PDF equipment sheet (plan-gated, added 2026-08-27).**
`GET .../equipment/{equipmentId}/report` (`EXPORT_EQUIPMENT_REPORT`, on a
dedicated `EquipmentReportExportResource` for the same route-collision reason
as the CSV export) streams a synchronous PDF on the shared PDF socle
(`templates/pdf/layout.html.twig`, translator domain `pdf`,
`OrganizationDocumentBrandingPort` letterhead + regional date formatting,
`DompdfEquipmentReportRenderer` with `isRemoteEnabled`/`isPhpEnabled` off and
canvas `page_text()` pagination). `ExportEquipmentReportController` reuses
the module's existing read queries only — `GetEquipmentQuery` (identity,
tags, facility name, maintenance due status), `ListMaintenanceLogsQuery`
(history, bounded to 100 rows) and `ListEquipmentAttachmentsQuery` (names and
metadata, never blobs). Linked non-conformities are deliberately absent: no
per-equipment non-conformity port exists (`NonConformityStatisticsPort` is
organization-wide only), and creating one would be new business logic.

**Decision — entitlement gate.** The sheet is reserved to the `pro`/`max`
plans, exactly like the Compliance safety register: the controller checks
`EquipmentReportEntitlementPort` (aliased to the SAME Organization adapter,
`OrganizationExportEntitlementAdapter`, one plan allow-list for every PDF
export) and answers a dedicated **403**
(`EquipmentReportNotEntitledException::planTooLow`) when the plan is lower.
This deliberately does **not** mirror the intervention report
(`GET /api/interventions/{id}/report`), which predates the decision and
remains ungated — new document exports align on the gated register.
Authorization first: `resolveAccess()` with `organization.equipment.read`,
`OUTSIDE_SCOPE` → **404**, `MISSING_PERMISSION` → **403**, entitlement
checked only after that split. A successful export dispatches
`EquipmentReportExportedEvent` (equipment, organization, actor, plan key);
the Audit module's own subscriber records it as `equipment.report_exported`.

**CSV column contract — the import round-trip.** `EquipmentCsvWriter::HEADER`
is a `public` constant, and its first six columns
(`type`, `subType`, `brand`, `model`, `serialNumber`, `locationLabel`, in that
exact order) are a published contract: they are the same six columns, in the
same order, that `Import\Application\Service\EquipmentRowFactory` reads back
by column *name* (not position — the importer maps by header, so reordering
is actually safe for the importer itself, but the position is still frozen
here to keep the two sides human-comparable) on a bulk CSV reimport. Every
column after the sixth (`id`, `status`, `facilityId`, `facilityName`,
`installedAt`, `commissionedAt`, `createdAt`, `updatedAt`) is read-only
metadata the importer ignores. The frozen slice is asserted by
`tests/Unit/Equipment/Presentation/Api/Service/EquipmentCsvWriterTest.php`.

**QR label sheet (added 2026-08-28).** `GET .../equipment/labels`
(`EXPORT_EQUIPMENT_LABELS`, on a dedicated `EquipmentLabelSheetResource` for
the same route-collision reason as the CSV export: `labels` is a literal
segment under `/equipment/`) streams a synchronous PDF of printable QR
stickers. Selection is mutually exclusive: `ids[]` (an explicit equipment
list, one label each), `facilityId` (every equipment item of one facility),
or neither (the whole organization park); providing both is a **400**, an
explicitly empty `ids[]` is a **400** too (silently falling back to the whole
park on a bad parameter would print hundreds of unwanted labels).
`ExportEquipmentLabelsHandler` resolves `organization.equipment.read`
(`OUTSIDE_SCOPE` → **404**, `MISSING_PERMISSION` → **403**), then bounds the
request with `EquipmentRepositoryPort::countEquipmentLabelCandidates()`
before fetching a row, rejecting with
`EquipmentLabelExportTooLargeException` (**422**) past
`ExportEquipmentLabelsHandler::MAX_LABELS` (**500** — ~21 A4 pages; beyond
that it is a bulk print job to split per facility). Identifiers outside the
organization never match: the repository always applies the organization
filter, so a foreign id silently yields no label rather than leaking
anything.

**QR payload — the scan contract.** Each label's QR encodes the equipment's
canonical relative IRI **`/api/equipment/{id}`** — byte-for-byte the first
form the frontend's
`InterventionDiscoveryService.normalizeScannedTarget()` accepts verbatim
(`fireguard-sso-web/src/app/features/organization/features/interventions/services/intervention-discovery/intervention-discovery.service.ts`;
it also normalizes a bare UUID and a full URL by its pathname — the relative
IRI is the deterministic choice, independent of whichever host the app is
deployed on). QR codes are generated by `bacon/bacon-qr-code` (`^3.1`, pure
PHP, BSD-2-Clause, no image extension) inside
`DompdfEquipmentLabelSheetRenderer`, error-correction level **M**, as SVG
injected into the HTML as a base64 `data:image/svg+xml` `<img>` —
**measured**: dompdf 3.1 silently drops inline `<svg>` elements (empty page
content stream) while the same SVG through an `<img>` data URI renders as
vector paths via php-svg-lib.

**Sheet geometry — Avery L7159.** `templates/equipment/labels.html.twig`
deliberately does **not** extend `pdf/layout.html.twig`: the common layout
paints a fixed header/footer inside the page body, which would print across
the top and bottom sticker rows. The template owns its own skeleton and maps
the physical die-cut grid of an Avery L7159 / J8159 sheet exactly: A4,
24 labels of 63.5 × 33.9 mm in 3 columns × 8 rows, horizontal pitch 66 mm
(2.5 mm gutters), vertical pitch 33.9 mm (no row gap), side margins 7.25 mm,
top/bottom margins 12.9 mm (the `@page` margins ARE the sheet margins). Each
label carries the QR (24 mm), the type/sub-type, the serial number and the
facility/location in small print. Page numbering is deliberately absent — a
sheet is cut apart.

**Decision — no entitlement gate.** Unlike the equipment sheet and the
safety register (both `pro`/`max`), the label sheet checks **no plan**: the
QR labels are the physical half of the field scan loop, which is itself
ungated (`InterventionDiscoveryService` and the intervention endpoints carry
no plan gate), so gating the sheet would break the core scan workflow for
lower plans. The gated exports are reporting deliverables; a label is
operational material. A successful export dispatches
`EquipmentLabelsExportedEvent`
(`organizationId`/`actorUserId`/`selection`/`labelCount`); the Audit module's
own subscriber records it as `equipment.labels_exported` (metadata: selection
mode name and label count, never the selected identifiers).

## Flows

### Create Equipment (Command)

```mermaid
sequenceDiagram
  participant API as CreateEquipmentProcessor
  participant Bus as CommandBusPort
  participant UC as CreateEquipmentHandler
  participant Repo as EquipmentRepositoryPort
  API->>Bus: dispatch(CreateEquipmentCommand)
  Bus->>UC: __invoke(Command)
  UC->>Repo: save(Equipment)
  UC-->>Bus: CreateEquipmentResult
```

### Commission Equipment (Command)

```mermaid
sequenceDiagram
  participant API as CommissionEquipmentProcessor
  participant Bus as CommandBusPort
  participant UC as CommissionEquipmentHandler
  participant Repo as EquipmentRepositoryPort
  API->>Bus: dispatch(CommissionEquipmentCommand)
  Bus->>UC: __invoke(Command)
  UC->>Repo: findById(equipmentId)
  UC->>UC: equipment.commission()
  UC->>Repo: save(Equipment)
  UC-->>Bus: CommissionEquipmentResult
```

### List Equipment (Query)

```mermaid
sequenceDiagram
  participant API as ListEquipmentsProvider
  participant Bus as QueryBusPort
  participant UC as ListEquipmentsHandler
  participant Repo as EquipmentRepositoryPort
  API->>Bus: ask(ListEquipmentsQuery)
  Bus->>UC: __invoke(Query)
  UC->>Repo: findAllByOrganizationId(...)
  UC-->>Bus: ListEquipmentsResult
```

## Permission Model

This module relies on Organization-scoped permissions:

- `organization.equipment.read`
- `organization.equipment.write` (also covers tags and attachments)

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

Aggregates and entities:

- `Equipment` — aggregate root
- `Tag` — lightweight label scoped to an organization, associated to equipment items
- `EquipmentAttachment` — file attachment linked to an equipment item

`Equipment` main fields:

- `id`
- `organizationId`
- `type` (`fire_extinguisher`, `smoke_detector`, `heat_detector`, `sprinkler`, `fire_alarm_panel`, `hydrant`, `fire_door`, `emergency_lighting`, `access_control`, `camera`, `gas_detector`, `other`)
- `status` (`in_stock`, `operational`, `under_maintenance`, `decommissioned`)
- `facilityId` (optional)
- `subType` (optional)
- `brand`, `model`, `serialNumber` (serialNumber is unique per organization)
- `locationLabel` (optional free-text — the spot *inside* a facility, not the facility)
- `facilityName` (read-only display name of the assigned facility). The module stores only
  `facilityId`; the name is resolved through `FacilityNamingPort`, batched once per listing.
  Deliberately separate from `FacilityValidationPort`: that contract throws, and a label
  lookup must not go through something whose job is to reject writes. Null when unassigned
  or unresolvable — an unresolved name is not a blank name.
- `installedAt`, `commissionedAt` (optional)
- `planPosition` (optional, `{attachmentId, x, y}`, Phase 4 — see "Plan
  position" below). Exposed on the **detail** read
  (`GET .../equipment/{id}`) only, not on the list/collection endpoints.

Status transitions:

- `in_stock` → `operational` (commission)
- `operational` ↔ `under_maintenance` (maintenance / commission)
- `operational` | `under_maintenance` → `decommissioned` (decommission, irreversible)

### Plan position (Phase 4)

Equipment gains an optional `planPosition`, pinning it at one point over a
floor plan attachment belonging to its own facility or one of that
facility's ancestors: `Equipment\Domain\ValueObject\PlanPosition`
(`attachmentId`, `x`, `y` — each coordinate a float normalized to `[0, 1]`, a
fraction of the plan image's width/height, mirroring
`Facility\Domain\ValueObject\PlanGeometry` in shape but Equipment-owned; this
module never imports Facility's Domain). Serialized on
`equipment.plan_position` (`JSONB`, main database, `Version20260816130000`)
as `{"attachmentId": "<uuid>", "x": float, "y": float}`. The free-text
`locationLabel` is untouched — it stays the offline-readable fallback when
no plan/attachment context is available.

**Write — `PUT /organizations/{organizationId}/equipment/{equipmentId}/plan-position`.**
PUT rather than assign/unassign's POST: this is an idempotent full-replace
(or clear-with-null) of one field, the same shape as Facility's own
`PUT .../plan-geometry` sibling endpoint, not a stateful transition with
side effects (maintenance-log closing, status reset) the way assign/unassign
are. `SetEquipmentPlanPositionInput` carries `attachmentId`, `x` and `y`
together: all three present sets or replaces the position, all three `null`
clears it. `SetEquipmentPlanPositionHandler` validates, through ports and
BEFORE the durable save:

1. the equipment exists and belongs to the organization
   (`EquipmentNotFoundException`, **404**),
2. the equipment is assigned to a facility — `EquipmentNotAssignedToFacilityException`,
   **409** — there is no facility whose plan the position could be validated
   against otherwise,
3. the attachment exists, is `kind: floor_plan`, and belongs to the
   equipment's own facility or one of its ancestors, delegated whole to
   `Equipment\Application\Port\Outbound\EquipmentFloorPlanValidationPort`
   (implemented by Facility — see `src/Facility/MODULE.md`'s "Equipment
   plan-position cross-module pair"): `FloorPlanAttachmentNotFoundException`
   (**404**), `FloorPlanAttachmentNotFloorPlanException` /
   `FloorPlanAttachmentNotAncestorException` (both **409**) — all three are
   contract exceptions under `Application/Contract/FloorPlan/`, since
   Facility's adapter throws them across the module boundary,
4. `EquipmentAlreadyDecommissionedException` (**409**) — a decommissioned
   asset is terminal, mirroring every other mutator on the aggregate.

`Equipment::placeOnPlan()` / `removeFromPlan()` are the aggregate mutators;
`removeFromPlan()` is idempotent (clearing an already-unset position is a
success, not an error). `Equipment::unassignFromFacility()` also clears
`planPosition` — a position bound to a facility's plan cannot outlive that
facility assignment; the same invariant is re-applied on the intervention
`apply()` offline path whenever `facility` is cleared (see "Persistence"
below).

**Read.** The equipment DETAIL output only
(`GetEquipmentResult`/`GetEquipmentHandler`, `GET .../equipment/{id}`) —
deliberately left `null` by `ListEquipmentsHandler`, since the shape is
shared between the two but only the single-item read populates it. The
plan-overlay READ that lists equipment pinned on a plan is owned by
Facility: see `src/Facility/MODULE.md`'s "Spatial zone geometry" section for
`GET .../facilities/{facilityId}/plan-overlay`'s `equipment` array, resolved
here through `Equipment\Infrastructure\Adapter\Facility\EquipmentPlanPositionAdapter`.

## Persistence

- Tables: `equipment`, `equipment_tags`, `tags` (main database)
- Doctrine mapping: `src/Equipment/Infrastructure/Persistence/Doctrine/Record`
- Migration (plan position): `migrations/main/Version20260816130000.php` —
  `plan_position JSONB NULL`, hand-written rather than Doctrine-diffed so the
  physical column is `JSONB` (indexable, used by
  `EquipmentPlanPositionAdapter`'s `->>'attachmentId'` filter) while the ORM
  mapping stays the same `json` DBAL type as the rest of the record.
- Repository implementations: `Equipment\Infrastructure\Persistence\Doctrine\Repository`
- The offline/intervention resource adapter
  (`Equipment\Infrastructure\Adapter\Intervention\EquipmentInterventionResourceAdapter`)
  accepts `planPosition` as an additional patchable field — a complete
  `{attachmentId, x, y}` object (validated through the same `PlanPosition`
  VO) or `null`. Clearing the `facility` field in the same or an earlier
  patch also clears `planPosition`, mirroring the aggregate's
  `unassignFromFacility()` invariant; the offline path does not re-validate
  attachment ownership against Facility (no cross-module port call from an
  adapter that must stay usable while genuinely offline) — that check is
  enforced only on the online `PUT .../plan-position` route.
- **Free-text search (R10)**: the `search` filter is pushed down into SQL in
  `EquipmentRepository::createListQueryBuilder()` via the shared
  `Shared\Infrastructure\Doctrine\Search\TrigramSearchExpression` builder —
  never post-filtered in memory. It emits a case-insensitive, wildcard-safe
  `LOWER(col) LIKE :search ESCAPE '\'` OR clause across `type`, `subType`,
  `brand`, `model`, `serialNumber`, `status`, `locationLabel`. The predicate
  shape (`LOWER(col) LIKE ...`) is deliberately aligned with `pg_trgm` GIN
  expression indexes (`idx_equipment_<col>_trgm`) planned for a later,
  index-only migration (R10-index) so `equipment` search stays index-backed
  at scale on PostgreSQL; no such index exists yet, and the predicate is
  fully correct without it (just a sequential scan).

## Architecture

- Presentation: Api Platform resources, providers, processors, DTOs.
- Application: Use cases (command/query), repository ports.
- Domain: Equipment aggregate, Tag, EquipmentAttachment, value objects, domain exceptions.
- Infrastructure: Doctrine record/mapper/repository.

Key folders:
- `src/Equipment/Presentation/Api`
- `src/Equipment/Application/UseCase`
- `src/Equipment/Domain`
- `src/Equipment/Infrastructure`

Cross-module contracts and lifecycle invariants:

- `EquipmentMaintenanceLogSynchronizerPort` (inbound): keeps the maintenance-log
  history in step with flat-surface status writes (canonical processor,
  intervention `apply()`, and draft publication) — a log opens on entering
  `under_maintenance` and closes on leaving it; `commissionedAt` is stamped on
  entering `operational` (preserved on re-commission, never set on drafts).
- In-service equipment (operational or under maintenance) always has a facility:
  clearing the facility while in service is refused on the flat surfaces.
- `Equipment\Infrastructure\Adapter\Facility\FacilityEquipmentDependencyAdapter`
  implements Facility's archival dependency port (active = published and not
  decommissioned).
- **Plan position cross-module pair (Phase 4)**: outbound —
  `Equipment\Application\Port\Outbound\EquipmentFloorPlanValidationPort`,
  consumed by `SetEquipmentPlanPositionHandler`, implemented by Facility
  (`Facility\Infrastructure\Adapter\Equipment\EquipmentFloorPlanValidationAdapter`,
  reusing `FacilityAttachmentAncestryGuard`). The reverse direction —
  `Equipment\Infrastructure\Adapter\Facility\EquipmentPlanPositionAdapter`
  — implements Facility's `FacilityEquipmentPlanPositionPort` for the
  plan-overlay read. See `src/Facility/MODULE.md` for the full pairing,
  including the one file allowed to import both modules' Domain layers to
  satisfy the port's typed `@throws` contract.
- **Per-equipment maintenance due status (L2.10)**: `EquipmentOutput.maintenanceDueStatus`
  (`GET .../equipment` and `GET .../equipment/{id}`) and the `maintenanceDueStatus`
  list filter are resolved cross-module through the new
  `Equipment\Application\Port\Outbound\MaintenanceDueStatusPort` (declared
  here; Equipment references only this port, never Maintenance's Domain or
  Infrastructure). Its adapter,
  `Maintenance\Infrastructure\Adapter\Equipment\EquipmentMaintenanceDueStatusAdapter`,
  is hosted in — and wired by — the Maintenance module (the module owning the
  `maintenance_schedules` read model), mirroring the existing reverse
  direction (`MaintenanceEquipmentDirectoryPort`, implemented here for
  Maintenance). Batching: `GetEquipmentHandler` and `ListEquipmentsHandler`
  each resolve the whole batch of equipment ids they need in ONE call to
  `dueStatusesForEquipment()` — never per row. Equipment ids with no
  maintenance schedule (never reconciled by the Maintenance sweep yet, or
  genuinely untracked) come back as `unscheduled`, never absent, never null.
  The `maintenanceDueStatus` filter cannot be pushed into the `equipment`
  table's SQL `WHERE` clause (the value lives in Maintenance, not here):
  `ListEquipmentsHandler::listFilteredByDueStatus()` instead loads every
  equipment matching the other filters unbounded (capped at
  `DUE_STATUS_FILTER_SCAN_LIMIT = 10_000`, a generous safety net given
  organizations are plan-quota bounded on equipment count — not a practical
  limit), resolves due status for that whole candidate set in one batch call,
  filters in memory, then paginates with `array_slice()`. **Do not assume a
  per-equipment "Non-conformity" status exists.** It does not: non-conformities
  attach to *inspections* (see `src/Inspection/MODULE.md`), never to equipment.
  The API exposes exactly four `MaintenanceDueStatus` values —
  `unscheduled`|`up_to_date`|`due_soon`|`overdue` — and a UI that wants a
  four-label status column must map onto those deliberately rather than invent
  a fifth state.
- **Equipment KPI endpoint (L2.11)**: `GET .../equipment/kpis`
  (`EquipmentKpiResource` / `GetEquipmentKpisProvider` /
  `GetEquipmentKpisHandler`) answers the Equipment page headline
  band — `totalAssets`, `compliant`, `dueSoon`, `openNonConformities` — as a
  single org-scoped call instead of the two endpoints the frontend previously
  had to combine.
  - `totalAssets` is every equipment record for the organization, every
    status included (`EquipmentRepositoryPort::countOverviewByOrganizationId()['total']`,
    no new query).
  - `compliant` / `dueSoon` reuse the L2.10 `MaintenanceDueStatusPort`
    exactly as instructed: the handler loads every equipment id for the
    organization (capped at the same `DUE_STATUS_SCAN_LIMIT = 10_000` safety
    net as `ListEquipmentsHandler::listFilteredByDueStatus()`), resolves due
    status for the whole candidate set in ONE batch call, and tallies
    `up_to_date` / `due_soon` in memory. Decommissioned equipment naturally
    never contributes to either bucket (Maintenance drops its schedule row,
    so it resolves to `unscheduled`), so no extra status filtering is needed.
  - **`openNonConformities` is deliberately ORGANIZATION-WIDE, not
    equipment-scoped** — the honest resolution of the same problem flagged
    under L2.10 above: non-conformities attach to inspections, not to
    equipment, and no reliable per-equipment non-conformity aggregate exists
    anywhere in the codebase. Rather than fabricate a number whose scope is
    unclear, the new
    `Equipment\Application\Port\Outbound\NonConformityStatisticsPort`
    (declared here, one method: `countOpenNonConformities(organizationId)`)
    exposes the same "non-conformities currently `open` or `in_progress`
    across the whole organization" figure the Organization dashboard and the
    Compliance register already surface. Its adapter,
    `Inspection\Infrastructure\Adapter\Equipment\EquipmentNonConformityStatisticsAdapter`,
    is hosted in — and wired by — the Inspection module (the module owning
    `non_conformities`), mirroring the L2.10 hosting convention; it composes
    two existing, already-tested `NonConformityRepositoryPort::countByOrganizationId()`
    calls (`open` + `in_progress`) rather than introducing new DQL, so it is
    covered by a mocked-port unit test, not a new integration test.
- Canonical DELETE = decommission — TERMINAL, never reversible. Idempotent: a
  repeat DELETE is a no-op; an open maintenance log is closed on the way out.
- **Decommissioning is gated by the org's four-eyes approval policy** (R17):
  `DecommissionEquipmentProcessor` consults
  `Approval\Application\Port\Inbound\ApprovalGatePort` (action type
  `equipment_decommission`) BEFORE dispatching `DecommissionEquipmentCommand`.
  If the organization requires approval, the endpoint returns **HTTP 202**
  with a pending approval request summary instead of `EquipmentOutput`, and
  the equipment stays in its current status until a second authorized
  member approves it. Approval defaults OFF (opt-in); see
  `src/Approval/MODULE.md`. `EquipmentDecommissionExecutorAdapter`
  (`src/Equipment/Infrastructure/Adapter/Approval/`) re-dispatches the same
  command on approval — the Equipment Domain never references Approval.
- Regulated actions emit domain events (`src/Equipment/Domain/Event/`)
  recorded in the audit ledger by Audit's `AuditEventSubscriber`:
  `equipment.commissioned`, `equipment.under_maintenance`,
  `equipment.returned_to_stock`, `equipment.decommissioned` (each with
  `previous_status`; in-service events carry `facility_id`). Emission sites:
  the Commission/PutUnderMaintenance/Decommission handlers (which load
  through `findPublishedById` — draft scratchpads are unreachable) and the
  canonical processor, which COLLECTS its events during the mutation and
  dispatches them only after `wrapInTransaction` commits. Idempotent repeats
  emit nothing. The intervention `apply()`/`publishDrafts` path is deferred
  to the `intervention.published` audit action.
- **Intervention service history sync** (R12): `EquipmentMaintenanceLog`
  entries are not only maintenance windows — a completed, point-in-time
  entry (`source = intervention`, `startedAt === completedAt`) is also
  synthesized whenever a published intervention mutates published equipment.
  `Equipment\Infrastructure\EventSubscriber\InterventionServiceHistorySubscriber`
  reacts to `intervention.intervention_published_event` (the same event
  `AuditEventSubscriber` records as `intervention.published`; see the
  Intervention module's audit trail) and dispatches
  `RecordInterventionServiceHistoryCommand` (sync — no async routing) to
  `RecordInterventionServiceHistoryHandler`, which reads back the
  intervention's applied equipment changes through the new outbound
  `Equipment\Application\Port\Outbound\InterventionServiceReportPort`
  (contracts under `Application/Contract/Intervention`; adapter hosted in
  Intervention, mirrors `Facility\Infrastructure\Adapter\Equipment\FacilityValidationAdapter`)
  and calls `EquipmentMaintenanceLog::recordInterventionService(...)` +
  `MaintenanceLogRepositoryPort::appendInterventionServiceEntry(...)` per
  serviced equipment. The subscriber is **best-effort**: it swallows every
  `Throwable` (logged, never rethrown) exactly like `AuditEventSubscriber`
  and `AutomationTriggerSubscriber`, since an uncaught exception here would
  abort the whole published-event fan-out and break the audit ledger row
  emitted by the same event. Idempotent via a `dedup_key` unique column on
  `equipment_maintenance_logs` (`sha1('intervention_change:' . appliedChangeId)`),
  inserted through a raw DBAL statement guarded by
  `UniqueConstraintViolationException`
  (`Equipment\Infrastructure\Persistence\Doctrine\Repository\MaintenanceLogRepository::appendInterventionServiceEntry`)
  — mirrors `AutomationRunRepository::reserveRun`, so a duplicate (at-least-once
  event redelivery, or a later publication re-reading an already-applied
  change) is a routine no-op that never poisons the EntityManager. New
  nullable fields on `EquipmentMaintenanceLog` /
  `equipment_maintenance_logs`: `source` (`status_transition` default |
  `intervention`), `interventionId`, `interventionNumber`, `workItemAction`
  (the linked work item's action, or a derived `status_change`/`update`
  fallback), `actorId` (the intervention's `responsibleId`, nullable), and
  `summary`. Surfaced read-only through the existing
  `GET /organizations/{organizationId}/equipment/{equipmentId}/maintenance-logs`
  endpoint (`ListMaintenanceLogsHandler` / `MaintenanceLogOutput`) — no new
  endpoint or permission. At-most-once delivery caveat: `publish()` only
  fires `InterventionPublishedEvent` on the delivery that durably transitions
  the publication, so a worker crash after commit but before the subscriber
  finishes loses that publication's service entries; acceptable for
  best-effort service history and consistent with the existing post-commit
  notification behavior.
- **Bulk CSV import (R13)**: `Equipment\Application\Port\Inbound\EquipmentProvisioningPort`
  is a new inbound port, hosted in this module, that lets another module
  (Import's bulk CSV import) provision one piece of equipment
  programmatically. Its implementation, `EquipmentProvisioningService`
  (`Application/Service`), dispatches the existing `CreateEquipmentCommand`
  through `CommandBusPort` — the same synchronous path the HTTP API uses, so
  the transactional plan-quota check runs intact — and translates every
  failure (`OrganizationQuotaExceededException`,
  `EquipmentSerialNumberAlreadyExistsException`, `InvalidArgumentException`,
  each raised directly or wrapped in `MessengerRuntimeException`) into a
  typed `ProvisionOutcome` (`CREATED`|`QUOTA_EXCEEDED`|`INVALID`) instead of
  rethrowing, so a caller processing many rows can continue past a single
  failed one. Mirrors `Intervention\Application\Port\Inbound\InterventionDraftFactoryPort`.
  See `src/Import/MODULE.md`.
- **Bulk CSV import v2 — dry-run mode**: `ProvisionEquipmentRequest` carries
  an optional `dryRun` (default `false`) and `quotaProjectionOffset` (default
  `0`), threaded onto `CreateEquipmentCommand`. `CreateEquipmentHandler`
  still builds and validates the `Equipment` aggregate under `dryRun`, but
  skips the transactional save and calls
  `OrganizationQuotaPort::assertProjectedCanAdd()` instead of
  `assertCanAdd()` — a lock-free projection (`getLimit()`/`getUsage()` plus
  the caller's offset) meant for a caller, such as Import's dry run, that
  persists nothing and has no insert to serialize an advisory lock against.
  See `src/Import/MODULE.md`'s dry-run section and `src/Facility/MODULE.md`
  for the sibling implementation.

**Architecture debt — `Presentation` reaching into a sibling's `Infrastructure` (1).**
Down from 3 on 2026-08-26. `CanonicalEquipmentMutationProcessor`'s
`OrganizationRecord` uses were `instanceof` checks on a property the ORM already
types `?OrganizationRecord`; `CanonicalEquipmentProvider`'s was an existence
lookup that `resolveAccess()` had made redundant — OUTSIDE_SCOPE answers the
same 404 for an organization with no membership row, and an unknown id
necessarily has none. The `=> Organization` pair is gone from the baseline.

**One real read remains**: `CanonicalEquipmentMutationProcessor` reads
`Facility\…\Record\FacilityRecord` to validate the assignment target. Unlike the
organization case, no permission gate covers it — the target facility's
existence is the actual question — so closing it needs Facility to publish an
`Application\Port\Inbound` lookup port. Do not add a second.

That one aside, the coupling underneath is untouched: `EquipmentRecord` still
declares `#[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]`, which is
schema-level.

### The canonical equipment mutations run on use cases (2026-08-26)

`CanonicalEquipmentMutationProcessor` was 385 lines holding three
`persist`/`flush`/`remove` sites, the published status machine, the
draft/published split, the `commissionedAt` stamp, the maintenance-log sync,
the four audit events and their post-commit dispatch. It is now HTTP
translation only, and **holds no entity manager**.

| Concern | Where it lives now |
| --- | --- |
| `PATCH /api/equipment/{id}` | `Application/UseCase/Command/Equipment/PatchCanonicalEquipment/` |
| `DELETE /api/equipment/{id}` | `Application/UseCase/Command/Equipment/DeleteCanonicalEquipment/` |
| Read one, for the gate | `Application/UseCase/Query/Equipment/GetCanonicalEquipment/` |
| Status machine, draft/published split, `commissionedAt`, in-service rule, revision bump, idempotent retire | `Domain/Model/Equipment/CanonicalEquipment` |
| Persistence | `Infrastructure/…/Repository/CanonicalEquipmentRepository` (port: `CanonicalEquipmentRepositoryPort`) |
| Intervention revision touch | `Equipment\Application\Port\Outbound\InterventionScopePort` |

**Two Domain models over one table, on purpose** — the same split the
Inspection module made on the same day, for the same reason: the `Equipment`
aggregate does not carry `record_status`, `intervention_id` or `revision`, so
saving it can never bump the revision the canonical `If-Match` contract is
built on. `src/Inspection/MODULE.md` carries the long-form account.

**`type` is deliberately NOT narrowed to `EquipmentType`.**
`PatchCanonicalEquipmentInput::$type` carries `#[Assert\Length(max: 32)]`, not
`#[Assert\Choice]`, so this surface has always accepted a type outside the
enum and written it through. Modelling it as the enum would turn today's 200
into a 422 — a contract change, not a refactor's side effect. `status` IS an
enum, because its DTO field always had `#[Assert\Choice]`.

**The validation order is load-bearing** and is now split across two objects
so it can stay what it was: `CanonicalEquipmentPatch::assertNonNullableFieldsArePresent()`
rejects a null `type` then a null `status`, the handler then checks the
facility's organization, and only then does the model apply the patch and run
the in-service and transition rules. A request carrying several mistakes at
once gets the same message it got before.

**The in-service rule fires on every patch**, not only on a status change: a
request that merely clears the facility of an operational asset is exactly
the one it rejects (`In-service equipment must be assigned to a facility.`).

**The canonical DELETE contract**, unchanged and now stated by
`DeleteCanonicalEquipmentHandler`: a draft scratchpad row is hard-deleted; a
published one retires to `decommissioned` — TERMINAL and never reversible,
unlike the inspection surface's `cancelled` — closing any still-open
maintenance log; a repeat DELETE is an idempotent no-op that does **not** bump
the revision, sync the log or reach the ledger.

**Audit events are still dispatched after the commit**, now by the handlers.
The ledger is on the `auth` database and commits independently, so an event
dispatched inside the transaction could describe a mutation `main` then rolled
back — a phantom row in an append-only, hash-chained ledger.
`PatchCanonicalEquipmentHandlerTest::testARolledBackMutationAuditsNothing`
freezes it.

**What deliberately stayed in the processor**: the authorization gate (the
permission depends on the request, and a row loaded by GLOBAL id must answer
404 rather than 403 outside the caller's organization), `MergePatchFields`
plus the facility IRI parse (absent-key versus explicit-null is a fact about
the HTTP body a deserialized DTO has lost; an IRI is transport, an identifier
is not), and the output (`CanonicalEquipmentProvider` joins tags and facility
names the write path has no reason to carry).

## Configuration

- Service wiring: `config/modules/equipment.yaml`
- `CanonicalEquipmentRepositoryPort` is aliased to `CanonicalEquipmentRepository`,
  wired to `main` explicitly. It is a **second** port over the `equipment`
  table, next to `EquipmentRepositoryPort` — see the architecture section for
  why one cannot serve both.
- `Equipment\Application\Port\Outbound\InterventionScopePort` is aliased to
  `Intervention\Infrastructure\Adapter\Equipment\InterventionScopeAdapter`.
  It is a **twin** of the Inspection module's port of the same name: each
  consumer owns its own, exactly as the two `FacilityValidationPort`s coexist.
- `PatchCanonicalEquipmentHandler` and `DeleteCanonicalEquipmentHandler` name
  `@equipment.main_transaction_manager` explicitly; autowiring would open a
  transaction on the `auth` connection while the writes go to `main`.
- `CanonicalEquipmentMutationProcessor` names **no** `$entityManager`, and
  must not: it holds none.
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`
- `MaintenanceDueStatusPort`'s adapter is aliased in `config/modules/maintenance.yaml`
  (adapter hosted in the Maintenance module), not here — see L2.10 above.
- `NonConformityStatisticsPort`'s adapter is aliased in `config/modules/inspection.yaml`
  (adapter hosted in the Inspection module), not here — see L2.11 above.
- `EquipmentFloorPlanValidationPort` is aliased to
  `Facility\Infrastructure\Adapter\Equipment\EquipmentFloorPlanValidationAdapter`
  here (this module owns the port); the adapter service itself is also
  registered here even though it lives in Facility's source tree, matching
  the existing `FacilityValidationAdapter` precedent.
- `Equipment\Infrastructure\Adapter\Facility\EquipmentPlanPositionAdapter`
  (implements Facility's `FacilityEquipmentPlanPositionPort`) is wired with
  `doctrine.orm.main_entity_manager` here; the port alias itself is
  registered in `config/modules/facility.yaml` (the port's owning module).
- CSV export: `Equipment\Application\UseCase\Query\ExportEquipments\ExportEquipmentsHandler`
  is tagged `messenger.message_handler`. It touches Doctrine only through the
  already-wired `EquipmentRepositoryPort`/`FacilityNamingPort` aliases, so it
  needs no `$entityManager` of its own; same for `ExportEquipmentsController`,
  which reaches Doctrine only via the query bus and is covered by the
  `Equipment\Presentation\:` resource autowiring.

## Testing

- Unit: `tests/Unit/Equipment/`
  - `Domain/Model/Equipment/CanonicalEquipmentTest` — the canonical rules with
    no container and no mocks: the transition table, the `commissionedAt`
    stamp and its survival across a re-commission, the in-service rule firing
    on every patch, the scratchpad bypass, `type`-before-`status` validation
    order, explicit-null erasure versus absent key, the idempotent retire that
    must not bump the revision, and the unknown `type` that must still be
    accepted.
  - `Application/UseCase/Command/Equipment/{Patch,Delete}CanonicalEquipment`
    and `Application/UseCase/Query/Equipment/GetCanonicalEquipment` — the
    orchestration: which event fires for which transition, the maintenance-log
    sync and the three paths that must sync and audit **nothing** (scratchpad,
    no-status-change, idempotent repeat DELETE), the post-commit guarantee,
    the null-field-before-facility ordering, and the revision re-check inside
    the handler's own transaction.
  - `Presentation/Api/Processor/Equipment/CanonicalEquipmentMutationProcessorTest`
    — what the processor still owns: the gate order (404 before 428), which
    permission a scratchpad row asks the intervention for, 404 rather than 403
    outside the organization, the merge-patch `has*` flags, and the facility
    IRI parse.
  - `Application/UseCase/Query/Equipment/GetEquipmentKpis/GetEquipmentKpisHandlerTest`
    (L2.11) — invalid organization id, compliant/dueSoon tally from a mocked
    batch due-status map, zero-equipment case.
  - `Presentation/Api/Provider/Equipment/GetEquipmentKpisProviderTest` (L2.11)
    — auth/permission gating, wrapped `InvalidArgumentException` mapped to
    400, result-to-output mapping.
- Cross-module adapter unit test hosted in Inspection (composes existing,
  already-tested repository calls — no new DQL, so no new integration test):
  `tests/Unit/Inspection/Infrastructure/Adapter/Equipment/EquipmentNonConformityStatisticsAdapterTest`.
- Functional: `tests/Functional/Api/CanonicalEquipmentApiTest` — the whole
  `PATCH`/`DELETE /api/equipment/{id}` contract, one HTTP request per test:
  200 + bumped revision on a legal transition, 422 on an illegal one, on a
  null non-nullable field, on a foreign facility and on an in-service asset
  left without one, 204 for the retire / the scratchpad hard-delete / the
  idempotent repeat, 412 on a stale revision, 404 before 428 on an unknown id,
  404 on a malformed one, 404 for a foreign organization (never 403), and 403
  for a member without write.
- Integration (real database):
  `tests/Integration/Equipment/Infrastructure/Persistence/Doctrine/Repository/CanonicalEquipmentRepositoryTest`
  — that `findById()` carries the columns the aggregate does not, that
  `save()` writes the mutable ones and **leaves `record_status`,
  `intervention_id` and `client_id` alone**, and that `save()` on an absent
  row inserts nothing.
- Functional: `tests/Functional/Api/EquipmentApiTest::testGetEquipmentKpisRequiresAuthentication`.
- Attachment MIME/size validation (closed 2026-08-19):
  - `tests/Unit/Equipment/Presentation/Api/Processor/Media/MediaProcessorTest`
    — `testUploadRejectsAFileJustOverTheMaxSizeBeforeDispatch` (oversize, unit
    test only — see the class docblock for why the HTTP round trip cannot
    reach the boundary in this environment) and
    `testUploadRejectsADisallowedMimeTypeBeforeDispatch`, both asserting the
    command bus is never dispatched.
  - `tests/Unit/Equipment/Presentation/Api/Processor/Equipment/AddAttachmentProcessorTest`
    — `testProcessRejectsADisallowedMimeTypeWith422` and
    `testProcessRejectsAnOversizedPayloadWith422`.
  - Functional: `tests/Functional/Api/EquipmentAttachmentApiTest.php` — both
    upload paths: happy path unchanged (base64 JSON and multipart), 422 on a
    disallowed MIME type (both paths) and on an oversized base64 payload, 403
    missing-permission, 404 cross-org equipment, 401/403 unauthenticated.
- Attachment download (closed 2026-08-19):
  - `tests/Unit/Equipment/Application/UseCase/Query/Equipment/GetEquipmentAttachmentContent/GetEquipmentAttachmentContentHandlerTest`
    — the stored-bytes happy path, unknown equipment, equipment in another
    organization, unknown attachment, attachment belonging to another
    equipment (never reads the file in any failure path), and the malformed
    identifier 400.
  - Functional: `tests/Functional/Api/EquipmentAttachmentApiTest.php` —
    `testDownloadAttachmentServesBytesWithAttachmentDispositionAndNosniff`,
    401/403 unauthenticated, 403 missing `organization.equipment.read`, 404
    for a caller outside the owning organization, and 404 when the
    `equipmentId` in the path does not own the requested attachment.
- Plan position (Phase 4):
  - `Domain/ValueObject/PlanPositionTest` — coordinate-bounds validation, the
    UUID check on `attachmentId`, and the `toArray()`/`fromArray()` round trip.
  - `Application/UseCase/Command/Equipment/SetEquipmentPlanPosition/SetEquipmentPlanPositionHandlerTest`
    — every failure path (unknown equipment, no facility assignment, unknown/
    wrong-kind/non-ancestor attachment via a mocked `EquipmentFloorPlanValidationPort`,
    partial-input rejection), the happy set, and the clear.
  - `tests/Unit/Facility/Infrastructure/Adapter/Equipment/EquipmentFloorPlanValidationAdapterTest`
    (hosted in Facility, since the adapter is) — every typed exception path
    and the success path.
  - `tests/Integration/Equipment/Infrastructure/Adapter/Facility/EquipmentPlanPositionAdapterTest`
    — the `plan_position` JSONB filter, published-only, and organization
    scoping, plus the type/serial-number display label.
  - `tests/Unit/Equipment/Infrastructure/Adapter/Intervention/EquipmentInterventionResourceAdapterTest`
    — the offline `planPosition` patch: valid set, rejected for
    facility-less equipment, rejected malformed, and cleared alongside
    `facility`.
  - Functional: `tests/Functional/Api/EquipmentPlanPositionApiTest.php` — PUT
    happy path (set, then clear), 404 unknown/cross-org equipment, 403
    missing-permission, 409 unassigned-equipment, 404 unknown attachment, 400
    partial input. Overlay-side equipment inclusion is covered in
    `tests/Functional/Api/FacilityPlanGeometryApiTest.php` (Facility owns
    that endpoint).
- CSV export:
  - `tests/Unit/Equipment/Application/UseCase/Query/ExportEquipments/ExportEquipmentsHandlerTest`
    — 403 without `organization.equipment.read`, 404 outside the
    organization's scope, 422 past `MAX_EXPORT_ROWS`, and bulk facility-name
    resolution with the raw-id fallback when a name cannot be resolved.
  - `tests/Unit/Equipment/Presentation/Api/Service/EquipmentCsvWriterTest` —
    freezes `EquipmentCsvWriter::HEADER`'s first six columns
    (`type`/`subType`/`brand`/`model`/`serialNumber`/`locationLabel`) as the
    Import module's round-trip contract, plus the header/data-row write and
    the facility-name fallback.
  - `tests/Unit/Equipment/Presentation/Api/Controller/ExportEquipmentsControllerTest`
    — the CSV body and headers (`StreamedResponse::getContent()` is not
    reliably buffered by the functional `KernelBrowser`), the missing-URI-
    variable 400, the unauthenticated 401, the `EquipmentsExportedEvent`
    dispatch, and the bus-wrapped 403/422 unwrapping.
  - Functional: `tests/Functional/Api/EquipmentExportApiTest.php` — 200 with
    CSV content type/attachment disposition and the import column order, 401,
    403 for a member without `organization.equipment.read`, 404 for a caller
    outside the organization. The 422 row-cap path is unit-only (`MAX_EXPORT_ROWS`
    is a class constant; seeding 50 001 rows for a functional test is not
    worth the runtime).
- QR label sheet:
  - `tests/Unit/Equipment/Application/UseCase/Query/ExportEquipmentLabels/ExportEquipmentLabelsHandlerTest`
    — 403 without `organization.equipment.read`, 404 outside the
    organization's scope, 400 on the ambiguous or empty selection, 422 past
    `MAX_LABELS` (both the early id-count check and the repository COUNT,
    neither fetching a row), id-list deduplication, the selection-mode name
    in the result, and the single bulk facility-name round trip.
  - `tests/Unit/Equipment/Presentation/Api/Controller/ExportEquipmentLabelsControllerTest`
    — the Twig context shaping (the `/api/equipment/{id}` QR value,
    byte-for-byte), the PDF headers/disposition, the both-modes 400, the
    empty-`ids[]` 400, the unauthenticated 401, the
    `EquipmentLabelsExportedEvent` dispatch, and the bus-wrapped 403/404/422
    unwrapping.
  - Functional: `tests/Functional/Api/EquipmentLabelSheetApiTest.php` — 200
    `%PDF-` for the whole-park, `ids[]` and `facilityId` selections, 400 for
    both modes at once, **422 through the real HTTP surface** (501 ids in the
    query string — cheap, unlike seeding 50 001 rows for the CSV cap), 401,
    403 for a member without `organization.equipment.read`, 404 for a caller
    outside the organization.
- Run module tests: `make test tests/Unit/Equipment/`

## Error Codes

- `EquipmentNotFoundException` → 404
- `EquipmentSerialNumberAlreadyExistsException` → 409
- `EquipmentAlreadyDecommissionedException` → 409 (processors map it via `ConflictHttpException`;
  note this module's own text elsewhere says 422 — treat 409 as authoritative, matching the code)
- `CanonicalEquipmentValidationException` → 422 — the canonical surface's five
  refusals: a non-nullable field sent as null, an unsupported enum value, an
  illegal status transition, a facility from another organization, and an
  in-service asset left without one
- `EquipmentRevisionMismatchException` → 412 (`If-Match` lost the race between
  the scope read on the query bus and the mutation's own transaction)
- `AttachmentNotFoundException` → 404
- `Shared\Domain\Attachment\InvalidAttachmentException` → 422 (MIME type,
  size, or the 25-attachment-per-equipment cap; `AttachmentConstraintExceptionSubscriber`
  maps the count-cap case wherever it is thrown through the command bus,
  `MultipartAttachmentGuard` and `AddAttachmentProcessor` map the MIME/size
  case themselves before dispatch — see "API Endpoints" above)
- `TagNotFoundException` → 404
- `EquipmentNotAssignedToFacilityException` → 409 (Phase 4 — equipment has no facility to place on a plan)
- `FloorPlanAttachmentNotFoundException` → 404 (Phase 4, contract exception)
- `FloorPlanAttachmentNotFloorPlanException` → 409 (Phase 4, contract exception)
- `FloorPlanAttachmentNotAncestorException` → 409 (Phase 4, contract exception)
- `EquipmentAccessDeniedException` → 403 (export only — authenticated member missing `organization.equipment.read`)
- `EquipmentExportTooLargeException` → 422 (export only — past `ExportEquipmentsHandler::MAX_EXPORT_ROWS`)
- `EquipmentLabelExportTooLargeException` → 422 (label sheet only — past `ExportEquipmentLabelsHandler::MAX_LABELS` (500))

The three `FloorPlanAttachment*` exceptions are **contract exceptions**, not
Domain ones: they live under `Application/Contract/FloorPlan/` because they
are the typed `@throws` surface of `EquipmentFloorPlanValidationPort`, thrown
by Facility's adapter across the module boundary — and cross-module access is
restricted to `Application\Port\` and `Application\Contract\` types.
