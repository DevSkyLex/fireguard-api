# MaintenanceExport

## Overview

MaintenanceExport owns retained ERP prestation exports and organization-internal external identity mappings. FireGuard retains its fire-park specialization; quotations, selling prices, taxes, invoicing and payments belong to the commercial ERP. Billing continues to own the FireGuard SaaS subscription.

Each generated export preserves schema-version-1 JSON and CSV bytes, SHA-256 fingerprints and the source fact baseline. Downloads always read these saved bytes. Names, references, independent time corrections and later internal costs cannot rewrite an original artifact or the published operational dossier.

## API endpoints

All routes require authentication and use `/api/organizations/{organizationId}`.

| Method | Path | Behavior |
|---|---|---|
| GET / POST | `/maintenance-exports` | Paginate retained exports or generate from 1–100 published intervention IDs. |
| GET | `/maintenance-exports/{id}` | Read retained metadata, version, checksums and import state. |
| GET | `/maintenance-exports/{id}/files/{format}` | Download the exact saved JSON or CSV bytes. |
| POST | `/maintenance-exports/{id}/adjustments` | Append a motivated adjustment to the latest export in a chain. |
| POST | `/maintenance-exports/{id}/confirm` | Record an explicit external import acknowledgement. |
| GET | `/maintenance-export-sources` | Paginate published dossiers, including unavailable historical snapshots. |
| GET | `/maintenance-export-references` | Paginate external references by system, resourceType and resourceId. |
| PATCH | `/maintenance-export-references/{resourceType}/{resourceId}` | Upsert a customer, root site or equipment external reference. |

Collections use Hydra pagination, default 30 and maximum 100; source search is literal and limited to 160 Unicode characters. Export collections filter by explicit ERP `system`. Sources expose `snapshotState`, `identityComplete`, `ready` and `blockedReason` (`snapshot_missing` or `no_validated_work`), with minimal client/site identity and no contacts.

Creation accepts `clientOperationId`, `interventionIds`, `system` and `includeInternalCosts` (false by default). Adjustments accept `clientOperationId` and `reason`; confirmation accepts `clientOperationId` and `externalImportReference`. Existing-resource actions require `If-Match: "revision-N"`. Reference creation uses revision zero; later changes use the returned mapping revision. Reference and import identifiers allow 200 Unicode characters; adjustment reasons allow 1000.

## Flows

Generation locks the scoped published interventions, reads their retained operational dossiers, captures external mappings, renders both formats and inserts the operation receipt in one main transaction. Every selected dossier must contain a validated prestation. A missing or unsupported historical snapshot blocks generation; the module never substitutes live historic names, client/site identity, results or dates. Retained version-one inspection dossiers can expose a validated published inspection reference while their uncaptured actual result/date and asset identity remain explicitly unknown.

An initial financial export additionally requires a private financial snapshot belonging to the same publication. Unknown amounts remain null, with `costsComplete=false` and an explicit incomplete contribution count; they are never replaced with zero. Normal exports omit amount/currency columns and JSON financial facts. Individual target identity, including an explicit absent customer, never inherits another task's primary client. Financial contributions retain their own captured allocation even when their task was skipped or unvalidated; only validated tasks become prestation rows. Older contributions without an allocation retain their task's frozen identity. Direct material consumption uses its own owner-supplied allocation, with an explicit incomplete identity when unavailable.

Rows carry stable source, work-item, publication and source-revision identifiers. JSON preserves exact decimal strings. CSV uses RFC4180 quoting and protects user text from spreadsheet formulas; numeric amounts and minute corrections retain their exact numeric representation. JSON remains the canonical unmodified text representation for ERP integrations.

A genuine correction produces compensating `reverse` rows and `add` rows linked to the preceding exported row through `correctionOf`. The new artifact retains `originalExportId`, `adjustmentOf` and a reason. Unchanged facts create no adjustment. A unique parent relationship prevents divergent adjustment forks; corrections continue from the latest artifact. Initial bytes and the original dossier remain unchanged. Each new adjustment is imported and confirmed independently.

