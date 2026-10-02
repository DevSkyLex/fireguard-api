# Backend testing

Tests use PostgreSQL and isolated clones of the auth/main templates. Select the owning boundary and a focused command before running the complete gate.

**Authoritative references:** [Makefile](../../Makefile) · [Coverage](../../COVERAGE.md) · [Test bootstrap](../../tests/bootstrap.php).

## Database baseline

```sh
make test-db
make phpunit-fast
```

Prepare templates when migrations/fixtures change. `make test-db` creates, migrates
and seeds both test databases. Runs clone the templates; test code cannot assume
it owns the shared fixture baseline. Create needed records and assert deltas or
membership. A development fixture reset is never test preparation.

## Focused checks

```sh
php -d memory_limit=1G vendor/bin/phpunit --filter '<test-name>'
```

Run module paths with PHPUnit's runner, not `make test <path>` (Make treats that
path as another target). Use `make phpunit-parallel` for the complete suite and
avoid overlapping parallel runs with the same worker tokens. The source bootstrap
defines current clone naming and isolation; do not rely on historical timings.

## Boundary evidence

- Unit: domain invariants, handler ports/results, translation and owned error paths.
- Integration: actual PostgreSQL SQL, mapping, locking and receipt/idempotency behavior.
- Functional: routes, serialized contracts, contextual permissions and denial status.
- E2E: multi-operation lifecycles, concurrency, replay and recovery.

Mocks cannot prove a DQL statement, transaction rollback or database constraint.
HTTP tests include anonymous/missing-grant/hidden-scope behavior where applicable.
Endpoint changes require OpenAPI checks; Record changes require both schema checks.

## Complete acceptance

Use `make test` for the composed application gate and `make coverage-check` for
complete local line coverage. CI merges the current run's suite reports; no earlier
artifact proves this revision. See COVERAGE.md for exact scope, 90% threshold and
the distinction between complete and diagnostic runs.

## Bounded mutation and performance checks

After preparing the PostgreSQL test templates, run:

```sh
XDEBUG_MODE=coverage php -d memory_limit=1G vendor/bin/infection \
  --configuration=infection-sensitive.json5 --show-mutations \
  --only-covering-test-cases --no-progress --threads=2
make benchmark
```

The mutation configuration covers refresh-token and Stripe webhook handlers with
four comparison/logical mutators and their focused tests. Its 90% MSI and covered
MSI limits apply only to that configuration. A passing bounded run provides no
repository-wide mutation or coverage percentage. Keep the reports from
`var/infection/sensitive*` with the tested revision; CI's manual mutation input
runs the same configuration against PostgreSQL.

The explicit performance suite checks synthetic volume, exact or bounded query
counts, incremental peak memory and stable outputs. Its test-only query counter
shares state across test-kernel reboots because DAMA retains the database
connection's logger. Each measured phase resets the counter. See the
[performance budgets](../../tests/Performance/README.md) for datasets, limits and
measurement artifacts. These tests create isolated data and roll it back.

## Equipment testing reference

<details>
<summary>Coverage scenarios and regression rationale</summary>

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
  - `tests/Unit/Equipment/Infrastructure/Pdf/DompdfEquipmentLabelSheetRendererTest`
    — the Avery L7159 geometry **measured on the rendered frames** (dompdf
    `end_frame` callbacks): label boxes at the 7.25 / 12.9 mm margins and the
    66 mm × 33.9 mm pitch, every text line inside its own label, the page
    count through the real renderer (24 → 1, 25 → 2), including the second
    sheet restarting at the top margin.
  - Functional: `tests/Functional/Api/EquipmentLabelSheetApiTest.php` — 200
    `%PDF-` for the whole-park, `ids[]` and `facilityId` selections, 400 for
    both modes at once, **422 through the real HTTP surface** (501 ids in the
    query string — cheap, unlike seeding 50 001 rows for the CSV cap), 401,
    403 for a member without `organization.equipment.read`, 404 for a caller
    outside the organization.
