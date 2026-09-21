# Import Module

## Ownership and boundaries

Import owns CSV files, jobs, exclusive worker reservations, row receipts, reports
resumption, server CSV templates and confirmation of retained simulations. Equipment, Facility and Organization own creation, quotas, assignment
and invitation rules, exposed through application provisioning ports. All import
state belongs to main. Application handlers enforce authorization.

## Public API

| Method | Path | Contract |
| --- | --- | --- |
| POST | /api/imports | Multipart organization, kind, file, optional dryRun; returns 202 after job and message commit together |
| GET | /api/imports | Organization-scoped collection, optional kind, server pagination |
| GET | /api/imports/{id} | Confirmed counters, report, timestamps and server-computed canResume/canConfirm and confirmedJobId |
| GET | /api/organizations/{organizationId}/import-templates/{kind} | Authorized JSON-LD filename, CSV content and media type for equipment, facility or member |
| POST | /api/imports/{id}/confirm | 202 with the real job; repeated confirmation returns the same job |
| POST | /api/imports/{id}/resume | 202 for the same retained job; 409 for a live reservation or completed job |

Every operation requires ROLE_USER. Equipment/facility imports use their respective
organization.equipment.* and organization.facilities.* permissions. Member imports
require organization.members.manage for writing and organization.members.read for
reading. Workers recheck the initiating/resuming actor's write permission before
claiming. A command whose actor lost access leaves the job untouched; new invitations
use the resuming actor. Outside scope is 404; missing entitlement is 403.

Collections require at least one applicable read permission. The same kind whitelist
filters rows before pagination and the total count. An explicit unauthorized kind is
403; unfiltered collections never expose jobs or counts from forbidden kinds.

## Execution and recovery

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

| Kind | Columns |
| --- | --- |
| equipment | type (required), subType, brand, model, serialNumber, locationLabel, facilityCode |
| facility | type and name (required), code, address, latitude, longitude, parentCode |
| member | email (required), roles (role names separated by a pipe; empty uses the default member role) |

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
