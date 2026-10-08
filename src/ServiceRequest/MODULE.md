# ServiceRequest Module

## Overview

ServiceRequest owns internal repair needs and their explicit qualification before
corrective work. It belongs to `main`. Requests can initially name an equipment or
an active root site; site-only requests select an equipment during qualification.
Customers remain internal organization records, without a portal or shared rights.

## API Endpoints

All routes require an authenticated member. An unentitled member receives 403;
an outsider, foreign request or missing request receives the same 404.

| Method | Route | Permission | Result |
| --- | --- | --- | --- |
| GET | `/api/organizations/{organizationId}/service-requests` | `organization.service_requests.read` | Paginated requests |
| GET | `/api/organizations/{organizationId}/service-requests/{id}` | `organization.service_requests.read` | Retained request dossier |
| POST | `/api/organizations/{organizationId}/service-requests` | `organization.service_requests.create` | Requested (201) |
| PATCH | `/api/organizations/{organizationId}/service-requests/{id}` | `organization.service_requests.manage` | Edit requested description (200) |
| POST | `/api/organizations/{organizationId}/service-requests/{id}/qualify` | `organization.service_requests.manage` | Qualified (200) |
| POST | `/api/organizations/{organizationId}/service-requests/{id}/reject` | `organization.service_requests.manage` | Rejected (200) |
| POST | `/api/organizations/{organizationId}/service-requests/{id}/cancel` | `organization.service_requests.manage` | Cancelled (200) |
| POST | `/api/organizations/{organizationId}/service-requests/{id}/convert` | `organization.service_requests.manage` and Intervention planning | Converted (200) |

Create accepts nullable `equipmentId`/`siteId` with at least one target, required
`title` (1–160 characters), `description` (1–10000), `priority`
(`low`, `normal`, `high`, `urgent`; default `normal`), optional
`originInspectionId`/`originNonConformityId`. Equipment ownership and publication,
root-site ancestry and any inspection origin are verified through owner ports.
Equipment derives its root site; an explicit site must match. Published reserve
equipment can be requested without a site. Retired equipment and archived
ancestors cannot receive new repair work.

PATCH accepts only `title`, `description`, `priority`. Qualification accepts
optional `note` (at most 10000) and `equipmentId`; equipment is required when a
site-only request is qualified, must belong to its original site, and cannot
replace an already selected equipment. Reject/cancel require a motivated `reason`
(1–2000). Convert accepts UUID `clientOperationId`, optional
`existingInterventionId`/`existingTaskId`; a task requires its intervention.

Every mutation after create requires `If-Match: "revision-N"`. Missing headers
produce 428, stale or malformed headers 412. A matching conversion receipt accepts
the original positive revision after a lost response, while other writes compare
the current locked revision. Qualification that selects an equipment records both
assignment and qualification, incrementing the revision twice.

All actions return the same output: identifiers, target references, title,
description, priority, status, optional origins, qualification note, decision
reason, linked intervention/task, timestamps for each lifecycle decision,
`revision` and `targetSnapshot`. The snapshot contains only equipment identity
(`id`, nullable `name`/`assetCode`/`status`), site (`id`, `name`) and customer
(`id`, `name`). Customer contacts and other private fields are never returned.

Collection filters are `status`, `equipmentId`, `siteId`, `search` (title and
description, at most 160), `page=1`, `itemsPerPage=30` (1–100). Totals use the same
scope and filters. Requested date descending and id ascending define stable order.

## Flows

Lifecycle is `requested → qualified → converted`. Requested or qualified requests
can be explicitly rejected or cancelled. Terminal requests keep their identity,
target, timestamps and decisions; they cannot be edited or converted again.
Qualification creates no work. Current targets and origin evidence are rechecked
before qualification, and current target availability before initial conversion.
Historical reads and committed conversion replay preserve their original facts.

