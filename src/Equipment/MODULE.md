# Equipment Module

**Reading guide:** [Documentation index](../../docs/README.md) · [Related guide](../../docs/guides/module-development.md).

## Overview

V3's public `InterventionEquipmentSnapshotPort` bridge supplies only published,
same-organization equipment identity to authorized operational and financial callers.
It preserves retired assets, archived catalog codes and the actual declared label,
asset reference, brand, model, serial and facility. The facility owner's public
hierarchy reader supplies root site and optional internal client `{id,name}`, including
archived published locations. No client contacts cross the bridge. Batches and live
facility scopes are bounded to 10000 records; missing, foreign or draft assets are
omitted and oversized scopes fail explicitly. Intervention and private cost owners
freeze these values in future snapshots; this reader never rewrites historical
snapshots or guesses missing legacy identities.

### Atomic creation and exact place counts (2026-10-03)

Creation, initial assignment, offline identity and intervention attachment use
one `main` transaction. Failure rolls back the equipment row, quota consumption
and creation event. Receipt/onboarding replays preserve their original result.
Published resources can target published facilities only; a draft can also
target another draft belonging to the same intervention.

Onboarding receipts compare the complete prepared equipment identity, including
`name`, `assetCode`, `criticality` and `technicalProperties`. An exact retry returns
the original equipment; changing any prepared identity field returns a conflict
before another quota debit or save.

`GET /api/organizations/{organizationId}/facilities/{facilityId}/equipment-summary`
is Equipment-owned and requires Equipment read access. `includeDescendants`
defaults to true. It returns `scope` (`subtree` or `direct`), `totalItems`, four
`byStatus` counts and `needingAttentionCount` (maintenance + decommissioned).
The aggregate uses the same predicates as the corresponding equipment
collection, across every page; the existing collection default remains direct.
Frontend detail and Assets explicitly share a subtree default and direct option.

Equipment manages the fire safety asset inventory of an organization. It tracks
physical fire safety equipment (extinguishers, smoke detectors, sprinklers, fire
alarm panels, hydrants, cameras, etc.) through a full operational lifecycle.

Main goals:

- Maintain a registry of fire safety equipment scoped to an organization.
- Enforce a controlled status machine for operational lifecycle management.
- Support facility assignment, free-form tagging, and file attachments.

## API Endpoints

The facility-scoped equipment collection supports `includeDescendants=true` with
server-side search and pagination. Its subtree scope remains within the organization
and returns each equipment's real assignment and original `planPosition`. Facility's
building projection reads equipment through the owned batch port and never assigns
an arbitrary position to an unplaced item. Deleting a referenced floor plan clears
that attachment's placements; choosing a new primary plan does not transfer them.

Equipment attachment retries with the same client id return the first stored
upload metadata without replacing its bytes. Every new attempt writes a unique
blob path. `saveIfAbsent` serializes on the owning equipment, checks the 25-file
cap under that lock, and inserts the id once. A concurrent loser removes only its
own attempt blob; it cannot delete the winning upload. Reusing an id from another
equipment answers 404. Storage/database failures still remove the attempt blob.

