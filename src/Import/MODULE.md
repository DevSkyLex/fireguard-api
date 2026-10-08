# Import Module

**Reading guide:** [Documentation index](../../docs/README.md) · [Related guide](../../docs/guides/async-processing.md).

<a id="ownership-and-boundaries"></a>

## Overview

Facility dry runs project the same strict hierarchy as actual creation, including
types, parents and stable simulated identifiers. Pending CSV parents are resolved
through this projected graph, rather than accepted by code alone. Resumption
reconstructs confirmed simulated nodes without quota consumption or writes.
Invalid roots, parent types, ancestry and depth fail in simulation; imports never
reorganize the estate implicitly.

Import owns CSV files, jobs, exclusive worker reservations, row receipts, reports
resumption, server CSV templates and confirmation of retained simulations. Equipment, Facility and Organization own creation, quotas, assignment
and invitation rules, exposed through application provisioning ports. All import
state belongs to main. Application handlers enforce authorization.

<a id="public-api"></a>

## API Endpoints

| Method | Path                                                        | Contract                                                                                               |
| ------ | ----------------------------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| POST   | /api/imports                                                | Multipart organization, kind, file, optional dryRun; returns 202 after job and message commit together |
| GET    | /api/imports                                                | Organization-scoped collection, optional kind, server pagination                                       |
| GET    | /api/imports/{id}                                           | Confirmed counters, report, timestamps and server-computed canResume/canConfirm and confirmedJobId     |
| GET    | /api/organizations/{organizationId}/import-templates/{kind} | Authorized JSON-LD filename, CSV content and media type for equipment, facility or member              |
| POST   | /api/imports/{id}/confirm                                   | 202 with the real job; repeated confirmation returns the same job                                      |
| POST   | /api/imports/{id}/resume                                    | 202 for the same retained job; 409 for a live reservation or completed job                             |

Every operation requires ROLE_USER. Equipment/facility imports use their respective
organization.equipment.* and organization.facilities.* permissions. Member imports
require organization.members.manage for writing and organization.members.read for
reading. Workers recheck the initiating/resuming actor's write permission before
claiming. A command whose actor lost access leaves the job untouched; new invitations
use the resuming actor. Outside scope is 404; missing entitlement is 403.

Collections require at least one applicable read permission. The same kind whitelist
filters rows before pagination and the total count. An explicit unauthorized kind is
403; unfiltered collections never expose jobs or counts from forbidden kinds.

<a id="execution-and-recovery"></a>

## Flows

- Creation saves the job and queues ProcessImportJobCommand in one main transaction
  on main_outbox; enqueue failure rolls back the job and removes the new file.
- ImportExecutionPort grants a 120-second lease with a unique owner. An unexpired
  lease excludes other workers. Each row locks the job, checks owner/status/deadline
  and renews the lease. A PostgreSQL row lock prevents takeover during an active row
  transaction, even when the work exceeds the lease TTL.
- Every new row commits creation/assignment, unique (job, row) receipt, report and
  progress together. Redelivery skips confirmed rows. Technical failure rolls back
  the current row and propagates to Messenger, preserving earlier rows. An old owner
  cannot release a newer lease.
- Validation/quota failures are confirmed row outcomes. An unreadable/malformed file
  fails the whole job with a safe report. Completed jobs can contain row failures
  and cannot be replayed.
- Resumption locks the same job and requeues transactionally, preserving its report.
  Duplicate resume requests can queue duplicate commands; leases and receipts prevent
  duplicate local creation. canResume is advisory and is revalidated by the command.
- Facility, imported invitation and completion events are queued in the owning main
  transaction. Invitation delivery starts after commit, checks the current token,
  status and expiry, and has a durable consumption receipt. Delivery retry never
  recreates the invitation or import row.
- Delivery is at least once. External acknowledgement loss may repeat an email.

## CSV and simulation

Comma/semicolon delimiters and UTF-8 BOM are supported. Unknown columns are ignored.
The 5000-data-row limit is counted before provisioning.

Ordinary equipment/facility exports append `_fireguard_text_encoding=apostrophe-v1`
to identify spreadsheet-safe text. Only rows with that exact marker are decoded,
once, before their provisioning factories run; literal apostrophes and formula-like
business text survive reimport. Marked rows use RFC4180 escaping and retain original
cell whitespace until existing factory normalization. Unmarked imports retain their
historical trimming and literal apostrophes, without spreadsheet decoding.

| Kind      | Columns                                                                                      |
| --------- | -------------------------------------------------------------------------------------------- |
| equipment | type (required), subType, brand, model, serialNumber, locationLabel, facilityCode            |
| facility  | type and name (required), code, address, latitude, longitude, parentCode                     |
| member    | email (required), roles (role names separated by a pipe; empty uses the default member role) |

