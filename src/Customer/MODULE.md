# Customer Module

## Overview

Customer owns internal client identities for an organization: contact details and
archival history. It belongs to `main`. An exploitant can manage sites without
customers; a service provider can assign a root site to one internal customer.
No customer creates a user, portal or cross-organization access.

## API Endpoints

All routes require an authenticated organization member. Unknown and foreign
customers return the same 404. An unentitled member receives 403.

| Method | Route | Permission | Behavior |
| --- | --- | --- | --- |
| GET | `/api/organizations/{organizationId}/customers` | `organization.customers.read` | Search and paginate active or archived customers |
| GET | `/api/organizations/{organizationId}/customers/{id}` | `organization.customers.read` | Read retained identity, including archived customers |
| POST | `/api/organizations/{organizationId}/customers` | `organization.customers.manage` | Create (201) |
| PATCH | `/api/organizations/{organizationId}/customers/{id}` | `organization.customers.manage` | Merge patch (200) |
| POST | `/api/organizations/{organizationId}/customers/{id}/archive` | `organization.customers.manage` | Archive without deletion (200) |
| POST | `/api/organizations/{organizationId}/customers/{id}/restore` | `organization.customers.manage` | Restore (200) |

Output: `id`, `organizationId`, `name`, nullable `code`/`email`/`phone`, `contacts`,
nullable `archivedAt`, `createdAt`, `updatedAt`, integer `revision`. Contacts contain
`name` and nullable `email`, `phone`, `role`; they have no independent identity.
Names contain 1–160 characters; codes at most 80, email 254, phone 40, contact role
80. At most 50 contacts are accepted. Optional empty strings normalize to null.
Codes are unique within an organization, including archived clients.

Collection parameters: `search` (name/code/email, at most 160 characters),
`archived=false` (active only) or `true` (archived only), `page=1`,
`itemsPerPage=30` (1–100). Name and id define stable ordering. Items and total share
the same filters. PATCH preserves omitted fields and clears nullable fields sent
as null; names and contacts cannot be null.

PATCH/archive/restore require `If-Match: "revision-N"`. Scope and permission checks
precede revision errors. Concurrent updates cannot overwrite another revision.
Archiving an already archived customer at its current revision does not write again.

## Flows

Writes resolve organization access, validate the domain identity and persist on
the explicitly wired main transaction. Mutations lock and reread the scoped row
before comparing the revision. No endpoint hard-deletes a customer. Significant
mutations enqueue `CustomerChangedEvent` after persistence, in the same main
transaction; archival replay emits no additional event. Events contain identifiers
and revision, without contact details.

Facility stores optional `customerId` on root sites only. New assignments lock and
validate an active customer in the same organization. Existing archived links
remain readable and accept unrelated descriptive edits. Detaching uses null.
This field is unrelated to replay `clientId`.

Intervention closure reads the published site's customer through the public lookup
port and copies the identity into its immutable closure snapshot. Later edits or
archival do not alter that snapshot.

## Architecture

Domain Customer enforces names, contact shapes and lifecycle. Application handlers
own permission decisions, pagination and mutations through ports. Infrastructure
persists PostgreSQL rows on main and exposes `CustomerLookupPort` plus the
Facility-owned validation port. Presentation translates API Platform contracts and
maps customer exceptions centrally to RFC 7807 responses.

## Configuration

Maintenance exports validate retained customer identities through the owner-hosted
`MaintenanceExportCustomerIdentityAdapter`, explicitly wired to main and tagged
`maintenance_export.identity_validator`. Archived customers remain valid historical
references; the lookup returns only a scoped boolean and never loads contacts.

`config/modules/customer.yaml` registers repository, lookup and validation aliases,
bus handlers, presentation and the named `customer.main_transaction_manager`.
Every Doctrine consumer uses `doctrine.orm.main_entity_manager`. Main mapping and
API resource discovery include Customer; migration `Version20261006101000`
creates customers and optional root-site ownership.
`Version20261006101001` adds Doctrine's immutable date metadata to the existing
UTC `TIMESTAMP(0) WITHOUT TIME ZONE` columns, without changing stored date values.

## Testing

Unit tests cover normalization, null/omitted patches, retained archival history,
denial before persistence, locked revision checks and transport translation.
Functional PostgreSQL tests cover CRUD, collection filters, 403/404, 409/412/428,
root-site assignments, unknown/archived refusals and retained archived references.

## Error Codes

| Code | HTTP | Meaning |
| --- | --- | --- |
| `customer_not_found` | 404 | Unknown customer or hidden organization scope |
| `customer_access_denied` | 403 | Missing read/manage permission |
| `customer_invalid` | 422 | Invalid fields, contacts or pagination |
| `customer_code_conflict` | 409 | Organization code already used |
| `customer_revision_stale` | 412 | Revision moved or If-Match is malformed |
| `customer_precondition_required` | 428 | Missing If-Match after access checks |
| `facility_customer_invalid` | 422 | Customer assignment is unavailable or not on a root site |