| Method       | Path                                                                                              | Description                                                                                                                                                    |
| ------------ | ------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| POST         | `/api/organizations/{organizationId}/equipment`                                                   | Create equipment                                                                                                                                               |
| GET          | `/api/organizations/{organizationId}/equipment`                                                   | List equipment (filters: `facilityId`, `type`, `status`, `brand`, `model`, `subType`, `search`, `maintenanceDueStatus`)                                        |
| GET          | `/api/organizations/{organizationId}/equipment/kpis`                                              | Get the four headline equipment KPI counters (L2.11): `totalAssets`, `compliant`, `dueSoon`, `openNonConformities`                                             |
| GET          | `/api/organizations/{organizationId}/equipment/{equipmentId}`                                     | Get equipment                                                                                                                                                  |
| PATCH        | `/api/organizations/{organizationId}/equipment/{equipmentId}`                                     | Update equipment fields                                                                                                                                        |
| POST         | `/api/organizations/{organizationId}/equipment/{equipmentId}/assign`                              | Assign to a facility                                                                                                                                           |
| POST         | `/api/organizations/{organizationId}/equipment/{equipmentId}/unassign`                            | Remove from current facility                                                                                                                                   |
| PUT          | `/api/organizations/{organizationId}/equipment/{equipmentId}/plan-position`                       | Set or clear this equipment's position pinned on a floor plan attachment (Phase 4)                                                                             |
| POST         | `/api/organizations/{organizationId}/equipment/{equipmentId}/commission`                          | Mark as `operational`                                                                                                                                          |
| POST         | `/api/organizations/{organizationId}/equipment/{equipmentId}/maintenance`                         | Mark as `under_maintenance`                                                                                                                                    |
| POST         | `/api/organizations/{organizationId}/equipment/{equipmentId}/decommission`                        | Permanently decommission                                                                                                                                       |
| POST         | `/api/organizations/{organizationId}/equipment/{equipmentId}/tags`                                | Add (or create) a tag                                                                                                                                          |
| DELETE       | `/api/organizations/{organizationId}/equipment/{equipmentId}/tags/{tagId}`                        | Remove a tag                                                                                                                                                   |
| GET          | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments`                         | List attachments                                                                                                                                               |
| POST         | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments`                         | Upload attachment (base64 JSON — see below)                                                                                                                    |
| DELETE       | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}`          | Delete attachment                                                                                                                                              |
| GET          | `/api/organizations/{organizationId}/equipment/{equipmentId}/attachments/{attachmentId}/download` | Download an attachment's raw bytes (`Content-Disposition: attachment`, never inline — see below)                                                               |
| POST         | `/api/media`                                                                                      | Canonical multipart upload, shared with the intervention offline/field-evidence flow (`equipment`/`intervention`/`clientId`/`file`/`label` fields — see below) |
| GET / DELETE | `/api/media/{id}`                                                                                 | Read / delete a canonical media attachment                                                                                                                     |
| GET          | `/api/organizations/{organizationId}/equipment/export`                                            | Streams a bounded CSV export of every equipment item in the organization — see below                                                                           |
| GET          | `/api/organizations/{organizationId}/equipment/{equipmentId}/report`                              | Streams a PDF equipment sheet (identity, maintenance history, attachment index) — plan-gated, see below                                                        |
| GET          | `/api/organizations/{organizationId}/equipment/labels`                                            | Streams a printable PDF sheet of QR equipment labels (Avery L7159 grid) — not plan-gated, see below                                                            |

`GET /api/organizations/{organizationId}/equipment-types` is the organization-owned
catalog for equipment families and custom types. It returns a collection of
`value`, `label`, `family` (`fire`, `safety`, `other`), `archived`, and `revision`.
The twelve historical codes retain their values; defaults have revision 1 and
changed descriptors have revision 2 or later. Unrecognized persisted historical
codes remain readable as archived descriptors. The status registry remains static.

| Method | Path | Description |
| --- | --- | --- |
| GET / POST | `/api/organizations/{organizationId}/equipment-types` | Read descriptors / create a custom descriptor |
| GET / PATCH | `/api/organizations/{organizationId}/equipment-types/{typeCode}` | Read / revise label, family or archive status using the observed body revision |
| POST | `/api/organizations/{organizationId}/equipment/{equipmentId}/replace` | Atomically replace a published equipment with an existing reserve or a new successor |
| GET | `/api/organizations/{organizationId}/equipment-summary` | Exact published totals with family and customer scope, before pagination |

Catalog reads require `organization.equipment.read`; administration and replacement
require `organization.equipment.write`. Unknown/foreign organization or asset scope
is hidden with 404; missing permission returns 403. Catalog codes are immutable.

### Specialized parc identity and replacement

Both organization and canonical equipment surfaces expose `name`, `assetCode`,
`criticality` (`low`, `medium`, `high`, `critical` or null), and `technicalProperties`
(at most 50 `{key, value, unit}` entries, unique nonempty keys). Asset codes are
optional, trimmed and unique within the organization. Empty identity text normalizes
to null. Canonical merge patches preserve omitted fields and can explicitly clear
optional identity text; the organization update also preserves omitted new identity.
These properties describe an asset and do not certify its condition or compliance.

The organization and facility collections accept `family` and `customerId`.
Family includes archived types for historical reads. Customer scope is resolved from
published root sites and their published descendants through the Facility owner port;
foreign and unknown customer identifiers return the same 404. Facility summaries use
the same filters before pagination. Search covers equipment names and patrimonial codes.
Organization summaries use the same predicates and return `scope` organization/customer.
The `maintenanceDueStatus=due` query combines due-soon and overdue rows before
pagination; individual equipment outputs retain their actual due-state literal.
`EquipmentParkScopePort::filterIds` publishes scoped identifiers for peer modules;
an optional facility identifier includes its published subtree by default. Passing
`includeDescendants=false` selects only equipment directly assigned to that validated
facility, while retaining family and customer filters. This permits scoped
anomaly projections without exposing Equipment records or substituting organization totals.
Validated work-item service history can carry its actual performed date, author and
notes. Publication time and responsible member remain the fallback for historical
applied changes; work-item/change identities retain idempotent append semantics.

Replacement accepts a stable UUID `clientOperationId` and exactly one of
`successorEquipmentId` or `successor` (ordinary creation identity fields, without
organization, facility, intervention or client-id overrides). One main transaction
locks candidates, creates a new successor with ordinary quota enforcement when
requested, retires the predecessor, establishes both history links and saves the
durable receipt. The successor inherits assignment, location and plan position and
enters service when replacing in-service equipment. Attachments, anomalies, dates,
QR identity and the predecessor's history remain attached to the original asset.
Repeated requests return the original identifiers without another creation or debit;
reusing the operation UUID with another payload returns 409. Output adds nullable
`predecessorEquipmentId` and `successorEquipmentId`. Complete reserve assets remain
individualized equipment, independently from future consumable stock quantities.

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

**Cross-organization `intervention` oracle on `MediaProcessor::upload()`, closed
2026-09-01.** The multipart `intervention` field is read straight off the
request body (`$request->request->get('intervention')`) — attacker-controlled,
independent of the `equipment` field's own organization. `assertWrite()` used
to resolve `interventionResourceManager->interventionContext($interventionId)`
FIRST and answer its own **409** (`InterventionConflictException`, "must
belong to the same organization") on a mismatch, which told a caller outside
the intervention's organization both that it exists and that it belongs to
another organization. `assertWrite()` now calls
`InterventionResourceManager::mutationPermission($interventionId, $userId,
$equipment->organization->id)` FIRST — its own `$expectedOrganizationId` gate
(see `Intervention\MODULE.md`, 2026-09-01 entry, and the identical fix already
applied to `Create{Facility,Equipment,Inspection}Processor` and
`InspectionResponseProcessor::assertWrite()`) — so a cross-organization
intervention id now answers a uniform **404** (`InterventionNotFoundException`)
before the `interventionContext()`/`resourceInInterventionScope()` checks are
ever reached. Those checks stay in place as defense in depth for a
same-organization caller. Regression coverage:
`EquipmentAttachmentApiTest::testUploadMediaReturns404WhenInterventionMultipartFieldBelongsToAnotherOrganization`
and the equipment/inspection mirrors in
`InterventionFacilityContractApiTest`, now data-provider-driven across all six
intervention workflow statuses (`crossOrganizationInterventionStatusProvider`).

**Wire-shape decision.** `AddAttachmentInput` (`fileName`/`content` base64/
`mimeType`/`label`) was deliberately KEPT rather than aligned to the
multipart shape used by every sibling module: `fireguard-web`'s
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

The optional registered office is consumed as scalar Application branding data
and labelled explicitly in the common legal footer, separately from the facility
address. An absent address preserves the existing document rendering.

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
is a `public` constant, and its first seven columns
(`type`, `subType`, `brand`, `model`, `serialNumber`, `locationLabel`,
`facilityCode`, in that exact order) are a published contract: they are the
same seven columns, in the same order, that
`Import\Application\Service\EquipmentRowFactory` reads back by column _name_
(not position — the importer maps by header, so reordering is actually safe
for the importer itself, but the position is still frozen here to keep the
two sides human-comparable) on a bulk CSV reimport. `facilityCode` (added
2026-08-28, appended 7th so the original six stay frozen in place) closes
the reimport loop for facility assignment: the export resolves each assigned
facility's organization-scoped unique `code` in one bulk round trip
(`FacilityNamingPort::findCodesByIds()`), and a reimport resolves it back and
assigns the item atomically through the existing `CreateEquipmentCommand`
(see the provisioning port below); a facility with no code exports an empty
cell, which the importer treats as "no assignment". Every column after the
seventh (`id`, `status`, `facilityId`, `facilityName`, `installedAt`,
`commissionedAt`, `createdAt`, `updatedAt`) is read-only metadata the
importer ignores. The frozen slice is asserted by
`tests/Unit/Equipment/Presentation/Api/Service/EquipmentCsvWriterTest.php`.

Text cells protect formula prefixes, leading control whitespace and literal
apostrophes. The appended `_fireguard_text_encoding=apostrophe-v1` metadata lets
Import remove that protection before provisioning, retaining original formulas
and apostrophes as stored text while the downloaded CSV remains safe to open.
The first seven columns and their reimport meanings remain unchanged.

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
(`fireguard-web/src/app/features/organization/features/interventions/services/intervention-discovery/intervention-discovery.service.ts`;
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
sheet is cut apart. Text occupies a fixed 32.6 mm column, vertically centred beside
the QR, with a maximum visible height of 27.9 mm. Longer content is clipped within
its own label instead of widening columns or creating extra pages. A relative slot
and absolute text descendant exclude content from table sizing and page-break checks;
the non-absolute inner block owns the height limit because dompdf does not apply
`max-height` to absolute blocks.

**Geometry is measured, not assumed.** dompdf silently breaks this grid in
three ways, all of which shipped once: a universal `* { margin: 0 }` reset
overwrites the `@page` margins (the sheet starts at 0,0), `table-layout: fixed`
makes dompdf 3.1 read every cell width as 0 and split the table into equal
columns (pitch 78 mm instead of 66 mm), and `padding: 0` on `table.sheet td`
also overrides the inner label cells' padding. The template header documents
each one, and `DompdfEquipmentLabelSheetRendererTest` asserts the rendered
label and QR positions, translated text painting coordinates, clipping regions and
the page count, including maximal field values in the final row.

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

Creation passes through the command owner before persistence and consequences. Validation, quota and organization rules remain enforced by the use case.

```mermaid
---
config:
  sequence:
    wrap: true