Facility codes resolve within the organization, excluding archived resources.
Equipment creation and initial assignment are atomic. Facility parents precede
children. Coordinates are either both absent or both supplied within valid bounds.
Member invitations apply the ordinary quota, duplicate, membership and role checks.

Dry runs never provision resources. Each validated row has a would_create report;
real jobs report failures only. Equipment/facility simulations include quota
projection and pending parent codes, reconstructed from confirmed rows on resumption.
Member simulation validates email and roles only; it does not promise conflict/quota
availability. Real execution revalidates current quotas and references.

Row codes include quota_exceeded, invalid, missing_required, already_member,
already_invited, unknown_role and would_create. They are report outcomes, not HTTP
errors. Browser observation failure must never be presented as job completion.

## Simulation confirmation

Only a completed, nonempty simulation with every row successful can be confirmed.
The command checks current write access, retains the original file and serializes on
its source job. Creating the real job, linking confirmedJobId and queueing its message
commit together in main. Retries after acknowledgement loss return that same job.
Real execution rechecks permissions, quotas and references; simulation is not a reservation.
Templates require the corresponding write permission and expose headers without sample data.
Retain the source file until both the simulation and confirmed job are beyond recovery retention.

## Deployment and verification

Reports live in `import_row_reports`, keyed by job and file row. The worker loads
only job counters and its current row outcome; it appends the report in the same
transaction as provisioning, counters and `import_row_receipts`. Replaying a
confirmed row neither reruns provisioning nor appends another report. Dry-run
resumption loads only the successful row numbers once to rebuild quota projection.
Main migration `Version20261002031100` preserves legacy JSON report rows and clears
the old job JSON; rollback reconstructs the ordered JSON before dropping the table.
Apply this migration before replacing workers. Retain both row tables with their
jobs for the same recovery period.

`GET /imports/{id}` preserves the `errorReport` array shape and returns at most 100
rows in file order. Use `reportPage` (default 1) and `reportItemsPerPage` (default
100, range 1–100) to retrieve subsequent rows. The additive `reportPage`,
`reportItemsPerPage`, `reportTotal` and `reportHasNextPage` fields describe that
page. Collection items return summaries with an empty report, avoiding reports
being hydrated for every listed job. Scope and permissions apply before reading
report data. Large-report clients must follow these pages to display every row.

`MaintenanceImportBoundedWorkloadTest` measures the real append/receipt and
resumption storage path at 1,000, 5,000 and 10,000 synthetic dry-run outcomes.
The measured append times were 8.057, 41.714 and 85.233 seconds, with exactly
10 statements per row plus two claim statements and no per-row ORM retention.
Resumption used ten statements at every volume. The 10,000-row case deliberately
stresses storage above the accepted CSV limit of 5,000 rows; these measurements
do not measure CSV parsing or resource provisioning. The performance suite
enforces linear query and bounded memory budgets; every sample is recorded in
`var/import-report-benchmark.json`.

Apply additive main lease/receipt and confirmation-link (Version20260921230000) migrations and initialize Messenger's transports.
Drain old workers and reconcile legacy processing jobs before resumption: pre-upgrade
creations beyond the old progress counter have no receipt and cannot retroactively
be guaranteed unique. Retain receipts while jobs/messages/backups can replay. Restrict
queue access because deferred invitations carry accept URLs. See OPERATIONS.md.

PostgreSQL tests cover competing workers, expiry/old-owner fencing, actual creation
rollback, receipt replay, recovery after ORM failure, atomic resumption and deferred
invitation visibility. API tests cover scope, live reservations, retained progress
and capability output. Worker tests cover revoked access, resuming actors, dry-run
projection reconstruction and row outcomes.

## Architecture

Use cases own decisions and inject public provisioning/coordination ports from the business owners. Infrastructure implements persistence/storage/transport; HTTP adapters translate. The ownership and transaction boundaries above remain authoritative.

## Configuration

Bindings are defined in [Import configuration](../../config/modules/import.yaml). Persistence consumers name their entity manager explicitly according to [Doctrine mapping](../../config/packages/doctrine.yaml). Runtime and recovery requirements in the sections above remain part of this contract.

## Testing

Use [Import unit tests](../../tests/Unit/Import) and the endpoint/integration/E2E suites for this capability. Tests cover owned results and denial paths; PostgreSQL checks establish actual transactions, isolation and replay. See [testing procedures](../../docs/guides/testing.md).

## Error Codes

| Code                              | HTTP | When                                              |
| --------------------------------- | ---- | ------------------------------------------------- |
| `import_not_found`                | 404  | The requested job is not found                    |
| `import_permission_required`      | 403  | Required contextual access is missing             |
| `import_confirmation_unavailable` | 409  | Retained simulation cannot currently be confirmed |

Row report codes in the CSV/simulation section are business outcomes, not HTTP failures. API Platform retains its ordinary scope and input-validation errors.