Conversion locks the request on main, validates its explicit selection and invokes
Intervention's public work port within the same transaction. It creates a corrective
draft with a repair task, or links explicitly selected compatible work. Without a
task selection, an existing corrective intervention reuses its single open repair
for the same equipment, adds one if absent, or refuses an ambiguous selection.
Completed tasks and submitted, published or abandoned interventions cannot acquire
new repair requests. Existing proofs and execution results are preserved.

Intervention requires `organization.interventions.plan`; adding discovered work
outside draft additionally follows its execution permission and participant rules.
Request permissions never grant intervention or customer rights implicitly.

One immutable receipt belongs to a request; `(organizationId, clientOperationId)`
is unique. Its hash contains the request id and canonicalized explicit work
selection. Matching replay returns the original link without additional work,
events or target checks. A changed selection, another key for a converted request,
or a reused key on another request produces 409. Request, work, receipt and
transactional outbox event commit together. A receipt collision rolls back the
second conversion's work. Existing intervention selection is reserved before site
hierarchy, equipment and ancestor row locks, following publication's parent-first
order. The reservation writes no work and is skipped for a committed receipt replay.
Target scope stays consistent while the conversion commits.

## Architecture

Domain owns immutable lifecycle rules, snapshot values and receipts. Application
handlers are the single business entry points for permissions, target validation,
revision checks and transaction coordination. Presentation only translates DTOs,
commands, queries and errors. PostgreSQL persistence uses the explicit main
manager, scoped reads, row locks, compare-and-swap updates and receipt uniqueness.
Creation and restoration group target/origin evidence, content, lifecycle and dates
in typed domain values. Restoration preserves every historical field without
reapplying creation normalization, and isolates retained snapshot JSON.

Equipment implements `ServiceRequestEquipmentTargetPort`; Facility implements
`ServiceRequestSiteTargetPort` using its published hierarchy and Customer's public
lookup. Inspection implements `ServiceRequestOriginPort`. Intervention implements
`ServiceRequestWorkPort` through its canonical workflow and work-item writers.
No ServiceRequest source imports a sibling persistence record or domain class.
`ServiceRequestChangedEvent` carries identifiers, change and revision without
customer contacts or request descriptions.

## Configuration

`config/modules/service_request.yaml` registers main repositories, public target
aliases, guards, the named main transaction manager, bus handlers and API services.
The owning Inspection and Intervention modules register their origin/work bridges.
Main Doctrine mapping and API Platform discovery include ServiceRequest.
Additive migration `Version20261006110000` creates requests and receipts with UTC
`TIMESTAMP(0) WITHOUT TIME ZONE`, immutable date metadata, scope indexes and
target, lifecycle, priority and conversion constraints. It preserves V1 data,
routes, QR identities and offline queues.

## Testing

Domain tests cover lifecycle, immutable targets, text bounds, valid identities,
chronology, complete raw historical restoration and exact receipt identity.
Transport tests preserve omitted versus explicit-null patch fields and parsed
optimistic preconditions. PostgreSQL repository tests exercise scoped
filters, stable pagination, row locking, revisions, uniqueness and atomic rollback.
Handler tests cover denied access before persistence, explicit qualification,
retained site/customer identity, matching and conflicting replay, collisions and
transactional work failure. HTTP PostgreSQL tests verify 403/404/422/409/412/428,
site-only qualification, reserve equipment, draft/foreign/retired/archive refusals,
minimal snapshots and real corrective conversion followed by lost-response replay.
Intervention's bridge tests independently verify task reuse and proof preservation.

## Error Codes

| Code | HTTP | Meaning |
| --- | --- | --- |
| `service_request_not_found` | 404 | Missing request or organization outside scope |
| `service_request_access_denied` | 403 | Member lacks the required request permission |
| `service_request_invalid` | 422 | Invalid fields or target |
| `service_request_origin_unavailable` | 422 | Unknown, foreign, unpublished or mismatched origin proof |
| `service_request_transition_conflict` | 409 | Lifecycle or active-target conflict |
| `service_request_operation_conflict` | 409 | Conversion key or selection collision |
| `service_request_revision_stale` | 412 | Incorrect revision |
| `service_request_precondition_required` | 428 | Missing If-Match |