- Run module tests: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Equipment/`

</details>

## Facility testing reference

<details>
<summary>Coverage scenarios and regression rationale</summary>

### Seeded floor plans

`make seed-fixtures` produces one building whose plan pipeline is fully
exercisable end to end, because until 2026-08-30 it produced none at all:
`facility_attachments` held zero `floor_plan` rows and no facility had a
`plan_geometry`, so the plan viewer, the outline editor, the equipment pin
layer and the 3D building view were all unverifiable locally without seeding
the database by hand.

`Main Building` now carries:

- **two ordered floors** — `Floor 1` (`levelIndex` 0) and `Floor 2` (1) — each
  with its own primary `floor_plan` attachment, 2400×1600;
- a `plan_geometry` on each floor pointing at **its own** primary plan, which
  is what makes the building model answer `outline.source: plan_geometry`
  rather than falling through to a bounding box;
- an `area` nested inside a `zone` on both floors (`Server Room` in `Zone A`,
  `Storage Room` in `Zone B`), both with outlines — this is what exercises the
  **geometric-leaf rule**: the building model must return the inner `area` and
  drop the enclosing `zone`;
- two equipment items pinned inside `Server Room` via `plan_position`.

The plan images live in `src/Facility/Infrastructure/DataFixtures/assets/` as
hand-written SVGs rather than raster blobs: they are reviewable in a diff, they
weigh nothing, and their `width`/`height` attributes are exactly what
`ImageDimensions` probes. Their bytes are written at the real
`StoragePathScheme` path, because the viewer downloads them — unlike the
document seeds, whose bytes nothing reads and which therefore keep a
placeholder path and no file at all.

**Seed from inside the container when the bytes matter.** `compose.yaml` mounts
`app_var` — a _named volume_ — over `/var/www/html/var`, so the host's `var/`
and the container's are two different filesystems. `make seed-fixtures` runs on
the host, which is fine for rows but writes the plan images somewhere the API
will never look: the download endpoint answers **404** and the plan viewer
spins forever on an image that exists, on the wrong disk. Seed the files where
the app reads them:

```bash
make seed-fixtures-docker
```

The symptom is worth recognizing because it looks like a frontend fault: the
attachment row is right there in the database, its dimensions are right, and
the viewer still shows nothing.

**A fixture that another fixture depends on cannot take a constructor
argument.** Doctrine's `Loader::addFixture()` resolves each declared dependency
through `createFixture()`, a bare `new $class()`, and it does so _before_
checking whether that class is already registered — so the container's
carefully autowired instance is beside the point, and a required argument is a
fatal error. That is why `FacilityFixtures` writes its bytes with plain
filesystem calls against the `STORAGE_DSN` it reads itself, rather than through
`FileStoragePort`. Only `local://` is handled, the only scheme seeding runs
against. Worth knowing before adding a service dependency to any fixture in
this repo.

