# Intervention workflows

Intervention owns operational tasks, publication and the independent time journal. Adjacent modules publish access, inspection, approval and workload capabilities through ports.

**Authoritative references:** [Intervention](../../src/Intervention/MODULE.md) · [Workload](../../src/Workload/MODULE.md) · [Approval](../../src/Approval/MODULE.md).

## Operational revisions

Check access, contextual capabilities and the command's expected revision before
mutation. Publication applies the domain's issue and approval rules. Time entries
have independent stable identities and revisions: correcting time does not implicitly
complete a task, change remaining effort or advance the publication revision.

## Effort, planning and capacity

Effort is integral minutes; missing values are unknown. Workload owns effective-dated
capacity and daily projections. Intervention owns estimates, remaining work, dates
and assignment. Planning reassessment and overload confirmation use the exact presented
command/revision/token contract and recheck it transactionally at execution.

## Offline consumers

Server contracts support replay-safe identities and explicit preconditions. Clients
preserve local intent after a conflict and reread server state before resolution.
An expired/changed confirmation cannot be silently reused. Consult the module's
success, denial, conflict, duplicate and concurrency cases before extending a flow.

## Intervention testing reference

<details>
<summary>Coverage scenarios and regression rationale</summary>

- Unit: `tests/Unit/Intervention/`
  - `Application/UseCase/Command/Attachment/{Add,Delete}InterventionAttachment`,
    `Application/UseCase/Query/Attachment/ListInterventionAttachments` —
    org-isolation via `InterventionResourceGatewayPort`, storage rollback on
    DB failure, path-traversal-safe file naming. `AddInterventionAttachmentHandlerTest`
    additionally covers Phase 5d.1: a `workItemId` belonging to the same
    intervention is accepted and round-trips into the Result, and a
    `workItemId` for which `workItemBelongsToIntervention()` returns `false`
    (another intervention's work item) is rejected with
    `InterventionValidationException` (422) before storage is touched — an
    absent `workItemId` is unaffected (covered by the existing happy-path
    tests, which pass no `workItemId`). `AddInterventionAttachmentHandlerTest`
    additionally covers Phase 5d.2: an unknown `kind` (422), a signature
    uploaded outside `in_progress`/`changes_requested` (409), a PDF-as-signature
    (422), a signature accepted while `changes_requested`, and a re-uploaded
    signature replacing the previous one (the previous record and file are
    deleted only after the new one is saved, and the cap check is not
    inflated by the row about to be replaced).
  - `Application/Service/InterventionIssueFinderTest` — the "capture the
    completion signature" recommendation: present only when `in_progress`,
    every required work item complete, and no signature yet; absent when a
    signature already exists, when required work items remain incomplete, or
    outside `in_progress`.
  - `Domain/Model/Attachment/InterventionAttachmentTest`,
    `Infrastructure/Persistence/Doctrine/Mapper/InterventionAttachmentMapperTest`
    — the `kind` value object defaults to `file` and round-trips through
    `create()`/`reconstitute()`/the Doctrine mapper in both directions; an
    unrecognized persisted `kind` value defaults back to `file` rather than
    throwing.
  - `Presentation/Api/Processor/Attachment/InterventionMediaProcessorTest` —
    the phase-based authorization matrix: `draft` requires
    `organization.interventions.plan`, `in_progress` requires `.execute` (a
    caller holding only `.review` is rejected with 403), and a `published`
    (immutable) intervention rejects the upload with 409.
    `testUploadForwardsTheWorkItemIdMultipartFieldToTheCommand` proves the
    `workItemId` multipart field is parsed (`ResourceIriParser::id(…,
'intervention-work-items')`) and forwarded on the command.
  - `Presentation/Api/Provider/Attachment/InterventionMediaProviderTest` —
    flat `organization.interventions.read` enforcement for reads.
  - `Application/UseCase/Query/Attachment/GetInterventionAttachmentContent/GetInterventionAttachmentContentHandlerTest`
    (Phase 4b) — every port mocked: the stored bytes returned when
    `organization.interventions.read` is granted, download allowed on a
    `published` (otherwise immutable) intervention, `InterventionAttachmentNotFoundException`
    when the record is missing, `InterventionNotFoundException` when the
    owning intervention is gone, `InterventionAccessDeniedException` (and the
    storage port never read) when the permission is missing, and
    `InvalidArgumentException` on a malformed attachment id.
  - `Application/UseCase/Query/Workflow/GetInterventionStatistics/GetInterventionStatisticsHandlerTest`
    — every port mocked: the 403 without `organization.interventions.read`,
    zero-filled status/priority maps and a `null` average from an empty
    aggregate, and name resolution wired through for non-empty top entries.
  - `Presentation/Api/Provider/Statistics/GetInterventionStatisticsProviderTest`
    — 401 unauthenticated, 400 missing `organization`, the handler's access
    exception mapped to 403, and the Result → Output mapping.
  - `Domain/ValueObject/InterventionStatusTest` — `closedValues()` returns
    exactly `['published', 'abandoned']`, the single source of truth
    `GetInterventionStatisticsHandler` and `ListInterventionWorkflowHandler`
    both exclude for "overdue".
  - `Application/UseCase/Workflow/InterventionWorkflowHandlersTest` —
    `ListInterventionWorkflowHandler` translates `filters['due'] === 'overdue'`
    into the gateway's resolved `overdueAsOf` (from `ClockPort::now()`) and
    `overdueExcludedStatuses` (`InterventionStatus::closedValues()`) keys,
    composes with a caller-supplied `dueAtAfter`/`dueAtBefore`, and never
    consults the clock when the `due` filter is absent.
  - `Presentation/Api/Provider/InterventionProviderTest` — the `due` query
    parameter is forwarded verbatim into the query filters, and an unknown
    value (anything but `overdue`) is rejected with 400.
  - `Domain/Service/InterventionMutabilityPolicyTest` — the three field-
    mutability windows, one status at a time.
  - `Application/Service/InterventionActionPolicyTest` — `allowedActions()`
    across every status for the responsible member with full permissions
    (one flag-by-flag expectation per status), permission gating (nothing
    granted denies everything), identity gating (a participant may mutate
    work items/attachments/changes outside draft, an outsider may not, only
    the responsible member may submit/withdraw, and a draft mutation ignores
    identity entirely), and an unrecognized status denying every flag.
    `requiredPermissions()` is covered by the same data set as
    `InterventionWorkflowHandlersTest` (the two must never diverge, since the
    handler calls the exact same method).
  - `Presentation/Api/Factory/InterventionOutputFactoryTest` — `fromView()`
    leaves `allowedActions` `null`; `fromViewForCaller()` populates it (and
    resolves the `responsible`/`participant` IRIs back to raw member ids for
    the identity check first); calling `fromViewForCaller()` on a factory
    built without an `InterventionActionPolicy` throws rather than silently
    omitting the block.
  - `Presentation/Api/Provider/InterventionProviderTest` — both the item and
    the collection read path assert `allowedActions` is present on the
    mapped output.
- Integration (Doctrine adapters against a real database): `tests/Integration/Intervention/`
  — used for `DoctrineInterventionRecurrenceAdapter`'s `DATE_SUB`-based
  lead-time window selection and the `reserveRun()` idempotence guard, both
  hard to trust from a mock. Also covers `DoctrineInterventionWorkflowGatewayAdapter`'s
  `number`, `labelId` (label join) and `memberId` (responsible OR jsonb
  participant lookup) list filters, `DoctrineInterventionReminderAdapter`'s
  status-set/date-window candidate selection and anti-spam stamping, and
  `DoctrineInterventionStatisticsGatewayAdapterTest` — grouped status/priority
  counts scoped to the organization, the overdue/terminal-status exclusion,
  the due-soon 48h boundary (inclusive) against the active-status set,
  top-10 truncation and descending order, and the average-publication-days
  computation (`null` with none published).
  `InterventionAttachmentRepositoryTest` (Phase 5d.1) additionally covers:
  `save()`/`findById()` round-tripping `workItemId`; `findByInterventionId()`'s
  optional `workItemId` filter narrowing to only that work item's attachments;
  and — the deletion-semantics assertion — deleting the referenced
  `intervention_work_items` row directly at the database level sets the
  attachment's `work_item_id` to `null` rather than deleting the attachment,
  proving the `ON DELETE SET NULL` FK. Additionally covers Phase 5d.2: `kind`
  round-trips through `save()`/`findById()` (including the unrecognized-value
  default), and `findSignatureByInterventionId()`/`hasSignature()` return the
  intervention's own signature only (scoped correctly across interventions,
  `null`/`false` when none exists).
