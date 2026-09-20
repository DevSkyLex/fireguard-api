# Workload

## Overview

Workload owns effective-dated capacity and daily projections in one organization.
Intervention owns task effort, assignment and the independent time journal.
Missing effort or capacity is unknown, never zero. No national week is assumed.

## API Endpoints

All paths are prefixed with `/api`. Authentication and active organization
membership are required. A member can read their own load; team reads require
`organization.workload.read`, capacity writes `organization.workload.manage`.

| Method | Path | Contract |
| --- | --- | --- |
| GET | `/organizations/{organizationId}/workload` | Daily projection, required `from`/`to`, optional `member`, `team`, `overloaded`, `page` (default 1), `pageSize` (default 10, max 100) |
| POST | `/organizations/{organizationId}/workload/assessments` | Read-only proposed task replacements or draft planning |
| GET/POST | `/organizations/{organizationId}/workload/settings` | Organization effective-dated weekly capacity |
| GET/POST | `/organizations/{organizationId}/workload/members/{memberId}/capacity` | Individual effective-dated weekly replacement |
| GET/POST | `/organizations/{organizationId}/workload/members/{memberId}/exceptions` | Dated availability reductions |
| DELETE | `/organizations/{organizationId}/workload/members/{memberId}/exceptions/{exceptionId}` | Audited cancellation, not physical deletion |

Week inputs use seven integral minute values in ISO weekday order (Monday first),
an effective local date and a stable identifier. No values are assumed. Individual
weeks replace organization weeks; exceptions specify actual daily availability,
not minutes to subtract. Overlapping exceptions and reductions above the effective
capacity are rejected. Changing a week preserves existing versions.

## Flows

Committed and draft demand remain distinct. Terminal tasks and submitted,
published or abandoned interventions have no future demand. Historical time
remains attributed to its contributor and never reduces remaining effort.
Allocation starts today, covers the entire task period, and is proportional to
capacity without capping overload. Past days show actuals; today also includes
remaining demand. Unassigned and unquantifiable work stays explicit.

Team filtering selects distinct members while retaining all their work within the
organization. Projections read the complete contribution set, not UI pages. Responses
distinguish complete, partial and unavailable data and retain explicit null capacity.
Zero capacity with work is unavailable, never a division by zero. Unknown work cannot
produce a confident availability claim. Workload is not payroll or time approval.

Member pagination runs after authorization, complete projection and overload filtering.
Members are ordered by display name with identifier tie-breaking. Responses expose
`totalItems`, the resolved `page` (clamped to the last page) and `pageSize`.
Member options, unassigned work and completeness are independent of the selected page;
neither contribution reads nor planning assessments are paginated.

## Architecture

Pure domain policies and values enforce calendar dates, minutes and capacity.
Modules communicate through published Application ports and contracts only.

- Organization publishes `OrganizationWorkforceDirectoryPort`: active members,
  team membership, labels, scoped picker identities (avatar and organization role names),
  timezone and first day of week. Picker identities retain the workload-authorized member
  scope and never require a broader member-directory permission.
- Intervention publishes `InterventionWorkloadContributionsPort`: task effort and
  independent time contributions. Workload never reads Intervention ORM records.
- Workload publishes `WorkloadProjectionPort`, `WorkloadPlanningPort` and
  `WorkloadCoordinationPort` to Intervention. The writing module owns its transaction.

All demand-changing writes share transaction-scoped PostgreSQL locks: a shared
organization lock and deduplicated member locks in stable order. Organization-wide
capacity changes take the exclusive organization lock. Reassignment locks both members.
Capture, mutation and overload re-evaluation run within that main transaction.
The signed consent token binds the presented daily before/after assessment to
the relevant prior data; stale consent yields an updated conflict, not blind acceptance.
Simulations replace existing task contributions rather than adding duplicates.
Factual time, remaining-effort and availability writes are never refused merely
because they reveal overload.

## Configuration

Persistence uses the main entity manager and new main migrations.
Capacity management is online-only.
Install main migration `Version20260916090509` before deploying the API, then deploy
the web client. Existing estimates and capacity remain unknown. No auth migration
or invented national/weekly schedule is required.

### Development demo data

With the existing development seed organization and main migrations in place:

```sh
php -d memory_limit=1G bin/console app:fixtures:append workload --env=dev --no-interaction
# Docker equivalent:
docker compose exec -T app php -d memory_limit=1G bin/console app:fixtures:append workload --env=dev --no-interaction
```

This opt-in group does not belong to the standard test/seed baseline. It appends
examples for the current and next local workweek to organization
`11111111-1111-4111-8111-111111111111`: seven-hour weekday capacity, one part-time
member, full-day absences and half-days. Intervention owns the accompanying tasks,
draft demand and versioned time entries. Examples cover daily overload, actual
versus remaining effort, unestimated, unassigned, undated and overdue work.

Repeat runs preserve existing intervention trees, edited schedules and cancelled
exceptions. User capacity takes precedence; overlapping availability exceptions
are skipped. Loading another week adds newly dated scenarios without rescheduling
previous ones. These are real demo records, not mocked API responses. Existing
legacy tasks without estimates stay visible as incomplete work. No purge or
production fixture loading is permitted.

## Testing

Domain tests cover allocation, daily overload, unknown and zero capacity,
drafts, terminal work and independent actual time. HTTP and concurrency tests
cover permissions, organization isolation, stale consent, independent published
time and historical-assignee access. Integration tests use two real PostgreSQL
connections to verify member and organization lock contention and release.

## Error Codes

Domain validation uses the shared invalid-value contract (422). Missing organization
or task is 404; insufficient member permissions are 403. Planning overload is 409
with `code=workload_confirmation_required` and a typed assessment. Intervention
operational revisions and time-entry revisions remain separate (412 when stale).
