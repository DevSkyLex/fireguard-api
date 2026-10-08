# Facility Module

**Reading guide:** [Documentation index](../../docs/README.md) · [Related guide](../../docs/guides/module-development.md).

When facility creation participates in a main transaction (including CSV import),
its event is appended to the main outbox in that transaction. Consumers only see
committed creations; direct non-transactional callers retain synchronous dispatch.

## Overview

The V3 economic-scope bridge resolves optional root-site and internal-client filters
for authorized Intervention source readers. It returns published root and descendant
identifiers, including archived locations, with organization fencing on every
recursive edge. Unknown or foreign filters return an empty scope. The depth is
bounded to 64 and the result to 10000 locations; oversized scopes require a narrower
filter rather than silently truncating an economic report. Published report filters
remain the Intervention owner's captured identities and do not call this live bridge.

### Internal customer assignment

Root SITE facilities may carry nullable `customerId`, independent from the
offline replay `clientId`. New assignments require an active internal customer in
the same organization through `FacilityCustomerValidationPort`; unknown, foreign
and archived clients produce the same `facility_customer_invalid` (422).
Only sites without a parent may carry this field. Both aggregates, canonical and
legacy writes, intervention proposals and draft publication enforce the invariant.
Retained archived customer links stay readable and accept unrelated edits.
Clearing the assignment uses explicit null. Duplicating a site starts unassigned.

Legacy facility lists accept `customerId` to filter root sites, with identical
criteria for records and total; unknown and foreign customers return 404 after
facility access checks. Detail/list responses and offline snapshots include the
stored customer identifier. Intervention closure snapshots freeze the site's
identity and current customer name/contacts through owner-published ports.

### Hierarchy consistency (2026-10-03)

`FacilityHierarchyPort` is the shared creation/relation policy. SITE is the only
root; BUILDING requires SITE, FLOOR requires BUILDING, and ZONE/AREA may belong
to any type, including another zone or area. Organization, active parents,
publication scope, cycles and the existing depth cap are validated together.
Historical invalid structures remain readable and accept descriptive edits;
repair requires an explicit valid move. No parent/site is synthesized.

Relation and archival mutations take the same PostgreSQL organization lock on
the `main` connection, then reread revisions and the final graph. MOVE requires
`If-Match: "revision-N"` (428 when absent, 412 when stale). Hierarchy violations
return 422, and retained-resource dependencies return 409. Create, canonical
PATCH, imports, duplicate and intervention publication share this policy.

Publication retains unchanged relationships of existing published facilities during
descriptive edits, including repeated type/parent values, and archival. A parent and
its children can therefore become archived in one atomic publication once all final
active dependents are removed. Draft publication, changed relationships/types and
restores still require valid active ancestry. Both validation stages compare against
the same locked baseline, including after the proposed writes are persisted.

Collections support `includePath=true`, with ancestors resolved in a batch.
Organization collections additionally accept mutually exclusive `parentForType`
and `parentForFacilityId`, filtering eligible parents before search/count/page.
Roots remain the unsearched browse mode; search can cover every place. These
transport identifiers remain stable while French UI uses **Lieu / Lieux**.

Parent filtering may include `interventionId` for an intervention preparation
context. The server checks its planning/execution capability and participation:
published parents require Facilities read permission, while same-intervention
draft parents remain available to authorized preparers. Foreign drafts and
inaccessible ancestor names are never projected. Collection revisions are read
in one batch for organization, children and descendants, including published
collections, so a parent chosen outside the first page carries its real revision.

`FacilitySpatialValidityResolver` supplies identical geometry diagnostics to
detail, 2D overlays and generated 3D through organization-scoped batch reads.
Moving a subtree preserves polygons, equipment coordinates and GLB bindings.
Unusable geometry is omitted from the corresponding representation and exposed
as `invalid_geometry`, `plan_unavailable`, `outside_ancestry` or `other_plan`.
Floor enumeration uses each floor's nearest building ancestor and reports
historical hierarchy issues.

Plans retain server-owned `calibrationBuildingId`. A move to another building
retains scale/elevation/height but emits `calibrationIssue=building_changed`;
unknown provenance emits `unverified_frame`. Both exclude the floor from metric
mode until explicit recalibration. Moving a building between sites keeps its
frame. Main migration `Version20261003120000` backfills only identifiable current
owners; previous historical moves cannot be reconstructed.

GLB output exposes only usable `bindings`, plus masked `bindingIssues` containing
node index and `target_unavailable`. Invalid bindings remain stored and the
active model remains readable, without facility navigation/selection/filtering.
Activation rejects invalid bindings. Omitted/null PATCH bindings retain history;
explicit `bindings: []` clears all. A partial replacement retains unavailable
bindings unless their indices appear in `removeBindingNodeIndices`.

Facility manages generic organizational structures such as sites, buildings,
floors, zones, and areas. It is organization-scoped and uses
`OrganizationAuthorizationPort` for permission checks.

Main goals:

- Provide a reusable, generic location hierarchy.
- Keep business-specific logic out of this module.
- Support progressive modularization (modulith today, extract later).

## API Endpoints