Generation and import confirmation are separate states (`generated` and `import_confirmed`). Confirmation is a manual external receipt, not an automatic assertion that the ERP imported a file. There is no delete or artifact-overwrite endpoint. UUIDs are normalized to lowercase. An actor/organization/operation receipt replays before stale revision checks, returning its original response metadata; reuse for another declaration conflicts without repeating effects.

## Architecture

Command and query handlers are the only application entry points. Domain models enforce preserved artifacts, optimistic confirmation, character limits, bounded rows and compensation semantics. Main PostgreSQL persistence verifies saved checksums and immutable fingerprints, serializes writes with an organization advisory lock and permits only confirmation/revision updates on existing documents. Exact SQL restoration and canonical integrity verification share a pure mapper; the repository owns the connection, transaction, scope and optimistic write policy. Archive collections read a lightweight summary through the repository port: PostgreSQL projects metadata, row count, stored hashes and UTF-8 byte lengths without transferring artifact bytes, rows or correction baselines. The API metadata contract is shared with verified full document reads; individual reads, downloads, adjustments and writes still rehydrate and verify the complete retained artifact.

Public Intervention ports provide bounded immutable publication facts and the independent time journal. MaintenanceCost provides private frozen/current cost facts. Customer, Facility and Equipment host scoped retained-identity validators, combined through `maintenance_export.identity_validator`; the export module never reads sibling records. External mappings belong to this module and do not alter owner identities or client contacts. API Platform providers return saved-file Responses without serializer transformation.

## Configuration

`config/modules/maintenance_export.yaml` registers the main connection, tagged identity composite, public owner-port source adapter, processors/providers, central exception subscriber and bus handlers. Events use the main transactional outbox and carry identifiers only. The additive main migration `Version20261007100000` creates documents, immutable operation receipts and external reference mappings. Artifact text is retained as exact UTF-8; JSONB source data is canonically compared to avoid ordering-induced false corrections. Dates are UTC.

`organization.maintenance_exports.read` authorizes reads. Generation, adjustments and references also require `.manage`; confirmation additionally requires `.confirm`. Manage alone does not grant read. Every read or mutation of a financial export independently requires `organization.maintenance_cost.read`, including replay and download. Collections omit private exports from readers without this financial permission.

Synchronous generation is bounded to 100 selected dossiers, 10000 output rows and 10 MiB per artifact. Owner publication ports impose their own aggregate task, proof/time and encoded-dossier caps. Exceeding a bound requires a narrower explicit selection, with no silent truncation.

## Testing

Domain tests cover canonical Unicode JSON, exact decimal strings, formula-safe CSV with ASCII-only numeric exceptions, stable identifiers, character limits, compensation, unchanged JSONB facts and immutable confirmations. Handler/transport tests prove permission ordering, no-event rejection, original receipt replay, missing-history precedence in source readiness and unchanged download bytes.

PostgreSQL tests use independent sessions for organization locks, operation uniqueness, immutable byte/fact enforcement, rollback and reference CAS. Archive tests compare lightweight and verified metadata, inspect executed SQL result columns and page through 31 retained near-limit artifacts with bounded PHP memory. Functional API tests cover frozen identities, independent time correction, an adjustment chain, preserved originals, explicit import acknowledgement, missing snapshots, private unknown costs, financial denial and organization isolation. Mixed-target CSV/JSON regressions retain expense and time identities and external references for a skipped task through a compensating adjustment. Source adapter regressions also cover null per-task client/site and direct consumption outside the primary dossier target.

Run targeted PHPUnit, PHPStan and scoped formatting before root container, architecture, PostgreSQL schema and OpenAPI gates. Test setup uses the migrated isolated test templates only.

## Error codes

- 400: malformed transport or unsupported serialization.
- 403: active scoped member missing export, confirmation or independent financial read permission.
- 404: organization or retained resource outside the caller's scope, unknown source or owner identity.
- 409: incompatible operation replay, overwritten acknowledgement, immutable artifact mutation, adjustment fork or absent genuine correction.
- 412: stale or malformed revision.
- 422: invalid identity/text/exact source, missing historical publication/financial snapshot, no validated prestation or exceeded explicit source/row/byte bound.
- 428: missing If-Match on an existing-resource mutation or mapping creation.