- Functional: `tests/Functional/Api/InterventionRecurrenceApiTest.php`,
  `tests/Functional/Api/InterventionTeamAssignmentApiTest.php` — the full
  status table above: 200 on `draft` **and** on `planned` (the guard against
  re-tightening the assignment to draft-only), 403 unentitled, 404 for an
  intervention or a team outside the caller's organization, 409 on
  `submitted`, 412 stale, 422 empty team, 428 without `If-Match`;
  `tests/Functional/Api/InterventionAttachmentApiTest.php`,
  `tests/Functional/Api/InterventionStatisticsApiTest.php` — 200 with the
  full shape (all 7 status keys, all 4 priority keys, `bySite` name
  resolution, `averagePublicationDays`), 400 without `organization`, 403 for
  a member without `organization.interventions.read`, and 404 — deliberately
  NOT 403 — for a caller who is not a member of the requested organization at
  all: the handler resolves both cases through
  `OrganizationAuthorizationPort::resolveAccess()`, and `isOutsideScope()`
  maps to 404 so a non-member cannot confirm the organization even exists.
  `tests/Functional/Api/InterventionExportApiTest.php` — 200 with the CSV
  content type, the `Content-Disposition: attachment` header, the documented
  header row, and one data row per matching intervention; 400 for an unknown
  `status` filter value; 401 unauthenticated; 403 for a member without
  `organization.interventions.read`. The 422 row-cap path (`MAX_EXPORT_ROWS`
  is a class constant, not injectable) is covered instead by
  `tests/Unit/Intervention/Application/UseCase/Query/ExportInterventions/ExportInterventionsHandlerTest.php`,
  which also covers the bulk name resolution (and its raw-id fallback when a
  site/member no longer resolves) and the `due=overdue` filter translation;
  `tests/Unit/Intervention/Presentation/Api/Service/InterventionCsvWriterTest.php`
  covers the CSV formatting itself.
  `tests/Functional/Api/InterventionOverdueFilterApiTest.php` — seeds
  interventions directly through the entity manager (like the statistics
  test, so a terminal status can carry a past due date without the workflow
  forbidding it) and proves `GET /interventions?due=overdue` lists a
  non-terminal past-due intervention while excluding one not yet due and one
  each `published`/`abandoned` despite a past due date, plus composability
  with a caller-supplied `dueAtAfter`. Denial paths are not duplicated here —
  they already exist for `GET /interventions`.
  `InterventionAttachmentApiTest` additionally covers the download route
  (Phase 4b): a real multipart-upload-then-download round-trip proving the
  exact bytes and the RFC-6266-encoded `Content-Disposition` for an accented
  file name, download succeeding on a `published` intervention (no phase
  restriction), 401 unauthenticated, 403 for a same-organization member
  without `organization.interventions.read`, 404 for a caller outside the
  owning organization entirely (the same `resolveAccess()`/`isOutsideScope()`
  pattern as the statistics endpoint — the caller cannot distinguish this
  from an unknown attachment id), 404 for an unknown attachment id, and 404
  when the stored file has gone missing from disk while the DB row survives.
  Phase 5d.1:
  `testUploadWithWorkItemIdRoundTripsIntoTheOutputAndTheFilterNarrowsAndTheWorkItemOutputExposesTheEvidenceCount`
  — a real multipart upload with a `workItemId` field round-trips into
  `InterventionAttachmentOutput.workItemId`, the `workItem` query filter on
  `GET /interventions/{id}/attachments` narrows to that work item's
  attachment only (zero for a sibling work item with none), and
  `GET /intervention-work-items/{id}` exposes the matching `evidenceCount`;
  `testUploadWithAWorkItemIdFromAnotherInterventionIsRejectedWith422` proves
  the cross-intervention denial end to end. Phase 5d.2: a real signature
  upload round-trips `kind: 'signature'` into the output and flips
  `InterventionOutput.hasSignature` to `true`; an upload with no `kind`
  defaults to `file`; an unknown `kind` and a PDF declared as a signature are
  both rejected with 422; a signature uploaded outside
  `in_progress`/`changes_requested` is rejected with 409; and re-uploading a
  signature mints a new attachment id, 404s the previous one, and leaves
  exactly one `signature`-kind attachment in the list.
  - `Infrastructure/Pdf/DompdfInterventionReportRendererTest` (Lot P4.5) —
    mirrors `DompdfSafetyRegisterRendererTest`: implements the renderer port,
    renders the given context through the given template, returns bytes
    starting `%PDF-`, the SSRF hardening (remote resource loading disabled)
    does not turn rendering into an outbound request, and a template failure
    bubbles up rather than being swallowed.
