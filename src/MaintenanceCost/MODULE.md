# MaintenanceCost

## Overview

MaintenanceCost owns private internal maintenance finance. It records exact six-decimal amounts, planning budgets, append-only external expenses, organization currency and effective hourly rates. Currency is organization-wide and becomes immutable after the first stock or cost fact.

Intervention remains owner of operational work and its independent time journal. Inventory remains owner of physical movements and material valuations. MaintenanceCost consumes their published Application ports and contracts; it never reads their persistence records. Unknown rates or material prices remain explicit incomplete values, never zeroes.

The ordinary intervention dossier, closure snapshot, client reports and PDFs do not contain financial amounts. Dedicated `organization.maintenance_cost.read` and `.manage` permissions protect the finance endpoints. Main database transactions coordinate immutable private snapshots with intervention publication. Current realized costs read later append-only expenses, corrected time and late material facts; those facts never rewrite the published cost snapshot.

Public currency and rate ports support Procurement and Inventory with one exact currency. Commercial quotes, invoicing, taxes and payments remain in the external ERP; Billing retains SaaS subscription responsibility.

## API endpoints

- `GET /api/organizations/{organizationId}/interventions/{id}/costs`: exact current contributions, incomplete flags and the separate frozen snapshot. Source corrections reference their original item; declarations received after publication reference its private publication identity.
- `PATCH .../costs/planning`: partial budget, minutes and estimated resources under `If-Match: "revision-N"`, independently from the operational revision. Preparation closes upon submission. Resource kind determines the calculation: material uses quantity × unit cost, time uses hourly amount × minutes / 60, external uses its explicit amount. Non-null fields belonging to another kind are rejected. Missing calculation inputs leave the amount unknown rather than retaining an earlier computed amount.
- `POST .../costs/expenses`: stable `clientId`, literal decimal amount, actual timezone-qualified date, description and optional work item. A correction appends a signed delta referencing `adjustmentOf`; original expenses are never edited. Replaying the identical actor/payload retains one fact.
- `GET/PATCH /api/organizations/{organizationId}/maintenance-cost/currency`: private settings; a currency change after locking returns 409, identical replay remains accepted.
- `GET/POST /api/organizations/{organizationId}/maintenance-cost/rates`: append-only effective rates by member; stable client UUID and uniqueness per organization/member/date prevent replacement on replay. Each source valuation captures its applied rate and currency.

Publication acquires the Inventory intervention fence before the operational work lock. Final pending-declaration validation and private snapshot capture run on the same `main` transaction as resources, results, ordinary dossier and publication status. A later failure rolls back every capture. Financial values never enter ordinary issue messages or publication reports.

- `GET /api/organizations/{organizationId}/maintenance-cost/reports`: private equipment, root site or internal customer aggregates. Required inclusive UTC calendar `from`/`to` dates span at most 366 days. Optional `siteId`, `customerId` and `equipmentId` filters retain matching targets and expose other allocated amounts in reconciliation. Pages contain at most 100 allocated rows; full-scope totals and the separate unallocated row do not change between pages.
- `GET /api/organizations/{organizationId}/maintenance-cost/dossiers`: a server-paginated minimal finance directory with title/number search, optional paired dates and target filters. Its equipment identities and filters include direct material allocations without operational tasks, from both current and captured financial facts. Financial readers can select minimal site/client/equipment identities and open private costs without ordinary Intervention, Facility, Customer or Equipment read permissions. No amounts, contacts, documents, proof bodies or operational dossier cross this endpoint.

## Flows

`MaintenanceCostInterventionHistoryPort` exposes only the existence of owned
financial references. Intervention and task DELETE checks run under the same
main parent lock as expense and preparation writes. Any expense, financial
preparation or frozen contribution retains its referenced operational work;
rejected deletion preserves detail reads, directory discovery and report totals.

Reporting selects interventions by completed publication date, or planned start/creation for work that remains unpublished. These dates describe the work dossier, not the occurrence date of each time or material fact. The organization date window is bounded to 500 candidate interventions before applying financial target filters, because direct material references need not occur among operational tasks. An oversized candidate window requires narrower dates; target filters cannot lift that cap. At most 50000 combined financial contributions are calculated. No truncated total is returned. The operational finance directory independently remains paginated when a report window is too large.

Each financial contribution is allocated once. Time and targeted expenses follow their work item. Material keeps its direct equipment identity even without a work item. Financial filters first retain matching source dossiers from current/frozen contribution identities and captured operational targets, then separate known different targets from the selected amounts. Missing target scope stays explicitly unallocated rather than being silently treated as a different site or client. Global expenses and budgets remain unallocated within the matching dossiers. All retained amounts reconcile against the selected source dossiers, with excluded targets shown separately; they are not claimed as attributable to a selected asset.