---
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

Commissioning is an equipment lifecycle command. Domain transition checks precede its persisted status and owned follow-up effects.

```mermaid
---
config:
  sequence:
    wrap: true
---
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

The collection query reads authorized equipment through the repository port. The server decides membership, ordering and totals before HTTP serialization.

```mermaid
---
config:
  sequence:
    wrap: true
---
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

| Caller                                                           | Response                                                         |
| ---------------------------------------------------------------- | ---------------------------------------------------------------- |
| Active member of the owning organization, lacking the permission | `403 Forbidden`                                                  |
| No active membership in the owning organization                  | `404 Not Found`, identical to the route's own not-found response |

The 404 is not a softer 403. These surfaces take their `organizationId` from
the URI — or resolve it from a record they just loaded by path id — **before**
they know whether the caller belongs to that organization, so a 403 at that
point confirms the organization or the record exists to someone who may not
learn even that much. That is an existence oracle: it lets a caller from
another organization enumerate valid identifiers. The out-of-scope 404
therefore reuses the _same_ message the route's own "unknown id" branch
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
- `locationLabel` (optional free-text — the spot _inside_ a facility, not the facility)
- `facilityName` (read-only display name of the assigned facility). The module stores only
  `facilityId`; the name is resolved through `FacilityNamingPort` — batched into ONE lookup
  per collection page (both the main path and the due-status filtered path), and resolved
  per item on the detail read and on **every action response** (create, update, assign,
  unassign, commission, maintenance, decommission): an action answers with the same
  equipment payload the detail read would give, so the UI never has to re-fetch to keep its
  facility badge. All of those payloads go through the single
  `Presentation/Api/Factory/EquipmentOutputFactory` (2026-09-01 — the per-processor mapping
  copies are gone; that duplication is how the field went missing from the action responses
  in the first place). Deliberately separate from `FacilityValidationPort`: that contract
  throws, and a label lookup must not go through something whose job is to reject writes.
  Null when unassigned or unresolvable — an unresolved name is not a blank name, and a null
  is omitted from the JSON entirely (API Platform skips null fields).
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
  `unassignFromFacility()` invariant. On replay/publication, the server revalidates
  attachment kind, current facility ancestry and decommissioning through the Facility
  public floor-plan port. Changing the facility clears an existing pin before an
  explicit new placement is considered.
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