- Functional: `tests/Functional/Api/InterventionReportExportApiTest.php`
  (Lot P4.5) — 200 with `Content-Type: application/pdf`, a
  `Content-Disposition: attachment` header naming
  `intervention-FG-{number}-report.pdf`, and a body starting `%PDF-`; success
  on a `published` intervention (no phase restriction, mirrors the
  attachment-download precedent); 401 unauthenticated; 404 for an unknown
  intervention id; 403 for a same-organization member without
  `organization.interventions.read`; 404 — deliberately not 403 — for a
  caller with no active membership in the owning organization at all, the
  same `OrganizationAuthorizationPort::resolveAccess()` / `isOutsideScope()`
  split every other read path in this module uses.
- E2E: `tests/E2E/InterventionFlowTest.php` covers the withdrawal round-trip —
  submit → work items frozen (409) → withdraw → work items mutable again →
  resubmit (`testWithdrawSubmissionReopensFieldWorkUntilResubmission`). The
  non-responsible 403 on withdrawal is proven at unit level
  (`InterventionMemberPolicyTest`), the gateway wiring being the same
  try/catch as submission. Also covers the `label`, `member` and `number`
  collection filters end to end, including the `number` filter's `FG-`
  prefix and its 400 on a non-numeric value.
- Run module tests: `php -d memory_limit=1G vendor/bin/phpunit tests/Unit/Intervention/`

