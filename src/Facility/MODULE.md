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
| POST | `/api/organizations/{organizationId}/facilities` | Create a facility |
| GET | `/api/organizations/{organizationId}/facilities` | List facilities (filters: `includeArchived`, `type`, `status`, `parentFacilityId`, `rootsOnly`, `code`, `hasCoordinates`) |
| GET | `/api/organizations/{organizationId}/facilities/export` | Streams a bounded CSV export of facilities, same filter subset as the list endpoint plus `search`. Requires `organization.facilities.read`, resolved in `ExportFacilitiesHandler` (not the resource's coarse `ROLE_USER` gate). Bounded to `ExportFacilitiesHandler::MAX_EXPORT_ROWS` (50 000) matching rows — 422 past that. |
| GET | `/api/organizations/{organizationId}/facilities/geocode?address=…` | Server-side geocoding input aid: resolves a free-form address (1–300 chars) to `{ latitude, longitude, displayName }` through `GeocodingPort` (Nominatim behind `GEOCODING_BASE_URL`). Requires `organization.facilities.write` — write, not read: the lookup exists to FILL a facility's coordinates, and only write-entitled members may burn the shared outbound budget. Rate limited 30/min/user (`facility_geocode`); the adapter additionally throttles the aggregate outbound channel to 1 req/s (Nominatim policy). 404 when no coordinates match (plain not-found, no oracle at stake — an address is not a resource). Declared before the `{facilityId}` item route so `geocode` is never read as an id. |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}` | Get one facility (includes the ancestor `path` breadcrumb) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/children` | List direct children for lazy tree expansion (paginated) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/descendants` | List all descendants for bulk subtree reads |
| PATCH | `/api/organizations/{organizationId}/facilities/{facilityId}` | Update a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/archive` | Archive a facility |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/move` | Move a facility under another parent |
| PUT | `/api/organizations/{organizationId}/facilities/{facilityId}/plan-geometry` | Set or clear this facility's plan geometry (Phase 4) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/plan-overlay` | Read one floor plan, every self-or-descendant zone bound to it, and every equipment item pinned on it (Phase 4, equipment additive — see Equipment's MODULE.md) |
| GET | `/api/organizations/{organizationId}/facilities/{facilityId}/building-model` | Read the ordered stack of floors (outline + rooms) a 3D viewer extrudes for a `building` facility (A3) |
| POST | `/api/organizations/{organizationId}/facilities/{facilityId}/duplicate` | Duplicate a facility and its full subtree into a new branch |
| GET | `/api/facilities/{id}` | Canonical item read (includes the ancestor `path` breadcrumb) |
| GET | `/api/facilities?organization={iri}` | Canonical collection read, org- or intervention-scoped |

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

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/facilities/{facilityId}/attachments` | Upload a multipart file attachment. Optional `kind` field (`document`, the default, or `floor_plan`) |
| GET | `/api/facilities/{facilityId}/attachments` | List a facility's attachments (optional `?kind=document\|floor_plan` filter) |
| GET | `/api/facility-attachments/{id}` | Get one attachment |
| DELETE | `/api/facility-attachments/{id}` | Delete an attachment (requires `If-Match: "revision-N"`) |
| POST | `/api/facility-attachments/{id}/primary` | Promote a `floor_plan` attachment to the facility's primary plan |
| GET | `/api/facility-attachments/{id}/download` | Download an attachment's raw bytes (`download_facility_attachment`, `DownloadFacilityAttachmentController`) |

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
Output DTO additionally carries `equipment: [{equipmentId, name, status, x,
y}]` — every equipment item, scoped to the organization, whose
`Equipment\Domain\ValueObject\PlanPosition` references the same attachment,
resolved cross-module through
`Facility\Application\Port\Outbound\FacilityEquipmentPlanPositionPort`
(implemented by `Equipment\Infrastructure\Adapter\Facility\EquipmentPlanPositionAdapter`,
mirroring `FacilityEquipmentDependencyPort`'s direction — Facility declares
the port, Equipment's Infrastructure supplies the data). See
`src/Equipment/MODULE.md` for the write side
(`PUT .../equipment/{id}/plan-position`) and the
`EquipmentFloorPlanValidationPort` this module implements in the other
direction.

**Read — `GET /organizations/{organizationId}/facilities/{facilityId}/building-model`
(A3).** For a `building` facility, assembles the ordered stack of floors a 3D
viewer extrudes: `{buildingId, buildingName, floors: [{facilityId, name,
levelIndex, status, plan, outline, rooms}]}`. `FacilityNotFoundException`
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
inside another room on the *same floor* (an `area` inside a `zone`) is
dropped, so a 3D view never receives two overlapping volumes. The kept
shape is byte-for-byte `GetFacilityPlanOverlayResult::$zones`
(`facilityId, name, type, status, points`) — the frontend reuses the same
TypeScript models for both endpoints. All of this lives in
`GetFacilityBuildingModelHandler`, over `FacilityRepositoryPort`'s
`findBuildingFloors()`/`findRoomsForFloors()`; no new port was needed.

**Two costs of the plain-array output, both deliberate.** `floors` is a single
array-typed property on `FacilityBuildingModelOutput`, mirroring how
`FacilityPlanOverlayOutput` carries `zones`/`equipment`, rather than a list of
nested DTOs. That choice buys symmetry between the two endpoints and pays for
it twice:

- **The generated OpenAPI describes `floors` as an opaque array of objects.**
  Field names, the `outline.source` enum, and the `plan`/`rooms` sub-shapes are
  absent from the published schema — a codegen client gets no types here. The
  frontend hand-writes its mirrors under `models/` and `/fg-contract-check`
  guards the drift, so the practical cost is low; but this is now the second
  endpoint paying it, and a third would read as settled precedent. Revisit with
  real sub-DTOs if either payload gains a consumer that reads only the schema.
- **Nested nulls are emitted, not omitted.** API Platform drops a null *DTO
  property* — which is why `FacilityOutput.planGeometry` arrives `undefined` on
  a collection read — but that rule does not reach inside an array-typed
  property. `plan: null`, `outline: null` and `levelIndex: null` therefore ship
  explicitly. Preferable for a client that would otherwise have to tell an
  absent key from a null one, and worth knowing before assuming the omission
  convention holds everywhere.

### Metadata schema (organization-defined typed fields)

| Method | Path | Description |
| --- | --- | --- |
| POST | `/api/organizations/{organizationId}/facility-metadata-fields` | Create a typed metadata field definition |
| GET | `/api/organizations/{organizationId}/facility-metadata-fields` | List the organization's metadata field definitions |
| PATCH | `/api/organizations/{organizationId}/facility-metadata-fields/{id}` | Partially update a metadata field definition |
| DELETE | `/api/organizations/{organizationId}/facility-metadata-fields/{id}` | Delete a metadata field definition |

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
  in the audit ledger by Audit's `AuditEventSubscriber`: `facility.created`,
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
  and `knownPendingCodes` (facility-only, dry-run only). When `dryRun`,
  `CreateFacilityCommand` also carries `dryRun`/`quotaProjectionOffset`, and
  `CreateFacilityHandler` takes a second branch: it still builds and
  validates the `Facility` aggregate (so every structural/domain invariant
  still applies) but never enters the transactional save — instead it calls
  `OrganizationQuotaPort::assertProjectedCanAdd()` (no advisory lock; a
  projection against `getLimit()`/`getUsage()` plus the caller's offset, for
  a caller — Import's dry run — walking many candidate rows in one pass with
  nothing persisted yet) and returns a `CreateFacilityResult` built from the
  unsaved aggregate. `FacilityProvisioningService.provision()` additionally
  resolves `parentCode` against `knownPendingCodes` when the database lookup
  finds nothing: a dry run lets a child row reference a parent that would
  itself be created earlier in the same file, mirroring how a real import
  lets the file order parents before children — the resolved
  `parentFacilityId` is left `null` in that case (there is no real id yet),
  and `CreateFacilityHandler`'s parent-existence/status checks are simply
  skipped when no parent id is present. See `src/Import/MODULE.md`'s dry-run
  section for the full row-report shape.

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

| Exception | HTTP status | When |
| --- | --- | --- |
| `FacilityNotFoundException` | 404 | Source facility missing, out of the caller's organization scope, or draft-only; also the target parent when explicitly provided |
| `FacilitySubtreeSourceArchivedException` | 409 | Duplication requested for an archived source facility |
| `FacilitySubtreeTooLargeException` | 422 | Source facility's subtree (including archived nodes) would traverse more than 500 nodes |
| `Organization\Application\Contract\Quota\OrganizationQuotaExceededException` | 409 | The whole clone count would exceed the organization's `facilities` plan quota |
| `FacilityHierarchyException` / `InvalidArgumentException` | 400 | Malformed input, or an invalid/out-of-organization target parent |
| `FacilityAddressNotFoundException` | 404 | Geocode lookup: the provider knows no coordinates for the submitted address (mapped centrally via `api_platform.exception_to_status`, FG-035 — the provider is catch-free) |
| `FacilityAccessDeniedException` | 403 | Caller is in the organization but lacks the required `organization.facilities.*` permission (now also in `api_platform.exception_to_status` for the catch-free geocode path) |

All other domain exceptions raised by this module map the same way as the
other Facility endpoints (see the create/archive/move handlers).

### The canonical facility mutations run on use cases (2026-08-26)

`CanonicalFacilityMutationProcessor` was 444 lines — the largest of the four
canonical processors — holding three `persist`/`flush`/`remove` sites, the
whole hierarchy guard set (cycle walk, depth cap, archived parent), the
restore rule, the archival dependency guard, the changed-field bookkeeping and
the four audit events with their post-commit dispatch. It is now HTTP
translation only, and **holds no entity manager**.

| Concern | Where it lives now |
| --- | --- |
| `PATCH /api/facilities/{id}` | `Application/UseCase/Command/Facility/PatchCanonicalFacility/` |
| `DELETE /api/facilities/{id}` | `Application/UseCase/Command/Facility/DeleteCanonicalFacility/` |
| Read one, for the gate | `Application/UseCase/Query/Facility/GetCanonicalFacility/` |
| Field assignment, trimming, restore rule, revision bump, changed-field set, idempotent archive | `Domain/Model/Facility/CanonicalFacility` |
| Persistence, child count, ancestry walk | `Infrastructure/…/Repository/CanonicalFacilityRepository` (port: `CanonicalFacilityRepositoryPort`) |
| Intervention revision touch | `Facility\Application\Port\Outbound\InterventionScopePort` |

**Two Domain models over one table**, the same split the Inspection and
Equipment modules made on the same day and for the same reason: the
`Facility` aggregate does not carry `record_status`, `intervention_id` or
`revision`, so saving it can never bump the revision the canonical `If-Match`
contract is built on. `src/Inspection/MODULE.md` carries the long-form
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
    identifying `User-Agent` (`FireGuard/1.0 (contact@valentin-fortin.pro)`),
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

## Testing

- Unit: `tests/Unit/Facility`
  - `Application/UseCase/Query/ExportFacilities/ExportFacilitiesHandlerTest` —
    403 without `organization.facilities.read`, 404 outside the organization's
    scope, 422 past `MAX_EXPORT_ROWS`, and the success path resolving a
    child's `parentCode` in one bulk call while an unresolvable parent id
    falls back to `null` rather than an empty string.
  - `Presentation/Api/Controller/ExportFacilitiesControllerTest` — the
    streamed CSV shape (content type, `Content-Disposition: attachment`,
    header row, `parentCode` in a data row), the 400 for a missing
    `organizationId`, the audited filter *names* only, 401 unauthenticated,
    and the bus-wrapped 403/422 domain exceptions unwrapped by
    `FacilityExportExceptionMapperTrait`.
  - `Presentation/Api/Service/FacilityCsvWriterTest` — freezes
    `FacilityCsvWriter::HEADER`'s first seven columns as the
    `Import\Application\Service\FacilityRowFactory` round-trip contract, and
    the plain-decimal coordinate formatting.
  - `Domain/Model/Facility/CanonicalFacilityTest` — the canonical rules with
    no container and no mocks: the changed-field list reporting what DIFFERS
    rather than what the body carried, trimming, explicit-null erasure versus
    absent key, `type`-before-`name` validation order, both coordinate-pairing
    rules, the restore-under-an-archived-parent refusal, the same-parent move
    that reports nothing, the scratchpad that reports nothing at all, and the
    idempotent archive that must not bump the revision.
  - `Application/UseCase/Command/Facility/{Patch,Delete}CanonicalFacility` and
    `Application/UseCase/Query/Facility/GetCanonicalFacility` — the
    orchestration: the hierarchy guards in order (invalid/foreign parent,
    cycle by identity and by ancestry, archived parent, depth cap), the
    restore path resolving a parent the patch never mentioned, the archival
    guard on both the PATCH and DELETE routes, the child-count guard standing
    in FRONT of it on the hard delete, the repeat DELETE that skips the guard
    entirely, the post-commit guarantee, and the revision re-check inside the
    handler's own transaction.
  - `Presentation/Api/Processor/Facility/CanonicalFacilityMutationProcessorTest`
    — what the processor still owns: the gate order (404 before 428), which
    permission a scratchpad row asks the intervention for, 404 rather than 403
    outside the organization, the merge-patch `has*` flags, the parent IRI
    parse, and the single coordinate that must still travel so the handler can
    reject it.
  - `Application/UseCase/Command/Attachment/{Add,Delete,SetPrimary}FacilityAttachment`,
    `Application/UseCase/Query/Attachment/ListFacilityAttachments` — handler
    behavior including storage-write-then-persist rollback on DB failure,
    path-traversal-safe file naming, the atomic primary swap, and the
    document-cannot-be-primary refusal.
- Functional: `tests/Functional/Api/CanonicalFacilityApiTest` — the whole
  `PATCH`/`DELETE /api/facilities/{id}` contract, one HTTP request per test:
  200 + trimmed name + bumped revision, 422 on a null name, a single
  coordinate, a cycle, a foreign parent, an archived parent and a restore
  under one, 409 on archiving with a live child and on hard-deleting a
  scratchpad with children, 204 for the archive / the scratchpad hard-delete /
  the idempotent repeat, 412 on a stale revision, 404 before 428 on an unknown
  id, 404 on a malformed one, 404 for a foreign organization (never 403), and
  403 for a member without write.
- Integration (real database):
  `tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/CanonicalFacilityRepositoryTest`
  — that `findById()` carries the columns the aggregate does not, that
  `save()` writes the mutable ones and **leaves `record_status`,
  `intervention_id` and `client_id` alone**, that it moves the
  `parentFacility` ASSOCIATION (resolved in the repository, not the mapper),
  and that `countChildren()` and `ancestorIdsOf()` answer over the real tree.
  - `Domain/ValueObject/AttachmentKindTest`, `Domain/ValueObject/ImageDimensionsTest`,
    `Domain/Model/Attachment/FacilityAttachmentTest` — the kind↔MIME and
    kind↔primary invariants, and the raster/SVG/no-dimensions probing paths.
  - `Presentation/Api/Processor/Attachment/{FacilityMediaProcessorTest,SetPrimaryFacilityAttachmentProcessorTest}`,
    `Presentation/Api/Provider/Attachment/FacilityMediaProviderTest` —
    permission enforcement, the `If-Match` revision guard on delete, and the
    409/404 mapping on the primary-plan route.
  - `Application/UseCase/Command/Facility/DuplicateFacilitySubtree/DuplicateFacilitySubtreeHandlerTest`
    — happy multi-level clone (codes null, names/type/address/metadata/coordinates
    copied), archived-source 409, archived-descendant skip and reattachment,
    the 500-node cap, the quota check running (and refusing) before any
    `save()`, and the cross-organization 404.
  - `Domain/Model/MetadataField/FacilityMetadataFieldTest` — aggregate
    invariants (key format, select-needs-options, unit length).
  - `Application/Service/FacilityMetadataSchemaGuardTest` — each field type
    validated, unknown keys pass through, `required` enforced on create
    only, `facilityType` scoping.
  - `Application/UseCase/{Command,Query}/MetadataField/**` — the four
    metadata field use cases.
- Integration (real database):
  `tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/{FacilityAttachmentRepositoryTest,FacilityMetadataFieldRepositoryTest}`
  — the attachment repository round-trips the new columns and proves the partial unique index (a second
  `is_primary_plan = true` row for the same facility is rejected at the DB);
  `FacilityRepositoryTest::testFindAncestorsWalksTheParentChainRootFirstAndExcludesDraftAncestors`
  (root facility, 3-level chain, draft ancestor exclusion),
  `Presentation/Api/Provider/Facility/CanonicalFacilityProviderTest` (item route
  `path` mapping, collection left empty);
  `tests/Integration/Facility/Infrastructure/Adapter/Intervention/FacilityInterventionResourceAdapterApplyTest`
  (includes the metadata-schema-rejection case on the offline apply() path).
- Functional: `tests/Functional/Api/FacilityExportApiTest.php` — 200 with CSV
  content type, attachment disposition, the header row, and a seeded child
  facility's row carrying its parent's resolved `code`; 400 on an unknown
  `type` filter value; 401 unauthenticated; 403 for a member without
  `organization.facilities.read`; 404 for a member of another organization
  (deliberately not 403 — that would confirm the organization exists). The
  422 row-cap path is covered by `ExportFacilitiesHandlerTest` instead, since
  `MAX_EXPORT_ROWS` is a class constant and exercising it end-to-end would
  require seeding 50 001 facilities.
- Functional: `tests/Functional/Api/{FacilityAttachmentApiTest,FacilityMetadataFieldApiTest}.php`,
  plus the typed-metadata create cases added to `FacilityApiTest.php` — floor
  plan upload (happy path + wrong-MIME 422), `?kind=` list filter, the
  primary-plan route (happy, swap, document-refusal 409, cross-org 404,
  missing-permission 403), the download route (attachment-disposition +
  nosniff headers on a floor_plan SVG, cross-org 404, missing-permission
  403), and an SVG floor plan carrying `<script>` accepted with dimensions
  probed (sanitization deliberately out of scope — see the security
  constraint above). The `AttachmentConstraints::MAX_SIZE_BYTES` boundary
  (10 MiB + 1 byte rejected before any probing) is covered as a UNIT test —
  `FacilityMediaProcessorTest::testUploadRejectsAFloorPlanJustOverTheMaxSizeBeforeProbing`
  — not a functional one: this environment's php.ini caps
  `upload_max_filesize` at 2M, so a real 10 MiB+1 multipart upload never
  reaches the application (`HttpKernelBrowser::filterFiles()` rejects it
  first).
  E2E `tests/E2E/FacilityCoordinatesFlowTest.php` and
  `tests/E2E/FacilityPresentationFlowTest.php` assert the `path` shape on the
  organization-scoped and canonical detail reads.
  `tests/E2E/FacilityPresentationFlowTest.php` also carries the duplicate
  endpoint's contract coverage against a real database —
  `testDuplicateFacilitySubtree*` (tree shape and null codes, 403,
  cross-organization 404, archived-source 409). The 422 size-cap and
  quota-4xx paths are covered at the handler-unit level only — seeding 500+
  facilities or a capped plan is impractical at this level; that gap is
  noted here rather than silently left uncovered.
- Plan geometry (Phase 4):
  - `Domain/ValueObject/PlanGeometryTest` — point-count and coordinate-bounds
    validation, the UUID check on `attachmentId`, and the `toArray()`/
    `fromArray()` round trip.
  - `Application/UseCase/Command/Facility/SetFacilityPlanGeometry/SetFacilityPlanGeometryHandlerTest`
    — every failure path (unknown facility, unknown attachment, non-ancestor
    attachment, wrong kind, malformed points), the happy set, the clear, and
    an archived facility still accepting a write.
  - `Application/UseCase/Query/Facility/GetFacilityPlanOverlay/GetFacilityPlanOverlayHandlerTest`
    — explicit `attachmentId`, default-to-primary-plan, no-primary-plan 404,
    and the zones list including a descendant's geometry.
  - Integration:
    `tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/FacilityRepositoryTest`
    — the `plan_geometry` JSONB round trip and the `findZonesForPlanAttachment`
    CTE (self, a descendant, and a sibling excluded).
  - Functional: `tests/Functional/Api/FacilityPlanGeometryApiTest.php` — PUT
    happy path (set, then clear), 422 on a malformed points shape, 400 on an
    out-of-bounds coordinate, 404 cross-org and unknown-facility, 403
    missing-permission, 409 wrong-kind and non-ancestor attachment; GET
    overlay happy path including a descendant's zone, default-primary-plan
    behavior, empty zones, 404 cross-org and no-primary-plan, 403
    missing-permission, and (equipment side, Phase 4) an equipment item
    pinned on the same attachment appearing in the `equipment` array.
  - `Application/UseCase/Query/Facility/GetFacilityPlanOverlay/GetFacilityPlanOverlayHandlerTest::testInvokeReturnsEquipmentPinnedOnTheSamePlan`
    — asserts the mocked `FacilityEquipmentPlanPositionPort` result flows
    through to `GetFacilityPlanOverlayResult::$equipment` untouched.
  - `tests/Unit/Facility/Infrastructure/Adapter/Equipment/EquipmentFloorPlanValidationAdapterTest`
    — every failure path (unknown attachment, malformed attachment id, wrong
    kind, non-ancestor) mapped to Equipment's typed exceptions, and the
    self-owned-attachment success path.
  - `tests/Integration/Equipment/Infrastructure/Adapter/Facility/EquipmentPlanPositionAdapterTest`
    (hosted in Equipment, since the adapter is) — the `plan_position` JSONB
    filter, published-only, and organization scoping.
- Hierarchy depth cap: repository integration coverage in
  `tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/FacilityRepositoryTest`
  (`depthOf`/`subtreeHeight` on a seeded chain), handler unit coverage in
  `CreateFacilityHandlerTest`/`MoveFacilityHandlerTest` (create at cap OK/refused,
  move accounting for the moved sub-tree's height), the canonical processor and
  offline adapter unit/integration tests, `FacilityProvisioningServiceTest`
  (`ProvisionOutcome::INVALID`), and one end-to-end functional test,
  `tests/Functional/Api/FacilityHierarchyDepthApiTest.php`, that creates a
  chain up to the cap through the real HTTP API and asserts the next level is
  refused with the mapped 400.
- Run module tests: `make test tests/Unit/Facility/`

## Error Codes

| Domain exception | HTTP status | Consumer sees |
| --- | --- | --- |
| `FacilityHierarchyException::cannotUseSelfAsParent` | 400 | "A facility cannot be its own parent." |
| `FacilityHierarchyException::parentInAnotherOrganization` | 400 | "Parent facility must belong to the same organization." |
| `FacilityHierarchyException::hierarchyCycleDetected` | 400 | "Cannot move facility: hierarchy cycle detected." |
| `FacilityHierarchyException::maxDepthExceeded($cap)` | 400 (Create/Move use cases, via the same `FacilityHierarchyException` catch as the other hierarchy errors); 422 on the canonical PATCH `parent` path (mirrors its existing cycle-check status); `InterventionConflictException` on the offline `apply()` path | "Facility hierarchy depth cap of `$cap` levels exceeded." |
| `FacilityHasActiveDependentsException` | 409 | archival refused while an active child facility, active equipment, an in-progress inspection, or an active intervention exists |
| `FacilityMetadataFieldNotFoundException` | 404 | Unknown id, or a field belonging to another organization (indistinguishable from unknown, see "Scope versus entitlement" above) |
| `FacilityMetadataFieldKeyAlreadyExistsException` | 409 | Duplicate `(organizationId, key)` |
| `FacilityMetadataFieldLimitExceededException` | 422 | Organization already has 50 field definitions |
| `FacilityMetadataValidationException` | 422 | One or more `metadata` entries fail the organization's typed schema; mapped centrally by `FacilityMetadataValidationExceptionSubscriber` regardless of which write path raised it |
| `CanonicalFacilityValidationException` | 422 | The canonical surface's refusals: a non-nullable field sent as null, an unsupported enum value, a half-supplied coordinate pair, an invalid/foreign/archived parent, a cycle, a depth-cap breach, and a restore under an archived parent. **Note the depth case: `FacilityHierarchyException` is 400, but the canonical surface wrapped its MESSAGE in a 422 rather than letting the exception surface — so this class answers 422 for it too.** |
| `CanonicalFacilityConflictException` | 409 | Hard-deleting a draft scratchpad row that still has child facilities |
| `FacilityRevisionMismatchException` | 412 | `If-Match` lost the race between the scope read on the query bus and the mutation's own transaction |
| `FacilityAccessDeniedException` | 403 | Export endpoint: caller is inside the organization's scope but lacks `organization.facilities.read` |
| `FacilityExportTooLargeException` | 422 | Export endpoint: the filters match more than `ExportFacilitiesHandler::MAX_EXPORT_ROWS` (50 000) facilities |
| `FacilityNotBuildingException` | 409 | 3D building model query: the requested facility's `type` is not `building`, mapped centrally in `api_platform.exception_to_status` (mirrors `FacilityAttachmentNotFloorPlanException`'s 409) |

Every other domain exception in this module (facility hierarchy, archival
dependents, code conflicts, …) is mapped locally by its processor/provider,
following the module's existing convention.