The planned amount sums estimated resources when present; otherwise it uses the global budget as an unallocated baseline. The separate budget column never adds the budget to its resource estimate. Missing estimates or prices remain unknown, with a known subtotal. A variance is produced only when current and planned costs are complete and a planning baseline exists.

The procurement overview is organization-wide within the order-creation date window, independent of equipment/site/client filters. It reports active commitments, gross physical receipts, receivable remainder, motivated returns and pending individualization. Receipts and returns on the selected orders include their full retained history; this basis is explicit in the response. Supplier contacts never enter the overview.

New private publication contributions capture minimal equipment/site/client identity through owning public ports. Corrections to the same source preserve its captured identity. Existing financial JSON without allocation remains readable. Legacy allocations are derived only from an unambiguous captured task map; absent historical identities remain incomplete or unallocated, including a published dossier with no private financial snapshot. Current park identities are allowed only for unpublished work or a proven new fact anchored to the same private publication identity; its later corrections follow that source allocation. Reading an aggregate never replaces a published snapshot.

The directory adds verified financial target identities to the owning operational query before its exact count, search/date filters and pagination. Captured allocations remain selectable after a move or retirement. Candidate material references alone cannot establish a historic site or client. Filtered directory discovery is bounded to 10000 distinct financial candidate interventions and 50000 combined current/frozen contributions; oversized scopes fail explicitly. Unfiltered pages read financial identities only for their at most 100 selected dossiers. These directory bounds are independent of the report's 500-intervention date-window cap.

## Architecture

MaintenanceCostReadPort publishes the existing typed private cost view to trusted application callers such as ERP export. Those callers must authorize organization scope and financial read before invoking it. Report and directory handlers apply the same explicit guard before querying public Intervention, Inventory and Procurement ports. MaintenanceEconomicDirectory discovers only bounded identity candidates through owner-published ports and verifies their private allocations. The module never reads sibling persistence records.

MaintenanceEconomicAggregator sums exact decimal strings and retains null valuations, one allocation destination and a separate reconciliation for filtered targets. Amount arithmetic uses BCMath; large aggregate values remain exact beyond an individual amount's storage precision. MaintenanceCostAllocationResolver enriches current contributions through organization-scoped equipment and operational identity contracts. The publication adapter captures those identities in its owning atomic transaction.

## Configuration

`config/modules/maintenance_cost.yaml` registers the financial query handlers, trusted read adapter, public currency/rate ports and the failure subscriber. Every persistence consumer explicitly uses the main entity manager or connection. Organization financial read/manage permissions are independent of operational, inventory and procurement permissions.

Allocation fields extend existing JSON snapshots additively and do not require a new table or rewrite of stored publications. Ordinary reports and PDFs continue to omit every internal amount.

Additive main migration 20261007150000 widens only frozen `total` and `knownTotal` to NUMERIC(38,6), so summing valid individual eighteen-digit expenses or computed time costs cannot overflow a published aggregate. Individual expense, budget and hourly-rate input validation remains unchanged.

## Testing

Retention coverage records expenses on drafts without time entries and verifies
DELETE refusal, readable task contributions and unchanged exact report totals.
Financial preparation also retains the referenced task and parent.

Unit tests cover single allocation across multiple equipment targets, direct materials, missing historical identity, exact sums beyond eighteen digits, unknown prices, resource/budget separation, current versus frozen costs, compound target filters, full-scope pagination, reconciliation and strict leap/calendar bounds. Handler and provider tests cover finance authorization before source reads and refusal of partial or oversized source windows.

Functional PostgreSQL tests exercise finance-only readers, ordinary operational denial, minimal server search, direct materials without tasks in directory names and target filters, exact filtered pages, captured targets after equipment moves, unknown historic publications, late expenses without snapshot replacement, organization 404, finance 403 and date/group/page 422. Procurement's owning reader separately proves partial receipts, returns, cancellation, unknown unit costs, exact sums, 500/501 limits and organization isolation.

## Error codes

- 403: financial read/manage entitlement missing within an active organization.
- 404: organization or intervention outside the caller's scope.
- 409: source currency mismatch, immutable snapshot conflict, or a source window that would return partial totals.
- 412: stale financial planning revision.
- 422: invalid exact value, report dates, grouping, filters or excessive bounded calculation.
- 428: missing planning revision.