### Seed fixtures

`Intervention\Infrastructure\DataFixtures\InterventionFixtures` (group
`intervention`, tagged `app.seed_fixture.main`) seeds the whole graph: 5
labels, 3 templates with their planned items, 12 hand-authored interventions
covering **every** `InterventionStatus`, `InterventionType` and
`InterventionPriority`, their work items, proposed/applied/rejected changes,
publication attempts (completed, pending, failed), activity feed, attachments,
3 recurrences with their materialization runs, and the per-organization
number counter. On top of those twelve, `BULK_INTERVENTION_COUNT` (40)
generated interventions — one work item each, no changes/publications/
comments — push the pool past 50 rows so its list, board and calendar views
actually paginate.

Two invariants the seed keeps, because the runtime enforces them and a
contradictory row would be a state no code path can produce:

- every seeded status is reachable from `draft` through
  `InterventionTransitionPolicy`, and the seeded activity feed replays exactly
  that transition chain;
- proposed changes exist only where field work is active (`in_progress`,
  `changes_requested`), per `InterventionChangePolicy` — a published
  intervention carries `applied` ones instead. A `skipped` work item always
  carries its reason.

`InterventionNumberCounterRecord::$lastNumber` is seeded at the highest seeded
number; leaving it behind would make the next runtime creation collide on the
unique `(organization_id, number)`.

Covered by `tests/Integration/Intervention/Infrastructure/DataFixtures/InterventionFixturesIntegrationTest`.

The separate opt-in `workload` demo group appends five intervention scenarios for
the current and next local workweek, with task effort, assignment history and
independent versioned time entries. It uses the existing per-organization number
allocator and preserves whole existing scenario trees on replay. It is loaded
through `app:fixtures:append workload` in dev/test, never the standard purged seed
baseline. Workload owns capacity examples separately; see `Workload/MODULE.md`
for the safe loading command and data-preservation contract.

</details>