### Procurement reserve batches and repair requests

Procurement individualizes physical equipment receipts through
`EquipmentReserveReceiptPort`. A batch contains one to one hundred units and
holds the normal Equipment quota lock before the first creation. Active catalog
validation and the ordinary creation use case apply to every unit. Missing
`organization.equipment.write`, an unavailable type or a quota cap return a
typed blocked outcome without creating orphan assets. Unexpected failures roll
back the complete main batch; Procurement retains its already received physical
receipt and owns the durable receipt-unit identities and replay mapping.

Equipment reserve is individual `in_stock` equipment, distinct from Inventory's
quantitative parts. Shared batch templates cannot repeat unique asset codes or
serial numbers across multiple units. The resulting assets keep the normal
published equipment identity and are assigned to sites later.

Repair requests read their target through the published ServiceRequest target
port. The Equipment adapter hides unknown, foreign and draft equipment. Inside
an active main transaction it locks and refreshes the Equipment row so retirement
and request conversion use current lifecycle state. ServiceRequest owns the
site hierarchy lock and the refusal of retired targets.

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
- `Equipment\Infrastructure\Adapter\Organization\EquipmentSearchAdapter` implements
  the Organization module's `EquipmentSearchPort` for the organization global
  search (`GET /organizations/{organizationId}/search`) — bounded org-scoped
  `LIKE` over type/brand/model/serialNumber/locationLabel, published records only.
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
- **Independent control and service deadlines (V1, extending L2.10)**:
  detail, organization collections and canonical collections expose
  `controlDueStatus`, `serviceDueStatus`, `controlNextDueAt` and
  `serviceNextDueAt`. Status values remain
  `unscheduled`|`up_to_date`|`due_soon`|`overdue`; dates are ISO 8601 when a
  deadline exists. Neither due status changes the operational condition or
  resolves an anomaly. `maintenanceDueStatus` is a compatibility alias of
  `controlDueStatus`, including on the filtered equipment collection.
  `Maintenance\Application\Port\Inbound\MaintenanceOperationsDuePort`
  publishes the organization-scoped bulk projection through its Application
  contract; Equipment never reads Maintenance's Domain or persistence.
  Get, List and the canonical collection call it once per batch, not per row.
  When that optional owner port is unavailable, the existing
  `MaintenanceDueStatusPort::dueStatusesForEquipment()` provides control
  statuses and service remains `unscheduled`. The compatibility adapter and
  new owner alias are wired by Maintenance in `config/modules/maintenance.yaml`.
  Unscheduled or suspended equipment has no deadline.
  The `maintenanceDueStatus` filter cannot be pushed into the `equipment`
  table's SQL `WHERE` clause (the value lives in Maintenance, not here):
  `ListEquipmentsHandler::listFilteredByDueStatus()` instead loads every
  equipment matching the other filters unbounded (capped at
  `DUE_STATUS_FILTER_SCAN_LIMIT = 10_000`, a generous safety net given
  organizations are plan-quota bounded on equipment count — not a practical
  limit), resolves control due status for that whole candidate set in one batch call,
  filters in memory, then paginates with `array_slice()`. **Do not assume a
  per-equipment "Non-conformity" status exists.** It does not: non-conformities
  attach to _inspections_ (see `src/Inspection/MODULE.md`), never to equipment.
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
- **Bulk CSV import v3 — `facilityCode` (2026-08-28)**:
  `ProvisionEquipmentRequest` carries an optional `facilityCode` — the
  organization-scoped unique code of the facility the created item should be
  assigned to. `EquipmentProvisioningService` resolves it **before creating
  anything** through the new
  `FacilityValidationPort::resolveIdByCode()` (implemented Facility-side in
  `Facility\Infrastructure\Adapter\Equipment\FacilityValidationAdapter`,
  archived facilities excluded, mirroring the Facility module's own
  `parentCode` resolution); an unknown code answers `INVALID` without
  dispatching a single command, on a real run and a dry run alike. On a real
  run with a resolved code the service dispatches `CreateEquipmentCommand`
  with the resolved facility ID. Facility validation, assignment, quota
  enforcement and persistence share the existing main transaction. A failed
  assignment is answered as `INVALID` without leaving an unassigned item.
  A dry run resolves the code (so an unknown one is caught) without persisting.
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