- Unit: `tests/Unit/Facility`
  - `Application/UseCase/Query/ExportFacilities/ExportFacilitiesHandlerTest` —
    403 without `organization.facilities.read`, 404 outside the organization's
    scope, 422 past `MAX_EXPORT_ROWS`, and the success path resolving a
    child's `parentCode` in one bulk call while an unresolvable parent id
    falls back to `null` rather than an empty string.
  - `Presentation/Api/Controller/ExportFacilitiesControllerTest` — the
    streamed CSV shape (content type, `Content-Disposition: attachment`,
    header row, `parentCode` in a data row), the 400 for a missing
    `organizationId`, the audited filter _names_ only, 401 unauthenticated,
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
- Run module tests: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Facility/`

</details>

## Inspection testing reference

<details>
<summary>Coverage scenarios and regression rationale</summary>

- Unit: `tests/Unit/Inspection/`
  - `Application/UseCase/Command/Attachment/{Add,Delete}InspectionAttachment`,
    `Application/UseCase/Query/Attachment/ListInspectionAttachments` — both
    inspection-level and non-conformity-scoped flows, non-conformity/inspection
    mismatch rejection, storage rollback on DB failure.
  - `Presentation/Api/Processor/Attachment/InspectionMediaProcessorTest`,
    `Presentation/Api/Provider/Attachment/InspectionMediaProviderTest` —
    both upload/list routes, permission enforcement, revision guard on delete.
  - `Domain/Model/Checklist/ChecklistTest` — reference code normalization
    (trim/blank-to-null/length), `update()` partial semantics, archived
    rejection.
  - `Application/UseCase/Command/Checklist/UpdateChecklist/UpdateChecklistHandlerTest`
    — not-found/org-mismatch, archived rejection, `ChecklistInUseException`
    when items change on a checklist already referenced by an inspection,
    item replacement when unreferenced, duplicate reference code mapped
    from `UniqueConstraintViolationException`.
  - `Presentation/Api/Processor/Checklist/UpdateChecklistProcessorTest` —
    PATCH "no field provided" rejection, dispatch + re-fetch round trip,
    conflict mapping for archived/in-use/duplicate-code, messenger unwrap.
  - `Application/UseCase/Query/Checklist/ListChecklists/ListChecklistsHandlerTest`
    (L1.10b) — `itemCount` sourced from `countItemsGroupedByChecklistId()`,
    including the "checklist absent from the grouped-query map" case
    defaulting to `0`; empty result page.
  - `Presentation/Api/Provider/Checklist/ListChecklistsProviderTest` —
    updated for L1.10b: the mocked query bus now returns `ListChecklistResult`
    (not `GetChecklistResult`/`ChecklistItemResult`), pinning that
    `ChecklistOutput->itemCount` is copied straight from `itemCount` and
    `ChecklistOutput->items` stays empty on the list path.
  - `Infrastructure/Adapter/Equipment/EquipmentNonConformityStatisticsAdapterTest`
    (L2.11) — sums the `open` + `in_progress` counts from a mocked
    `NonConformityRepositoryPort` (no new DQL, so no new integration test is
    needed here).
  - `Infrastructure/Adapter/Assistant/InspectionAssistantContextProviderAdapterTest`
    (L2.2) — `supports()` on/off the permission gate, and `provide()`
    degrading to an empty fragment when the repository throws. Deliberately
    does NOT mock the QueryBuilder/DQL — see the integration test below.
  - `Domain/Model/Inspection/CanonicalInspectionTest` — the canonical rules
    with no container and no mocks: the transition table, terminal-state
    immutability, the scratchpad bypass, `result`-before-`status` validation
    order, explicit-null erasure versus absent key, and the idempotent cancel
    that must not bump the revision.
  - `Application/UseCase/Command/Inspection/{Patch,Delete}CanonicalInspection`
    and `Application/UseCase/Query/Inspection/GetCanonicalInspection` — the
    orchestration: which event is dispatched for which transition, the three
    paths that must dispatch **nothing** (scratchpad, no-status-change,
    idempotent repeat DELETE), the post-commit guarantee (a rolled-back
    transaction audits nothing), and the revision re-check inside the
    handler's own transaction.
  - `Presentation/Api/Processor/Inspection/CanonicalInspectionMutationProcessorTest`
    — what the processor still owns: the gate order (404 before 428), which
    permission a scratchpad row asks the intervention for, 404 rather than 403
    outside the organization, and the merge-patch `has*` flags.
  - `Application/UseCase/Query/Inspection/{ReadCanonicalInspection,ListCanonicalInspections,ResolveCanonicalInspectionScope}`
    — the `recordStatus` default, the equipment filter, the one-based page
    turned into an offset, the empty page that asks for no counts at all, and
    the non-conformity counts coming from ONE grouped call with a row absent
    from the map meaning zero rather than a missing key.
  - `Application/UseCase/Query/Response/{ListInspectionResponses,ResolveInspectionResponseScope}`
    — the `recordStatus` default (published by default, drafts when scoped to
    an intervention, explicit always wins), the one-based page turned into an
    offset, the empty page that still carries its total, and the precedence
    between the three scoping filters including the inspection FALLBACK that
    only runs when the first two produced nothing.
  - `Presentation/Api/Provider/InspectionResponse/InspectionResponseProviderTest`
    — what the provider still owns: the view-to-output projection, the gate's
    404-not-403 split, the IRI filters it parses, and the pagination clamp.
  - `Application/UseCase/Command/Response/{Create,Update,Delete}InspectionResponse`
    and `Application/UseCase/Query/Response/GetInspectionResponse` — the
    lifecycle rules that used to sit in the processor: draft-only edit and
    delete, the revision re-check, the replay guard firing **before** any scope
    is read, the three scope conflicts, `touchDraft()` called with the right
    intervention (and never on a rejected path), and a malformed identifier
    resolving to "not found" rather than "invalid".
  - `Presentation/Api/Processor/InspectionResponse/InspectionResponseProcessorTest`
    — what the processor still owns: 412 vs 409 for a duplicate `clientId`
    (with the failure delivered double-wrapped, as the real bus delivers it),
    404 before the revision guard, and the stored revision travelling into the
    command rather than the header's value.
- Functional: `tests/Functional/Api/CanonicalInspectionApiTest` — the whole
  `PATCH`/`DELETE /api/inspections/{id}` contract, one HTTP request per test:
  200 + bumped revision on a legal transition, 422 on an illegal one and on a
  null non-nullable field, 409 on both terminal-state paths, 204 for the
  cancel / the scratchpad hard-delete / the idempotent repeat, 412 on a stale
  revision, 404 before 428 on an unknown id, 404 on a malformed one, 404 for a
  foreign organization (never 403), and 403 for a member without write.
- Functional: `tests/Functional/Api/InspectionResponseApiTest` — the whole
  `/inspection-responses` contract, one HTTP request per test: 201 on create,
  409/412 on a known `clientId`, 200 + bumped revision on PATCH, 204 on DELETE,
  409 on both published paths, 412 on a stale revision, 404 before 428 on an
  unknown id, 404 for a foreign organization (never 403), 403 for a member
  without write, and **400 for a malformed PUT identifier** — the one status
  this refactor moved, asserted rather than assumed.
- Integration (real database):
  `tests/Integration/Inspection/Infrastructure/Persistence/Doctrine/Repository/CanonicalInspectionRepositoryTest`
  — that `findById()` carries the three columns the aggregate does not, that
  `save()` writes the six mutable ones and **leaves `record_status` and
  `intervention_id` alone** (a PATCH that silently published a scratchpad row
  would be invisible in the response and permanent in the table), and that
  `save()` on an absent row inserts nothing.
  `tests/Integration/Inspection/Infrastructure/Persistence/Doctrine/Repository/InspectionResponseRepositoryTest`
  — save/find/delete round trip, `save()` updating in place on a replayed id,
  `existsByClientId()`, and `InspectionRepository::findScope()`, whose scalar
  projection over `IDENTITY(i.organization)` plus the canonical
  `intervention_id` column nothing but a real query can prove.
  `tests/Integration/Inspection/Infrastructure/Adapter/Assistant/InspectionAssistantContextProviderAdapterTest`
  (L2.2) — executes the adapter's DQL for real: severity ordering
  (critical → high → low), resolved (`done`)/foreign-organization rows
  excluded, and the symmetric case (the foreign-org row IS reported when
  queried from ITS OWN organization) — pinning that organization-scoping
  comes from the query's join, never from trusting the caller.
  `tests/Integration/Inspection/Infrastructure/Persistence/Doctrine/Repository/InspectionAttachmentRepositoryTest`
  (round-trip of both discriminator states, `findByInspectionId` excludes
  non-conformity photos).
  `tests/Integration/Inspection/Infrastructure/Persistence/Doctrine/Repository/ChecklistRepositoryIntegrationTest`
  (L1.10b) — executes `countItemsGroupedByChecklistId()` for real: an
  in-organization checklist with items is counted, a zero-item checklist is
  absent from the map, and a foreign-organization checklist never
  contributes even when its ID is explicitly included in the requested list
  (organization-scoping is enforced by the join, not by trusting the
  caller's ID list). Also pins that `findByOrganizationId()` returns
  checklists with an empty `items()` (no per-row hydration).
  `Application/UseCase/Query/NonConformity/ListOrganizationNonConformities/ListOrganizationNonConformitiesHandlerTest`
  (B7) — org-scoped filters/pagination/sorting passed through, equipment
  batching via `findEquipmentIdsByIds()` + `EquipmentNamingPort`, unresolved
  equipment/serial degrading to `null`, empty page.
  `Presentation/Api/Provider/NonConformity/ListOrganizationNonConformitiesProviderTest`
  (B7) — authentication/permission gating, filter passthrough, pagination
  envelope.
- Functional: `tests/Functional/Api/InspectionAttachmentApiTest.php`,
  `tests/Functional/Api/InspectionApiTest.php` (checklist endpoints, including
  `PATCH .../checklists/{id}`, and B7's
  `GET /organizations/{organizationId}/non-conformities`).
  The five non-conformity endpoints carry the full contract matrix there:
  201/200 success shapes, 403 for a member missing
  `organization.inspection.{read,write}`, 404 for a cross-organization
  inspection or a non-conformity addressed under an inspection that does not
  own it, 409 for reopening a resolved row and for adding to a closed
  inspection, and the closed-inspection asymmetry above. The gated waiver runs
  against the REAL `ApprovalGate` (no mock): an organization seeded with
  `settings.approval.action_rules.nc_waiver.enabled = true` at the `critical`
  threshold yields the live **202** body
  (`{status: 'pending_approval', approvalRequestId, approvalStatus, expiresAt}`)
  with the row left `open`; a `low` finding below that threshold applies
  immediately (**200**); a repeated ask returns the request already pending
  instead of opening a duplicate; and a caller with
  `organization.inspection.write` but without `organization.approvals.request`
  gets **403**.
- Run module tests: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Inspection/`

</details>