| Method | Path                                                                         | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| ------ | ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| POST   | `/api/organizations/{organizationId}/facilities`                             | Create a facility                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| GET    | `/api/organizations/{organizationId}/facilities`                             | List facilities (filters: `includeArchived`, `type`, `status`, `parentFacilityId`, `rootsOnly`, `code`, `hasCoordinates`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          |
| GET    | `/api/organizations/{organizationId}/facilities/export`                      | Streams a bounded CSV export of facilities, same filter subset as the list endpoint plus `search`. Requires `organization.facilities.read`, resolved in `ExportFacilitiesHandler` (not the resource's coarse `ROLE_USER` gate). Bounded to `ExportFacilitiesHandler::MAX_EXPORT_ROWS` (50 000) matching rows — 422 past that.                                                                                                                                                                                                                                                                                                                                                                                      |
| GET    | `/api/organizations/{organizationId}/facilities/address-suggestions?q=…`     | Address suggestions (3–250 characters), `organization.facilities.write`; up to five `member` entries (canonical label, coordinates, street, city, region, postal code, country and ISO country code) and `totalItems`; 400/403/404/429/503                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| GET    | `/api/organizations/{organizationId}/facilities/geocode?address=…`           | Server-side geocoding input aid: resolves a free-form address (1–300 chars) to `{ latitude, longitude, displayName }` through `GeocodingPort` (Nominatim behind `GEOCODING_BASE_URL`). Requires `organization.facilities.write` — write, not read: the lookup exists to FILL a facility's coordinates, and only write-entitled members may burn the shared outbound budget. Rate limited 30/min/user (`facility_geocode`); the adapter additionally throttles the aggregate outbound channel to 1 req/s (Nominatim policy). 404 when no coordinates match (plain not-found, no oracle at stake — an address is not a resource). Declared before the `{facilityId}` item route so `geocode` is never read as an id. |
| GET    | `/api/organizations/{organizationId}/facilities/{facilityId}`                | Get one facility (includes the ancestor `path` breadcrumb)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| GET    | `/api/organizations/{organizationId}/facilities/{facilityId}/children`       | List direct children for lazy tree expansion (paginated)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           |
| GET    | `/api/organizations/{organizationId}/facilities/{facilityId}/descendants`    | List all descendants for bulk subtree reads                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| PATCH  | `/api/organizations/{organizationId}/facilities/{facilityId}`                | Update a facility                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| POST   | `/api/organizations/{organizationId}/facilities/{facilityId}/archive`        | Archive a facility                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| POST   | `/api/organizations/{organizationId}/facilities/{facilityId}/move`           | Move a facility under another parent                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| PUT    | `/api/organizations/{organizationId}/facilities/{facilityId}/plan-geometry`  | Set or clear this facility's plan geometry (Phase 4)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               |
| GET    | `/api/organizations/{organizationId}/facilities/{facilityId}/plan-overlay`   | Read one floor plan, every self-or-descendant zone bound to it, and every equipment item pinned on it (Phase 4, equipment additive — see Equipment's MODULE.md)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| GET    | `/api/organizations/{organizationId}/facilities/{facilityId}/building-model` | Read the ordered stack of floors (outline + rooms) a 3D viewer extrudes for a `building` facility (A3)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| POST   | `/api/organizations/{organizationId}/facilities/{facilityId}/duplicate`      | Duplicate a facility and its full subtree into a new branch                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| GET    | `/api/facilities/{id}`                                                       | Canonical item read (includes the ancestor `path` breadcrumb)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      |
| GET    | `/api/facilities?organization={iri}`                                         | Canonical collection read, org- or intervention-scoped                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             |

Removed 2026-08-20: `GET /api/facilities/types` and `GET /api/facilities/statuses`
(unconsumed reference catalogs; the frontend's localized typed registries are the
source of these values).

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

### CSV export and the Import round-trip contract

`GET /api/organizations/{organizationId}/facilities/export` streams a synchronous
CSV (no 202+poll), mirroring `Intervention\...\ExportInterventionsController`'s
pattern: `ExportFacilitiesController` authenticates, resolves the same filter
subset the list endpoint accepts (plus `search`), and dispatches
`ExportFacilitiesQuery`. `ExportFacilitiesHandler` resolves
`organization.facilities.read` through `OrganizationAuthorizationPort` itself —
the resource's `is_granted('ROLE_USER')` is only the coarse gate — counts the
match before fetching a single row, and rejects with 422
(`FacilityExportTooLargeException`) past `MAX_EXPORT_ROWS` (50 000). Under the
cap, `FacilityRepositoryPort::findByOrganizationId()` is reused directly (no
intermediate "candidate" projection: unlike Intervention's cross-context
workflow gateway, this port already returns the full `Facility` aggregate in
one query), and every row's parent facility `code` is resolved in one bulk
call to `FacilityRepositoryPort::getFacilityCodesByIds()`.

`FacilityCsvWriter::HEADER`'s **first seven columns are a stable contract**:

```
type, name, code, address, latitude, longitude, parentCode
```

in that exact order — `Import\Application\Service\FacilityRowFactory` reads a
bulk-import CSV back with this same header, so a file exported here can be
re-imported unchanged. `parentCode` is the parent facility's own `code`, never
its id, because the import side resolves a parent by `code`. Latitude/longitude
are written as plain decimal strings (no locale formatting). Every column past
`parentCode` (`id`, `status`, `createdAt`, `updatedAt`, `levelIndex`) is read-only
export metadata the import side ignores — which is why `levelIndex` was appended at
the very end rather than slotted among the descriptive columns: inserting it into the
first seven would silently break the bulk import round trip.
`tests/Unit/Facility/Presentation/Api/Service/FacilityCsvWriterTest.php` freezes
the first-seven-columns ordering.

Text cells protect formula prefixes, leading control whitespace and literal
apostrophes. An appended `_fireguard_text_encoding=apostrophe-v1` column lets
Import restore their original values before provisioning. Coordinates and
`levelIndex` keep their plain numeric representation, including negative values.
The first seven columns and their reimport meanings remain unchanged.

A `FacilitiesExportedEvent` is dispatched after a successful export, carrying
only the applied filter **names** (`filterKeys`), never their raw values. The
Audit module wires it centrally to the `facility.list_exported` action — this
module never writes to the audit ledger directly.

### Attachments (R11b, floor plans Phase 3)

### Subtree duplication

`DuplicateFacilitySubtreeHandler` clones a published, active facility and its
full published subtree (fetched through the same descendants recursive CTE as
`/descendants`) into a new, independent branch — for chains rolling out
identical buildings. Request body: optional `name` (the copy's root name;
default `"{original} (copy)"`, a plain suffix — no server-side localization)
and optional `parentFacilityId` (default: the source's own parent). Response:
201 with the new root's `FacilityOutput`.

Cloning rules:

- **`code` → always `NULL`** on every clone. `uniq_facility_organization_code`
  makes copying the original code impossible, and `NULL` does not collide
  with itself under that constraint, so every clone in the batch gets one.
- **`status` → always `active`** on every clone. Duplicating an archived
  source would otherwise un-archive its lineage by the back door, so an
  archived source is refused outright with **409**
  (`FacilitySubtreeSourceArchivedException`) rather than silently reactivated.
- **Archived descendants are skipped**: no clone is created for an archived
  node, but its own live children (if any) are still visited and reattached
  to the nearest cloned ancestor — normally the new root, or the closest
  active ancestor's clone — so a live branch beneath an archived one is not
  silently dropped. This mirrors the traversal the archival guard already
  performs (see Architecture below).
- **Copied**: `type`, `address`, `metadata`, `latitude`/`longitude`.
  **Not copied**: `name` on the root only (root gets `name` or the `(copy)`
  suffix; every other clone keeps its original node's name), `clientId`,
  `interventionId` (every clone is a fresh published record at revision 1 —
  this module owns no plan geometry column on this base, so there is nothing
  to copy or omit there).
- **Quota**: the whole clone count — root plus every non-skipped descendant —
  must pass the `facilities` plan quota check **before any insert**, in the
  same transaction as the inserts. `OrganizationQuotaPort::assertCanAddMultiple()`
  (the batched sibling of `assertCanAdd()`, added for this use case) takes the
  same per-(organization, resource) advisory lock so a concurrent create or
  duplicate cannot slip past the count. Exceeded →
  `Organization\Application\Contract\Quota\OrganizationQuotaExceededException`
  (the contract twin of the Domain exception the single-create path uses,
  identical message) → the same **409**.
- **Size cap**: a subtree that would traverse more than **500** nodes (source
  included, archived nodes included since they still cost a query/traversal)
  is refused with **422** (`FacilitySubtreeTooLargeException`) before the
  quota check or any insert.
- **Audit**: exactly **one** `FacilitySubtreeDuplicatedEvent` (source id, new
  root id, node count) is dispatched after the transaction commits — never
  one event per cloned node — recorded as `facility.subtree_duplicated` (see
  Architecture below).

### Attachments (R11b)

| Method | Path                                       | Description                                                                                                 |
| ------ | ------------------------------------------ | ----------------------------------------------------------------------------------------------------------- |
| POST   | `/api/facilities/{facilityId}/attachments` | Upload a multipart file attachment. Optional `kind` field (`document`, the default, or `floor_plan`)        |
| GET    | `/api/facilities/{facilityId}/attachments` | List a facility's attachments (optional `?kind=document\|floor_plan` filter)                                |
| GET    | `/api/facility-attachments/{id}`           | Get one attachment                                                                                          |
| DELETE | `/api/facility-attachments/{id}`           | Delete an attachment (requires `If-Match: "revision-N"`)                                                    |
| POST   | `/api/facility-attachments/{id}/primary`   | Promote a `floor_plan` attachment to the facility's primary plan                                            |
| GET    | `/api/facility-attachments/{id}/download`  | Download an attachment's raw bytes (`download_facility_attachment`, `DownloadFacilityAttachmentController`) |

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

**Security constraint — bytes only ever leave through `AttachmentDownloadResponder`.**
`GET /facility-attachments/{id}/download` (`DownloadFacilityAttachmentController`) is
the ONLY route serving attachment bytes, and it MUST route every response
through the shared `Shared\Presentation\Api\Attachment\AttachmentDownloadResponder`
UNMODIFIED — never an inline `new Response(...)`, for any `kind`. The
responder forces `Content-Disposition: attachment` (never `inline`) and
`X-Content-Type-Options: nosniff`. This matters specifically for `kind:
floor_plan`: `image/svg+xml` is an accepted MIME type, and an SVG is active
content (it may embed `<script>`). A `floor_plan` attachment must NEVER be
served with an inline disposition — that would let a browser execute an
uploaded SVG's script in the app's origin (stored XSS). Content-sanitization
of an uploaded SVG is deliberately OUT OF SCOPE: the security boundary is
enforced entirely on the read side by the attachment disposition, not by
rejecting or rewriting the upload. Browser `<img>`/blob rendering (the
frontend floor-plan viewer's use case) is unaffected by the attachment
disposition and does not execute an SVG's embedded scripts — only navigating
or framing the raw response directly would, and the disposition is exactly
what prevents that. Authorization mirrors every other read surface in this
section: `organization.facilities.read`, checked inline in the controller
(the same `resolveAccess()`/`isOutsideScope()`/`isGranted()` pattern as
`FacilityMediaProvider`) — 404 for a caller outside the owning organization,
403 for a member missing the permission.

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

### Spatial zone geometry (Phase 4)

A zone stays a plain `Facility` node — `type: zone` is a convention, not a
different aggregate — that gains an optional `planGeometry` bound to an
ancestor's `floor_plan` attachment: `Facility\Domain\ValueObject\PlanGeometry`
(`attachmentId`, and `points`, a polygon of at least 3 vertices, each
coordinate a float normalized to `[0, 1]` — a fraction of the plan image's
width/height, not a pixel, so the shape survives the plan being re-rendered
at a different resolution). Serialized on `facilities.plan_geometry`
(`JSONB`, main database, `Version20260816120000`) as
`{"attachmentId": "<uuid>", "points": [[x, y], ...]}`.

**Write — `PUT /organizations/{organizationId}/facilities/{facilityId}/plan-geometry`.**
`SetFacilityPlanGeometryInput` carries `attachmentId` and `points` together:
both present sets or replaces the geometry, both `null` clears it — a
PUT-with-null-to-clear shape mirroring `MoveFacilityInput`'s
`parentFacilityId`, rather than a separate DELETE route (this module has no
DELETE-as-clear convention; POST-verb-actions and PUT-with-null both already
exist here). `SetFacilityPlanGeometryHandler` validates, through ports and
BEFORE the durable save:

1. the attachment exists (`FacilityAttachmentNotFoundException`, **404**),
2. it is `kind: floor_plan` (`FacilityAttachmentNotFloorPlanException`,
   reused from the primary-plan flow, **409**),
3. it belongs to the target facility itself or to one of its ancestors —
   `Facility\Application\Service\FacilityAttachmentAncestryGuard` walks
   `parentFacilityId` upward (cycle-safe, stops at the first organization
   mismatch), shared with the read side below
   (`FacilityAttachmentNotAncestorException`, **409**),
4. the polygon's own invariants (point count, coordinate bounds) —
   enforced inside `PlanGeometry`'s constructor, never re-checked in
   Presentation (`InvalidArgumentException`, **400**).

An archived facility MAY still receive or clear a plan geometry — this
write is not a lifecycle action and does not use `findPublishedById`,
matching `UpdateFacilityHandler`'s convention rather than
`Move`/`Archive`/`Restore`'s. Setting a geometry emits no domain event
today (deliberately — unlike move/archive/restore, this is not yet wired
into the audit ledger).

**Read — `GET /organizations/{organizationId}/facilities/{facilityId}/plan-overlay?attachmentId=<id>`.**
Resolves one floor plan — the explicit `attachmentId`, or this facility's
own primary plan when the parameter is omitted
(`FacilityAttachmentRepositoryPort::findPrimaryFloorPlan()`) — and returns
`{attachmentId, imageWidth, imageHeight, zones: [{facilityId, name, type,
status, points}]}` for every PUBLISHED facility, self-or-descendant of the
path's `facilityId`, whose `planGeometry.attachmentId` matches: a single
recursive CTE joined with a `plan_geometry ->> 'attachmentId'` filter
(`FacilityRepositoryPort::findZonesForPlanAttachment()`), never a
descendants query followed by N geometry reads. An explicit `attachmentId`
still goes through the same kind and ancestry checks as the write path. The
Output DTO additionally carries `equipment: [{equipmentId, type, serialNumber,
locationLabel, status, x, y}]` — every equipment item, scoped to the
organization, whose
`Equipment\Domain\ValueObject\PlanPosition` references the same attachment,
resolved cross-module through
`Facility\Application\Port\Outbound\FacilityEquipmentPlanPositionPort`
(implemented by `Equipment\Infrastructure\Adapter\Facility\EquipmentPlanPositionAdapter`,
mirroring `FacilityEquipmentDependencyPort`'s direction — Facility declares
the port, Equipment's Infrastructure supplies the data). **The identity travels in parts, not as a label.** Equipment has no name
field, so this endpoint used to compose one and sent
`"gas_detector (SEED-GAS-003)"` — a raw enum value a client can only print
verbatim, in English, underscore included. Naming the thing belongs to the
client: it owns the translated type catalogue and it alone knows the user's
locale. `locationLabel` is the operator's own words for where the item sits
and is usually the best label of the three. See
`src/Equipment/MODULE.md` for the write side
(`PUT .../equipment/{id}/plan-position`) and the
`EquipmentFloorPlanValidationPort` this module implements in the other
direction.

**Read — `GET /organizations/{organizationId}/facilities/{facilityId}/building-model`
(A3).** For a `building` facility, assembles the ordered stack of floors a 3D
viewer extrudes: `{buildingId, buildingName, floors: [{facilityId, name,
levelIndex, elevationMeters, heightMeters, status, plan, outline, rooms,
equipment, diagnostics}]}`. `FacilityNotFoundException`
(unknown facility, or one belonging to another organization — same message,
no oracle) is **404**; `FacilityNotBuildingException` (the target facility's
`type` is not `building`) is **409**. Both are mapped centrally
(`config/packages/api_platform.yaml`); `FacilityBuildingModelProvider` holds
no try/catch — it only resolves the `organization.facilities.read` gate
through `OrganizationAuthorizationPort::resolveAccess()`, dispatches
`GetFacilityBuildingModelQuery`, and maps the Result.

Nothing beyond 403/404/409 is an error. A building with no floors answers
`200` with `floors: []`; a floor with no primary plan answers `plan: null`;
a floor with no room answers `rooms: []`.

Each floor's `outline` follows a strict cascade, recorded in `source`:

1. `plan_geometry` — the floor's own `planGeometry`, only when it is
   expressed in the floor's own primary-plan coordinate space (an
   ancestor's plan is a different frame and unusable here);
2. `rooms_bbox` — the axis-aligned bounding box of the floor's retained
   rooms;
3. `image_rect` — the unit rectangle `[[0,0],[1,0],[1,1],[0,1]]`, only when
   the floor has a primary plan but no room to bound it;
4. `null` — none of the above applies.

`rooms` keeps geometric leaves only: among a floor's rooms, one nested
inside another room on the _same floor_ (an `area` inside a `zone`) is
dropped, so a 3D view never receives two overlapping volumes. The kept
shape is byte-for-byte `GetFacilityPlanOverlayResult::$zones`
(`facilityId, name, type, status, points`) — the frontend reuses the same
TypeScript models for both endpoints. All of this lives in
`GetFacilityBuildingModelHandler`, over `FacilityRepositoryPort`'s
`findBuildingFloors()`/`findRoomsForFloors()` and the Equipment-owned
`FacilityEquipmentPlanPositionPort` batch projection. Equipment records are included
only when the caller also has `organization.equipment.read`; their actual assignment
is retained while the nearest floor resolves their display context. A position on
another attachment is not moved to the current primary plan. Placement diagnostics
distinguish unplaced equipment, missing plans, other plans and invalid coordinates.

### Plan calibration and physical floor dimensions

Normalized zone vertices and equipment positions remain relative to their original
attachment. An optional plan calibration contains `widthMeters`, `rotationDegrees`,
`offsetXMeters` and `offsetZMeters`; the image aspect ratio derives the physical depth.
`PUT /api/facility-attachments/{id}/calibration` accepts `{calibration: object|null}`
and requires facilities-write entitlement and `If-Match: "revision-N"`. A successful
write persists and returns the attachment's new revision. Nullable `elevationMeters`
and positive `heightMeters` describe floors; `levelIndex` remains ordering metadata.
Deleting a plan clears references to that attachment instead of transferring them.
New polygons must have positive area and must not self-intersect. Invalid historical
geometry is omitted from extrusion with a diagnostic rather than breaking the model.

### Imported building models

Facility models are separate immutable GLB assets, not document or floor-plan attachments.
The building-scoped collection `/api/organizations/{organizationId}/facilities/{facilityId}/models`
supports GET and multipart POST (`file`). `/api/facility-models/{id}` supports GET,
PATCH of the full transform and bindings, and DELETE; `/activate` supports POST and
`/download` supports authenticated GET. Mutations of existing models use their revision
ETag. Authorization follows facilities-read/write and masks foreign organization records.

The model transform uses positive uniform scale, rotation about Y in degrees and XYZ
translation in metres. Associations bind immutable source node indices to existing
facilities in the building; display names are not identifiers. Several nodes may share
one facility. Replacement files have distinct identities and associations. Activation
atomically selects one model for the building.

Uploads accept autonomous GLB 2.0 files up to 10 MiB, validate the container and indexed
scene content, and reject external resources and unsupported required extensions.
The Docker PHP configuration permits 12M uploads and 16M multipart requests; any
deployment proxy must allow the latter. IFC and separate glTF resource bundles are
outside this contract. Storage and persistence remain on the `main` database.

After `make test-db`, `php -d memory_limit=1G bin/check-facility-model-http.php` runs a socket HTTP
smoke against private database and storage clones. It verifies a multipart upload
at exactly 10 MiB, byte-identical authenticated download, rejected oversized and
corrupt files, and deletion. Rebuild the PHP container to apply the upload limits.
The test server uses the deployed 256M PHP memory limit; the higher parent limit
is only for compiling the isolated test kernel.

`floors` remains an array-typed projection rather than a list of nested DTOs.
Its explicit OpenAPI schema documents every nested field, nullable value and
placement diagnostic; frontend contract tests validate populated metric floors.
Nested nulls are emitted, not omitted: `plan`, `outline`, `levelIndex`, metric
dimensions and missing positions use explicit nulls inside the projection.

### Metadata schema (organization-defined typed fields)

| Method | Path                                                                | Description                                        |
| ------ | ------------------------------------------------------------------- | -------------------------------------------------- |
| POST   | `/api/organizations/{organizationId}/facility-metadata-fields`      | Create a typed metadata field definition           |
| GET    | `/api/organizations/{organizationId}/facility-metadata-fields`      | List the organization's metadata field definitions |
| PATCH  | `/api/organizations/{organizationId}/facility-metadata-fields/{id}` | Partially update a metadata field definition       |
| DELETE | `/api/organizations/{organizationId}/facility-metadata-fields/{id}` | Delete a metadata field definition                 |

Facility `metadata` was an untyped `Record<string, string\|null>` free-for-all.
This lets an organization define its own typed schema for it — EU-generic, no
national fire-safety regime presumed: `key` (machine key, kebab/snake,
unique per organization), `label`, `fieldType`
(`text`\|`number`\|`date`\|`boolean`\|`select`), `options` (only for
`select`, ≥2 unique non-blank values), an optional `facilityType` scope
(null = every type), `required`, and an optional `unit` (≤16 chars). Capped
at **50** definitions per organization (`422` once reached).
`GET /facility-metadata-fields` is designed to serve as the frontend's
form-schema source
(`Facility\Presentation\Api\Dto\Output\MetadataField\FacilityMetadataFieldOutput`)
— **no frontend surface consumes it today** (neither a schema-admin screen nor
a schema-driven metadata form exists; the web app does not even render the
free `metadata` field). Building that UI is explicitly out of scope for now
(decision 2026-08-20); the contract stays because `FacilityMetadataSchemaGuard`
enforces it server-side on every facility create/update, so the endpoints are
load-bearing regardless of UI. It is a business resource, not a reference
catalog, because the organization owns and edits its own values — so its
provider gates on `organization.facilities.read` explicitly, the same
403-vs-404 scope rule as every other Facility provider (see below).

**Deleting a field definition does not touch any facility's stored
`metadata` values** — they simply become "unschema'd" free-form entries
again, matching the compatibility rule below. This is deliberate: retiring a
field must not retroactively invalidate historical data.

**Validation and compatibility rule**, enforced by
`Facility\Application\Service\FacilityMetadataSchemaGuard` and called from
every facility metadata write path — `CreateFacilityHandler`,
`UpdateFacilityHandler`, `CanonicalFacilityMutationProcessor` (the flat
canonical PATCH), and `FacilityInterventionResourceAdapter::apply()` (the
offline intervention path):

- When an organization has **no** field definitions, every metadata payload
  passes untouched — this feature is strictly additive over the pre-existing
  free-form contract.
- When definitions exist, only the metadata keys that **match** a
  definition (and whose `facilityType` scope applies, or is null) are
  checked against that definition's type. Every other key is passed through
  unexamined — an unschema'd key is never rejected. This is what lets the
  old free-form usage and the new typed schema coexist.
- Type parsing: `number` accepts int/float; `boolean` accepts bool; `date`
  accepts an ISO 8601 date or date-time; `select` accepts a string present
  in the definition's `options`; `text` accepts any string.
- `required` is enforced on **create only**. A partial PATCH (canonical or
  the dedicated endpoint) is never rejected for omitting a required key it
  never touched — merge-patch semantics, matching every other Facility
  field.
- A `null` value for a schema'd key is treated as "not provided" (skipped),
  not as a type failure.

`FacilityMetadataValidationException` carries the offending keys and is
mapped centrally to **422** by
`Facility\Presentation\Api\EventSubscriber\FacilityMetadataValidationExceptionSubscriber`
(mirrors `Shared\Presentation\Api\EventSubscriber\AttachmentConstraintExceptionSubscriber`),
because the guard is called from three different write paths whose HTTP
mapping must agree.

## Permission Model

This module relies on Organization-scoped permissions:

- `organization.facilities.read`
- `organization.facilities.write` (also covers attachments — see above)

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
- `levelIndex` (optional, signed integer, range [-100, 200]) — stacking order of a floor
  within its parent building: ground floor `0`, basement `-1`. Semantically meaningful on
  `type: floor`, but accepted on every type: the hierarchy is homogeneous everywhere else and
  this is not the place to introduce the first type-dependent constraint. **Duplicates between
  sibling floors are tolerated** — no unique index, because a subtree move would produce
  transient collisions; consumers order by `level_index ASC NULLS LAST, created_at ASC, id ASC`,
  so unset levels stack after the ordered ones in creation order. The range is enforced in the
  **domain** (`Facility::normalizeLevelIndex`, `CanonicalFacility::applyPatch`), not only by the
  DTO's `Assert\Range` — the canonical PATCH surface cannot bypass it. Unlike `planGeometry`, it
  is exposed on collections as well as on the detail read.
- `metadata` (JSON object)
- `planGeometry` (optional, `{attachmentId, points}`, Phase 4 — see the
  "Spatial zone geometry" section above)
- `createdAt`, `updatedAt`

> **Adding a scalar column here takes two edits, not one.** `FacilityRepository::save()` writes
> a new row through `FacilityMapper::toRecord()`, but for an **existing** row it copies the
> aggregate field by field onto the managed record. A field added to the mapper and forgotten in
> that copy block persists on create and is **silently dropped on every update** — the PATCH
> still echoes the value, because the response serializes the in-memory Result, so only a read
> _after_ a write exposes it. `levelIndex` shipped with exactly that hole; the regression test is
> `FacilityApiTest::testPatchingLevelIndexSurvivesTheNextDetailRead`, which does POST → PATCH →
> GET rather than trusting the PATCH body. The canonical surface is unaffected: it goes through
> `CanonicalFacilityMapper::applyTo()`, a separate copy list.

Aggregate:

- `FacilityMetadataField` — an organization-defined typed metadata field
  definition: `id`, `organizationId`, `key`, `label`, `fieldType`,
  `options`, `facilityType` (optional), `required`, `unit` (optional),
  `createdAt`, `updatedAt`. See "Metadata schema" above.

## Persistence

- Table: `facilities` (main database)
- Doctrine mapping: `src/Facility/Infrastructure/Persistence/Doctrine/Record`
- Migration: `migrations/main/Version20260212120000.php`
- Migration (coordinates): `migrations/main/Version20260708120000.php`
- Migration (plan geometry): `migrations/main/Version20260816120000.php` —
  `plan_geometry JSONB NULL`, hand-written rather than Doctrine-diffed so the
  physical column is `JSONB` (indexable, used by the plan-overlay CTE's
  `->>'attachmentId'` filter) while the ORM mapping stays the same `json`
  DBAL type as `metadata`.
- Migration (level index): `migrations/main/Version20260830141438.php` —
  `level_index INT NULL` plus the composite index `idx_facility_parent_level
(parent_facility_id, level_index)`, which the floor ordering reads. The index is not
  Doctrine-diffable, so it is hand-written in the migration **and** declared as an
  `#[ORM\Index]` on `FacilityRecord`; without the attribute `doctrine:schema:validate`
  reports it as untracked drift forever.
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
- `facility_attachments` gains `kind` (`VARCHAR(20) NOT NULL`),
  `is_primary_plan` (`BOOLEAN NOT NULL`), `image_width`/`image_height`
  (`INT NULL`), plus the partial unique index
  `uniq_facility_attachment_primary_plan ON facility_attachments (facility_id)
WHERE is_primary_plan` — Migration: `migrations/main/Version20260816110904.php`
  (Facility plan Phase 3).
  Both columns were added with a `DEFAULT` (`'document'` and `false`), because
  `ADD COLUMN ... NOT NULL` is rejected on a non-empty table without one. The
  defaults were backfill scaffolding, not contract: the mapping never declared
  them, so they put `main` out of sync with its mapping until
  `migrations/main/Version20260826230000.php` dropped them. Doctrine always
  writes both columns, so nothing in the application could reach a default —
  only a bug could, and it would then be absorbed instead of failing.
  The partial index itself is unmappable in ORM attributes and is re-declared in
  `Shared\Infrastructure\Doctrine\PartialIndexSchemaListener`, predicate
  `is_primary_plan` — a bare boolean, no parentheses and no cast, unlike the
  `((kind)::text = 'signature'::text)` form a varchar comparison normalizes to.
- Table: `facility_metadata_fields` (main database) — `organization_id` FK
  `ON DELETE CASCADE`, unique `(organization_id, field_key)`. Migration:
  `migrations/main/Version20260816165736.php`. Repository:
  `Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityMetadataFieldRepository`.
  Deleting the organization cascades its field definitions; it never touches
  any facility's stored `metadata` values (separate table, no FK between
  them).

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
  while the facility has an active descendant facility, active equipment, an
  in-progress inspection, or an active (not closed) intervention targeting it
  as its site. The equipment/inspection/intervention checks are provided by
  the owning modules through `FacilityEquipmentDependencyPort` /
  `FacilityInspectionDependencyPort` / `FacilityInterventionDependencyPort`
  (outbound, adapters in Equipment/Inspection/Intervention). "Active" for the
  intervention check means any status outside `InterventionStatus::closedValues()`
  (i.e. not `published` or `abandoned`) — including `draft`.
  **Documented gap**: `intervention_recurrences.site_id` is not covered by this
  guard (recurrence materialization reads it independently of the archival
  check), so an archived facility can still receive newly materialized
  interventions from an existing recurrence — a follow-up candidate.
- `Facility\Infrastructure\Adapter\Organization\FacilitySearchAdapter` implements
  the Organization module's `FacilitySearchPort` for the organization global
  search (`GET /organizations/{organizationId}/search`) — bounded org-scoped
  `LIKE` over name/code/address, published records only.
- **Equipment plan-position cross-module pair (Phase 4)**: two ports, one in
  each direction, both scoped to this feature only. Outbound —
  `FacilityEquipmentPlanPositionPort::findEquipmentPlacedOnPlan()`, consumed
  by `GetFacilityPlanOverlayHandler`, implemented by Equipment
  (`EquipmentPlanPositionAdapter`). Inbound-from-Equipment's-perspective —
  `Equipment\Application\Port\Outbound\EquipmentFloorPlanValidationPort`,
  **implemented here** by
  `Facility\Infrastructure\Adapter\Equipment\EquipmentFloorPlanValidationAdapter`,
  reusing `FacilityAttachmentAncestryGuard` as-is. The port's typed
  `@throws` contract (`FloorPlanAttachmentNotFoundException` /
  `FloorPlanAttachmentNotFloorPlanException` /
  `FloorPlanAttachmentNotAncestorException`) is made of **contract
  exceptions** under `Equipment\Application\Contract\FloorPlan\` —
  Equipment's declared error surface for this port — so the adapter imports
  nothing of Equipment beyond `Application\Port\` and
  `Application\Contract\`, staying inside the cross-module boundary rule.
  Facility's own `FacilityAttachmentNotAncestorException` (Domain) is caught
  in the adapter and translated to the contract type at the boundary.
- **3D building model**:
  `Application/UseCase/Query/Facility/GetFacilityBuildingModel/GetFacilityBuildingModelHandler`
  assembles a building's ordered floor stack for a 3D viewer to extrude,
  entirely from `FacilityRepositoryPort::findBuildingFloors()` /
  `::findRoomsForFloors()` — both raw-row reads, no new port. Refuses a
  non-`building` facility with `FacilityNotBuildingException` (409). Two
  rules live in the handler, not the repository: a **geometric-leaf**
  filter drops any room that is another same-floor room's declared parent
  (an `area` nested in a `zone` would otherwise double-render), and an
  **outline cascade** per floor — the floor's own `planGeometry` only when
  expressed in its own primary-plan coordinate space, else the bounding box
  of its retained rooms, else the unit image rectangle when a primary plan
  exists, else `null`. `GetFacilityBuildingModelResult::$floors[]['rooms']`
  is byte-for-byte `GetFacilityPlanOverlayResult::$zones`'s shape on purpose
  — the frontend reuses the same models for both.
- Canonical DELETE = archive — the only REVERSIBLE retirement state (restore is
  refused while the parent is archived). Idempotent: a repeat DELETE is a no-op.
- The descendants listing and the archival probe (`hasActiveDescendants`) run on
  a single recursive CTE over PUBLISHED records: draft intervention scratchpads
  are invisible to both, and archived intermediate nodes are traversed so a live
  descendant beneath them is still found.
- Regulated actions emit domain events (`src/Facility/Domain/Event/`) recorded
  in the audit ledger by Audit's `ResourceAuditEventSubscriber`: `facility.created`,
  `facility.archived`, `facility.restored`, `facility.moved` (previous/new
  parent in metadata), `facility.updated` (`changedFields` — the field
  NAMES that changed, never their values, keeping PII/noise such as address
  and metadata contents out of the ledger), and `facility.subtree_duplicated`
  (new root id and node count in metadata). Emission sites: the
  Create/Update/Archive/Restore/Move/DuplicateFacilitySubtree handlers —
  Create and Update dispatch directly after their durable save
  (`CreateFacilityHandler`, `UpdateFacilityHandler`),
  Archive/Restore/Move/DuplicateFacilitySubtree load through
  `findPublishedById` (draft scratchpads are unreachable) — and the canonical
  processor, which COLLECTS its events during the mutation and dispatches
  them only after `wrapInTransaction` commits (no phantom ledger row on
  rollback). Idempotent repeats, same-parent moves, and no-op patches (a
  PATCH that re-sends the current value for every field) emit nothing; a
  subtree duplication always emits exactly one event (never one per cloned
  node) once the whole clone batch has committed.
  `facility.updated`'s changed-field detection compares actual before/after
  values, not merely which keys a merge-patch body carried, and never lists
  `status` or `parent` — those are covered by their own dedicated events.
  Both the resource-scoped `POST /facilities` and the canonical `PUT
/facilities/{id}` upsert route through `CreateFacilityProcessor` into the
  same `CreateFacilityHandler`/`CreateFacilityCommand`, so both emit exactly
  one `facility.created`; the canonical processor is therefore extended only
  for the PATCH branch's `facility.updated`, not for create. The intervention
  `apply()` path is deferred to the `intervention.published` audit action.
  Note: a dedicated Equipment subscriber for `FacilityArchived` (once planned
  for reconciliation) is deliberately NOT built — the complete archival guard
  already refuses to archive a facility with active equipment, so there is
  never anything to reconcile. `facility.created` and `facility.updated` are
  also in Webhook's curated allowlist (`WebhookEventCatalog`,
  `WebhookEventType`) alongside the pre-existing `facility.archived` /
  `facility.restored` — see `src/Webhook/MODULE.md`.
- **Subtree duplication and the plan quota**: `DuplicateFacilitySubtreeHandler`
  needed to check the `facilities` quota for N nodes atomically, which
  `OrganizationQuotaPort::assertCanAdd()` (one node at a time) cannot express.
  `Organization\Application\Port\Inbound\OrganizationQuotaPort::assertCanAddMultiple()`
  was added for this — same advisory lock as `assertCanAdd()`, batched count —
  implemented by `Organization\Application\Service\OrganizationQuotaService`.
  On refusal it throws
  `Organization\Application\Contract\Quota\OrganizationQuotaExceededException`
  — a contract type, so `DuplicateFacilitySubtreeProcessor` catches it without
  importing `Organization\Domain`. See `src/Organization/MODULE.md`.
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
- **Hierarchy depth cap**: the hierarchy has a configurable maximum depth
  (default 8, root = level 1; env `FACILITY_MAX_DEPTH`) so a pathological
  chain cannot degrade the recursive CTEs (`findDescendants`,
  `hasActiveDescendants`) or a tree UI. `FacilityRepositoryPort::depthOf()`
  and `::subtreeHeight()` compute both over the PUBLISHED tree only (a
  single recursive CTE each, mirroring `hasActiveDescendants`'s style).
  Enforced as: no facility may end up at `depth > cap`. A facility gaining a
  parent checks `depth(parent) + 1 <= cap`; reparenting an existing facility
  (which may carry a sub-tree) checks `depth(newParent) + 1 +
subtreeHeight(moved) <= cap`, so the whole moved sub-tree — not just its
  root — is accounted for. Enforcement sites: `CreateFacilityHandler`,
  `MoveFacilityHandler`, the canonical PATCH `parent` path
  (`CanonicalFacilityMutationProcessor`, mapped to
  `UnprocessableEntityHttpException`/422, mirroring its existing cycle-check
  status), and the offline intervention `apply()` parent patch
  (`FacilityInterventionResourceAdapter`, mapped to
  `InterventionConflictException`, mirroring its existing cycle check). The
  canonical PUT/POST create path reuses `CreateFacilityHandler` and needs no
  separate check. `FacilityProvisioningService` translates the violation
  (`FacilityHierarchyException::maxDepthExceeded`) into
  `ProvisionOutcome::INVALID` like every other hierarchy failure — no new
  handling was needed there.

- **Metadata schema guard**: `Facility\Application\Service\FacilityMetadataSchemaGuard`
  is called from `CreateFacilityHandler`, `UpdateFacilityHandler`,
  `CanonicalFacilityMutationProcessor`, and
  `FacilityInterventionResourceAdapter::apply()` — see "Metadata schema"
  above for the validation and compatibility rules.

- **Bulk CSV import v2 — dry-run mode**: `ProvisionFacilityRequest` carries an
  optional `dryRun` (default `false`), `quotaProjectionOffset` (default `0`)
  and a projected graph plus pending code-to-id map (facility-only, dry-run only). When `dryRun`,
  `CreateFacilityCommand` also carries `dryRun`/`quotaProjectionOffset`, and
  `CreateFacilityHandler` takes a second branch: it still builds and
  validates the `Facility` aggregate (so every structural/domain invariant
  still applies) but never enters the transactional save — instead it calls
  `OrganizationQuotaPort::assertProjectedCanAdd()` (no advisory lock; a
  projection against `getLimit()`/`getUsage()` plus the caller's offset, for
  a caller — Import's dry run — walking many candidate rows in one pass with
  nothing persisted yet) and returns a `CreateFacilityResult` built from the
  unsaved aggregate. `FacilityProvisioningService.provision()` additionally
  resolves `parentCode` to the stable id of an earlier projected parent or
  an existing database parent. Every simulated relationship and its ancestry
  is validated through the same hierarchy guard; parent ids are never replaced
  by null to bypass a check. Import derives UUID v5 ids from job/row identity
  and reconstructs confirmed nodes on resume without a creation/quota/write.
  See `src/Import/MODULE.md` for the row-report and resumption contracts.

**Architecture debt — cross-module `Organization\Domain` imports (2).** The
2026-08-18 quota-contract migration retyped `OrganizationQuotaPort`'s whole
surface with `Organization\Application\Contract\Quota` types (the resource
enum and the quota-exceeded exception — see `src/Organization/MODULE.md`), so
the quota-related Domain imports this note used to document are gone and the
`CrossModuleDomainBoundaryTest` baseline for `Facility => Organization`
shrank 6 → 2. The two survivors —
`Organization\Domain\ValueObject\OrganizationId` in `ArchiveFacilityHandler`
and `CreateFacilityConsoleCommand` — are unrelated to quotas; the eventual
fix is Organization publishing a contract identifier type. Do not add a
third import; extend the contract surface instead.

**`Presentation` no longer reads `Organization\Infrastructure` — closed 2026-08-26.**
It was 2. `CanonicalFacilityMutationProcessor`'s uses were `instanceof
OrganizationRecord` on a property the ORM already types `?OrganizationRecord`,
so a null check replaced them. `CanonicalFacilityProvider`'s was an existence
lookup that `resolveAccess()` had made redundant: OUTSIDE_SCOPE answers the same
404 for an organization with no membership row, and an unknown id necessarily
has none. Both entries are gone from
`PresentationInfrastructureBoundaryTest`'s baseline.

One message changed with it. The collection route answered `'Organization not
found.'` for an unknown organization and `'Facility not found.'` for one the
caller was not in; both now answer `'Facility not found.'`. Same status, and an
identical body for the two cases instead of a distinguishable one.

**The coupling underneath did not go away.** `FacilityRecord` still declares
`#[ORM\ManyToOne(targetEntity: OrganizationRecord::class)]`. That is
schema-level; what closed here is Presentation reading another module's table,
not the association itself. Do not plan a refactor expecting the latter to have
disappeared.

## Error Codes

| Exception                                                                    | HTTP status | When                                                                                                                                                                         |
| ---------------------------------------------------------------------------- | ----------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `FacilityNotFoundException`                                                  | 404         | Source facility missing, out of the caller's organization scope, or draft-only; also the target parent when explicitly provided                                              |
| `FacilitySubtreeSourceArchivedException`                                     | 409         | Duplication requested for an archived source facility                                                                                                                        |
| `FacilitySubtreeTooLargeException`                                           | 422         | Source facility's subtree (including archived nodes) would traverse more than 500 nodes                                                                                      |
| `Organization\Application\Contract\Quota\OrganizationQuotaExceededException` | 409         | The whole clone count would exceed the organization's `facilities` plan quota                                                                                                |
| `FacilityHierarchyException` / `InvalidArgumentException`                    | 400         | Malformed input, or an invalid/out-of-organization target parent                                                                                                             |
| `FacilityAddressNotFoundException`                                           | 404         | Geocode lookup: the provider knows no coordinates for the submitted address (mapped centrally via `api_platform.exception_to_status`, FG-035 — the provider is catch-free)   |
| `FacilityAccessDeniedException`                                              | 403         | Caller is in the organization but lacks the required `organization.facilities.*` permission (now also in `api_platform.exception_to_status` for the catch-free geocode path) |

All other domain exceptions raised by this module map the same way as the
other Facility endpoints (see the create/archive/move handlers).

### The canonical facility mutations run on use cases (2026-08-26)

`CanonicalFacilityMutationProcessor` was 444 lines — the largest of the four
canonical processors — holding three `persist`/`flush`/`remove` sites, the
whole hierarchy guard set (cycle walk, depth cap, archived parent), the
restore rule, the archival dependency guard, the changed-field bookkeeping and
the four audit events with their post-commit dispatch. It is now HTTP
translation only, and **holds no entity manager**.

| Concern                                                                                        | Where it lives now                                                                                  |
| ---------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------- |
| `PATCH /api/facilities/{id}`                                                                   | `Application/UseCase/Command/Facility/PatchCanonicalFacility/`                                      |
| `DELETE /api/facilities/{id}`                                                                  | `Application/UseCase/Command/Facility/DeleteCanonicalFacility/`                                     |
| Read one, for the gate                                                                         | `Application/UseCase/Query/Facility/GetCanonicalFacility/`                                          |
| Field assignment, trimming, restore rule, revision bump, changed-field set, idempotent archive | `Domain/Model/Facility/CanonicalFacility`                                                           |
| Persistence, child count, ancestry walk                                                        | `Infrastructure/…/Repository/CanonicalFacilityRepository` (port: `CanonicalFacilityRepositoryPort`) |
| Intervention revision touch                                                                    | `Facility\Application\Port\Outbound\InterventionScopePort`                                          |

**Two Domain models over one table**, the same split the Inspection and
Equipment modules made on the same day and for the same reason: the
`Facility` aggregate does not carry `record_status`, `intervention_id` or
`revision`. Doctrine now owns the shared optimistic version column: legacy saves,
canonical mutations and publication all advance the same persisted revision. A stale
ORM write fails with 412 instead of overwriting a newer record. `src/Inspection/MODULE.md` carries the long-form
account.

**The validation order is load-bearing and alternates between pure checks and
external ones**, which is why the handler spells it out step by step instead
of hiding it in the model: descriptive fields (`type`, `name`, the coordinate
pair) → the organization's metadata schema → `status` → everything about the
parent (existence and ownership, cycle, archived parent, depth cap). That is
the order the processor ran, and therefore the message a client sending
several invalid fields at once observes.

**Three rules that are easy to get subtly wrong, and are each pinned by a
test:**

- **The restore guard reads the EFFECTIVE parent.** A patch that only flips
  `status` back to `active` still has to be judged against the parent the
  facility already hangs from — otherwise a facility comes back to life inside
  an archived subtree. `CanonicalFacility::wouldRestore()` is what lets the
  handler pay for that extra parent read only when it matters.
- **The changed-field list reports what DIFFERS, not what the body carried.**
  Resending the current value is a no-op; a same-parent move emits nothing.
  An audit event saying "name changed" when it did not is worse than no event.
- **The repeat DELETE skips the archival guard.** An idempotent no-op must
  stay a no-op, not start failing because a dependent appeared after the
  facility was retired.

**The canonical DELETE contract**, unchanged: a draft scratchpad row is
hard-deleted — refused while it still has children (`ON DELETE SET NULL` would
silently promote the sub-tree to root) or live dependents; a published one
retires to `archived`, the **only reversible** retirement state of the three
canonical surfaces; a repeat DELETE is an idempotent no-op.

**Audit events are still dispatched after the commit**, now by the handlers.
One patch can produce three of them — a move, a status transition and a
descriptive update are independent facts — and a rollback must produce none.

**What deliberately stayed in the processor**: the authorization gate (the
permission depends on the request, and a row loaded by GLOBAL id must answer
404 rather than 403 outside the caller's organization), `MergePatchFields`
plus the parent IRI parse, and the output (`CanonicalFacilityProvider` joins
counts and ancestry the write path has no reason to carry).

## Configuration

Maintenance exports validate published root-site identities through the owner-hosted
`MaintenanceExportSiteIdentityAdapter`, explicitly wired to main and tagged
`maintenance_export.identity_validator`. Archived root sites remain valid historical
references; drafts, buildings, nested sites and foreign identities return false.

- Service wiring: `config/modules/facility.yaml`
  - `CanonicalFacilityRepositoryPort` is aliased to
    `CanonicalFacilityRepository`, wired to `main` explicitly. It is a
    **second** port over the `facilities` table, next to
    `FacilityRepositoryPort` — see the architecture section for why one cannot
    serve both.
  - `Facility\Application\Port\Outbound\InterventionScopePort` is aliased to
    `Intervention\Infrastructure\Adapter\Facility\InterventionScopeAdapter`.
    It is the **third** declaration of that capability: Inspection and
    Equipment own theirs too, exactly as the `FacilityValidationPort`-shaped
    interfaces coexist.
  - `PatchCanonicalFacilityHandler` and `DeleteCanonicalFacilityHandler` name
    `@facility.main_transaction_manager` explicitly.
  - `CanonicalFacilityMutationProcessor` names **no** `$entityManager`, and
    must not: it holds none.
  - `DuplicateFacilitySubtreeHandler` is wired with the same
    `$transactionManager: '@facility.main_transaction_manager'` argument as
    `CreateFacilityHandler`, so the quota check and every clone insert run in
    one `main`-database transaction.
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`
- `FacilityEquipmentPlanPositionPort` is aliased to
  `Equipment\Infrastructure\Adapter\Facility\EquipmentPlanPositionAdapter` in
  `config/modules/facility.yaml` (adapter wired with
  `doctrine.orm.main_entity_manager` in `config/modules/equipment.yaml`, the
  module that hosts it).
- `Equipment\Application\Port\Outbound\EquipmentFloorPlanValidationPort` is
  aliased to `Facility\Infrastructure\Adapter\Equipment\EquipmentFloorPlanValidationAdapter`
  in `config/modules/equipment.yaml` (the port's owning module) — this
  module only registers the adapter service itself.
- `FACILITY_MAX_DEPTH` (env, optional, default 8): maximum facility
  hierarchy depth, root = level 1. Wired via `config/services.yaml`
  (`facility.hierarchy.max_depth`, `%env(int:default:
facility.hierarchy.max_depth_default:FACILITY_MAX_DEPTH)%`) and injected
  into the enforcement sites with `#[Autowire('%facility.hierarchy.max_depth%')]`.
- `Facility\Infrastructure\Persistence\Doctrine\Repository\FacilityMetadataFieldRepository`
  and `Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort`
  are wired with `$entityManager: '@doctrine.orm.main_entity_manager'`, same
  as every other Facility repository.
- `ExportFacilitiesHandler` is registered with the `messenger.message_handler`
  tag, same as every other query handler; it touches Doctrine only through
  `FacilityRepositoryPort`, so it names no `$entityManager` itself.
  `ExportFacilitiesController`, `FacilityCsvWriter`, and
  `FacilityExportCriteriaFactory` are plain autowired Presentation services
  under the `Facility\Presentation\` resource scan — none of them touch
  Doctrine directly, so none needs an `$entityManager` argument either.
  `getFacilityCodesByIds()` was added to `FacilityRepositoryPort` /
  `FacilityRepository` for the export's `parentCode` resolution; it reuses the
  already-wired `doctrine.orm.main_entity_manager`, no new alias needed.

- Address geocoding (2026-08-28):
  - `GEOCODING_BASE_URL` (env, default `https://nominatim.openstreetmap.org`):
    base URL of the service behind `GeocodingPort`. Free public Nominatim by
    default — no API key. `.env.test` pins it to an unroutable address so no
    test can reach the real service. See OPERATIONS.md.
  - `Facility\Application\Port\Outbound\GeocodingPort` is aliased to
    `Facility\Infrastructure\Adapter\Geocoding\NominatimGeocodingAdapter` in
    `config/modules/facility.yaml`. The adapter names **no** entity manager —
    it touches no database. It enforces the Nominatim usage policy itself:
    identifying `User-Agent` (the code-owned contact is recorded in the
    [installation appendix](../../docs/operations/current-installation.md)),
    3 s timeout, and a process-safe 1 req/s outbound throttle (a `LockFactory`
    lock — the schedulers' pattern — around a last-request timestamp kept in
    the shared cache pool). Results are cached 24 h per hashed normalized
    address through Shared's `CachePort`; definitive answers only (matches and
    provider-confirmed misses), never transport failures. Every failure is
    fail-soft `null` — geocoding never blocks facility management.
  - `facility_geocode` rate limiter (`config/packages/rate_limiter.yaml`):
    sliding window, 30/min, keyed by user id in `GeocodeAddressProvider`.
  - `GeocodeAddressHandler` is a `messenger.message_handler` like every other
    query handler; ports-only (`GeocodingPort` + `OrganizationAuthorizationPort`).

<a id="seeded-floor-plans"></a>

## Testing

Unit tests cover owned domain/use-case and HTTP translation contracts. Integration tests execute real PostgreSQL queries and persistence behavior. Functional/E2E tests preserve authorization, contextual isolation, replay and failure recovery.

Detailed cases and regression rationale are retained in the [Facility testing reference](../../docs/guides/testing.md#facility-testing-reference). Use the [testing guide](../../docs/guides/testing.md) for current commands and isolated database setup.

## Error Codes

| Domain exception                                          | HTTP status                                                                                                                                                                                                                                                   | Consumer sees                                                                                                                                                                                                                                                                                                                                                                                                                                    |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `FacilityHierarchyException::cannotUseSelfAsParent`       | 400                                                                                                                                                                                                                                                           | "A facility cannot be its own parent."                                                                                                                                                                                                                                                                                                                                                                                                           |
| `FacilityHierarchyException::parentInAnotherOrganization` | 400                                                                                                                                                                                                                                                           | "Parent facility must belong to the same organization."                                                                                                                                                                                                                                                                                                                                                                                          |
| `FacilityHierarchyException::hierarchyCycleDetected`      | 400                                                                                                                                                                                                                                                           | "Cannot move facility: hierarchy cycle detected."                                                                                                                                                                                                                                                                                                                                                                                                |
| `FacilityHierarchyException::maxDepthExceeded($cap)`      | 400 (Create/Move use cases, via the same `FacilityHierarchyException` catch as the other hierarchy errors); 422 on the canonical PATCH `parent` path (mirrors its existing cycle-check status); `InterventionConflictException` on the offline `apply()` path | "Facility hierarchy depth cap of `$cap` levels exceeded."                                                                                                                                                                                                                                                                                                                                                                                        |
| `FacilityHasActiveDependentsException`                    | 409                                                                                                                                                                                                                                                           | archival refused while an active child facility, active equipment, an in-progress inspection, or an active intervention exists                                                                                                                                                                                                                                                                                                                   |
| `FacilityMetadataFieldNotFoundException`                  | 404                                                                                                                                                                                                                                                           | Unknown id, or a field belonging to another organization (indistinguishable from unknown, see "Scope versus entitlement" above)                                                                                                                                                                                                                                                                                                                  |
| `FacilityMetadataFieldKeyAlreadyExistsException`          | 409                                                                                                                                                                                                                                                           | Duplicate `(organizationId, key)`                                                                                                                                                                                                                                                                                                                                                                                                                |
| `FacilityMetadataFieldLimitExceededException`             | 422                                                                                                                                                                                                                                                           | Organization already has 50 field definitions                                                                                                                                                                                                                                                                                                                                                                                                    |
| `FacilityMetadataValidationException`                     | 422                                                                                                                                                                                                                                                           | One or more `metadata` entries fail the organization's typed schema; mapped centrally by `FacilityMetadataValidationExceptionSubscriber` regardless of which write path raised it                                                                                                                                                                                                                                                                |
| `CanonicalFacilityValidationException`                    | 422                                                                                                                                                                                                                                                           | The canonical surface's refusals: a non-nullable field sent as null, an unsupported enum value, a half-supplied coordinate pair, an invalid/foreign/archived parent, a cycle, a depth-cap breach, and a restore under an archived parent. **Note the depth case: `FacilityHierarchyException` is 400, but the canonical surface wrapped its MESSAGE in a 422 rather than letting the exception surface — so this class answers 422 for it too.** |
| `CanonicalFacilityConflictException`                      | 409                                                                                                                                                                                                                                                           | Hard-deleting a draft scratchpad row that still has child facilities                                                                                                                                                                                                                                                                                                                                                                             |
| `FacilityRevisionMismatchException`                       | 412                                                                                                                                                                                                                                                           | `If-Match` lost the race between the scope read on the query bus and the mutation's own transaction                                                                                                                                                                                                                                                                                                                                              |
| `FacilityAccessDeniedException`                           | 403                                                                                                                                                                                                                                                           | Export endpoint: caller is inside the organization's scope but lacks `organization.facilities.read`                                                                                                                                                                                                                                                                                                                                              |
| `FacilityExportTooLargeException`                         | 422                                                                                                                                                                                                                                                           | Export endpoint: the filters match more than `ExportFacilitiesHandler::MAX_EXPORT_ROWS` (50 000) facilities                                                                                                                                                                                                                                                                                                                                      |
| `FacilityNotBuildingException`                            | 409                                                                                                                                                                                                                                                           | 3D building model query: the requested facility's `type` is not `building`, mapped centrally in `api_platform.exception_to_status` (mirrors `FacilityAttachmentNotFloorPlanException`'s 409)                                                                                                                                                                                                                                                     |

Every other domain exception in this module (facility hierarchy, archival
dependents, code conflicts, …) is mapped locally by its processor/provider,
following the module's existing convention.

### Address suggestions

`AddressSuggestionsPort` isolates Photon from the application. Searches require the same
organization scope and write permission as geocoding. Only street-and-city matches with
valid WGS 84 coordinates are selectable; house numbers remain optional. Successful
empty results differ from temporary failures (503). Requests are limited to 30/minute
per user and one uncached outbound request per second across workers, without waiting.
Successful responses, including empty results, are cached for 24 hours; provider failures
are never cached. HTTP duration is bounded to three seconds. `PHOTON_BASE_URL` is an
optional operator override; the application defaults to the public demo and production
should point it to a private instance for sustained traffic. This read-only capability
does not persist in either database.

## Durable onboarding setup

Creation accepts optional `onboardingSessionId` and `onboardingItemKey` together. These identify input previously prepared by the authenticated creator through Onboarding. The owner handler checks the session, step, input and pinned organization, then records its created identifier in the same `main` transaction as the resource and quota enforcement. A replay returns that resource without another quota consumption or event. Missing or incompatible preparation returns `onboarding_setup_conflict` (409), never a legacy fallback. Calls without either field keep their existing contract.

### Spatial mutation consistency

Published geometry changes enqueue a public `FacilityPlanGeometryChangedEvent` in the
same main transaction. Initial publication emits placement once; replaying publication
of an already published row emits nothing. Audit records placement, movement or clearing,
plan identifiers, revision and intervention without coordinates. Main-to-auth delivery
uses the shared outbox actor context and consumer receipts.

Canonical detail, direct plan writes and legacy update/move/archive/restore/duplicate
responses use the same complete persisted detail projection, including path, counts,
coordinates and revision. Creation retains the revision returned by intervention assignment.
Import/publication resources can be reread through that same canonical detail contract.
Publication validates attachment kind and ancestry, including a proposed parent change.
`floor_plan_outside_ancestry` and `attachment_not_floor_plan` return 409;
`resource_revision_conflict` returns 412 when another committed write won.

## Flows

Authorized commands enforce hierarchy, organization scope and lifecycle invariants before persistence. Related equipment/intervention operations use their owner-published contracts; geocoding remains a bounded external adapter read.