| Concern                                                                                                    | Where it lives now                                                                                    |
| ---------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| `PATCH /api/equipment/{id}`                                                                                | `Application/UseCase/Command/Equipment/PatchCanonicalEquipment/`                                      |
| `DELETE /api/equipment/{id}`                                                                               | `Application/UseCase/Command/Equipment/DeleteCanonicalEquipment/`                                     |
| Read one, for the gate                                                                                     | `Application/UseCase/Query/Equipment/GetCanonicalEquipment/`                                          |
| Status machine, draft/published split, `commissionedAt`, in-service rule, revision bump, idempotent retire | `Domain/Model/Equipment/CanonicalEquipment`                                                           |
| Persistence                                                                                                | `Infrastructure/…/Repository/CanonicalEquipmentRepository` (port: `CanonicalEquipmentRepositoryPort`) |
| Intervention revision touch                                                                                | `Equipment\Application\Port\Outbound\InterventionScopePort`                                           |

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

Maintenance exports validate published equipment identities through the owner-hosted
`MaintenanceExportEquipmentIdentityAdapter`, explicitly wired to main and tagged
`maintenance_export.identity_validator`. Retired equipment remains a valid historical
reference; drafts, missing assets and foreign identities return false.

The main mappings include `EquipmentTypeCatalogRecord` and
`EquipmentReplacementReceiptRecord`. `EquipmentTypeCatalogPort` and
`EquipmentReplacementRepositoryPort` have explicitly main-wired repositories.
`EquipmentSelectionScopeResolver` composes the catalog, Customer lookup and
Facility customer scope ports. No identity or replacement migration touches auth.

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

Unit tests cover owned domain/use-case and HTTP translation contracts. Integration tests execute real PostgreSQL queries and persistence behavior. Functional/E2E tests preserve authorization, contextual isolation, replay and failure recovery.

Detailed cases and regression rationale are retained in the [Equipment testing reference](../../docs/guides/testing.md#equipment-testing-reference). Use the [testing guide](../../docs/guides/testing.md) for current commands and isolated database setup.

## Error Codes

- `equipment_asset_code_conflict` → 409, patrimonial code already used in the organization.
- `equipment_replacement_conflict` → 409, incompatible lifecycle, successor or replay payload.
- `equipment_type_exists` → 409; `equipment_type_revision_conflict` → 412.
- `equipment_type_not_found` → 404; `equipment_type_invalid`, `equipment_type_unknown`, `equipment_type_archived` → 422.

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

## Durable onboarding setup

Creation accepts optional `onboardingSessionId` and `onboardingItemKey` together. These identify input previously prepared by the authenticated creator through Onboarding. The owner handler checks the session, step, input and pinned organization, then records its created identifier in the same `main` transaction as the resource and quota enforcement. A replay returns that resource without another quota consumption or event. Missing or incompatible preparation returns `onboarding_setup_conflict` (409), never a legacy fallback. Calls without either field keep their existing contract.

Published equipment persistence exposes `EquipmentChangedEvent` as a public invalidation fact.
Its Doctrine listener enqueues it in the owning main transaction for creation, lifecycle,
assignment, type and publication changes or deletion; consumers reload current source state.

### Spatial history and persisted revisions

Published placement changes enqueue the public `EquipmentPlanPositionChangedEvent` in
main. Audit delivery preserves the initiating actor and deduplicates local consequences.
Metadata contains operation, old/new plan identifiers, revision and intervention, never
coordinates. Publishing a draft validates its placement and emits once; replay is inert.

Doctrine owns the shared optimistic revision for legacy writes, canonical patches and
publication. Direct placement and legacy update/assignment/lifecycle responses use the
same complete detail projection as canonical GET. Creation retains the revision returned
by intervention assignment. Concurrent stale writes return 412 `resource_revision_conflict`.
Plan writes expose 409 codes `equipment_facility_required`, `equipment_decommissioned`,
`floor_plan_outside_ancestry` and `attachment_not_floor_plan` for contextual recovery.
