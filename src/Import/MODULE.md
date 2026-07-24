# Import Module

## Overview

Import lets an organization onboard a real fire-safety estate in bulk instead
of one-by-one entry: upload a CSV → a persisted async `ImportJob` streams the
file row by row and provisions Equipment or Facility resources **through the
existing Create use cases** (`CreateEquipmentHandler` / `CreateFacilityHandler`),
so every domain invariant and the organization's plan quota apply exactly as
they do for the HTTP API — there is no parallel creation path.

Main goals:

- Accept a multipart CSV upload, persist a `pending` job, and enqueue its
  processing on the `async` transport — the request returns `202` immediately.
- Stream the CSV row by row (bounded memory — never load every row into an
  array at once) and provision one resource per row.
- Treat a row failure (structural validation, an unknown reference, or a plan
  quota breach) as **non-fatal**: it is recorded in the job's error report and
  the batch continues. The job still reaches `completed` with a partial
  success — only an unreadable/malformed file reaches `failed`.
- Make redelivery of the same job (Messenger retry after a worker crash)
  safe: an already-terminal job is a no-op, a still-`processing` job resumes
  from its persisted `processedRows` high-water mark.

## API Endpoints

| Method | Path | Description | Permission |
| --- | --- | --- | --- |
| POST | `/api/imports` | Multipart upload (`organization`, `kind`, `file`); `202 {id, status: "pending", ...}` | `organization.equipment.write` (kind=equipment) or `organization.facilities.write` (kind=facility) |
| GET | `/api/imports` | Org-scoped list (filters: `organization` *(required)*, `kind`; 30/page, client page size) | `organization.equipment.read` or `organization.facilities.read` (whichever matches the job's kind; either grants visibility over an unfiltered list) |
| GET | `/api/imports/{id}` | Status + counters + per-row error report | matching read permission for the job's own kind |

Every operation requires `ROLE_USER` at the resource level; the finer-grained
permission checks above are self-enforced in the Application handlers
(`CreateImportJobHandler`, `GetImportJobHandler`, `ListImportJobsHandler`),
consistent with the codebase invariant that handlers — not
processors/providers — own authorization.

## CSV format

- **Equipment** (`kind=equipment`) header: `type` (required, must be a valid
  `EquipmentType` value), `subType`, `brand`, `model`, `serialNumber`,
  `locationLabel`.
- **Facility** (`kind=facility`) header: `type` (required, `FacilityType`),
  `name` (required), `code`, `address`, `latitude`, `longitude`, `parentCode`
  (resolved to a parent facility by exact code within the same organization;
  the parent row must already exist — ordering parents before children in the
  file is the caller's responsibility).
- Unknown columns are ignored. Delimiter is sniffed (comma, or semicolon for
  the French-Excel convention); a UTF-8 BOM is stripped. Max 5000 data rows,
  max 5 MB upload — both bounds exist to keep worker time/memory predictable.
- `type` enum membership is **not** validated by Import's own row factories —
  it is left to `CreateEquipmentHandler`/`CreateFacilityHandler` (via the
  provisioning ports below), which already report an invalid value as a
  non-fatal `INVALID` row outcome. This keeps the row factories free of any
  dependency on Equipment/Facility's Domain types.

## Domain Model

`ImportJob` (`Domain/Model/ImportJob`) — a real aggregate (not a record-level
entity): `id`, `organizationId`, `kind` (`ImportKind`: `equipment`|`facility`),
`status` (`ImportStatus`: `pending`|`processing`|`completed`|`failed`),
`storagePath`, `originalFilename`, `totalRows` (`?int`, set once counted),
`processedRows`/`successfulRows`/`failedRows` (counters), `errorReport`
(`list<ImportRowError>`), `jobError` (`?string`, catastrophic only),
`createdBy`, timestamps. State machine: `pending` -> `processing` ->
(`completed` | `failed`); every transition is guarded on the aggregate.

`ImportRowError` (`Domain/ValueObject`) — one reported row failure:
`rowNumber`, `code` (`quota_exceeded`|`invalid`|`missing_required`),
`message`, optional `column`.

## Flows

### Create (synchronous)

```mermaid
sequenceDiagram
  participant P as CreateImportJobProcessor
  participant H as CreateImportJobHandler
  participant FS as FileStoragePort
  participant Q as ImportJobQueuePort
  P->>P: CsvUploadGuard validates extension/MIME/size (before reading bytes)
  P->>H: CreateImportJobCommand (sync bus)
  H->>H: assertGrantedPermissions (equipment.write or facilities.write)
  H->>FS: write(imports/{orgId}/{jobId}.csv, contents)
  H->>H: ImportJob::create() + repository.save() (status pending)
  H->>Q: dispatch(jobId) -- fire-and-forget, routed async
  H-->>P: CreateImportJobResult
  P-->>P: 202 ImportJobOutput
```

### Process (async worker)

```mermaid
sequenceDiagram
  participant W as ProcessImportJobHandler
  participant R as ImportJobRepositoryPort
  participant FS as FileStoragePort
  participant CSV as CsvRowStreamerPort
  participant EQ as EquipmentProvisioningPort
  participant FAC as FacilityProvisioningPort
  W->>R: claim(jobId) -- raw-DBAL conditional UPDATE
  Note over W,R: pending OR processing -> processing; completed/failed -> false (no-op)
  R-->>W: false => return (already terminal)
  W->>FS: read(storagePath)
  W->>CSV: countDataRows() -> job.setTotalRows() + save()
  loop each data row (skip rowNumber <= processedRows on resume)
    W->>W: kind==equipment ? EquipmentRowFactory : FacilityRowFactory
    alt factory throws ImportRowValidationException
      W->>W: job.recordRowError(code, column, message)
    else
      W->>EQ: provision(request) / W->>FAC: provision(request)
      alt CREATED
        W->>W: job.recordRowSuccess()
      else QUOTA_EXCEEDED / INVALID
        W->>W: job.recordRowError(...)
      end
    end
    W->>R: save(job) every 50 processed rows
  end
  W->>W: job.complete(now) -- even if every row failed (partial success)
  W->>R: save(job)
  W-->>W: dispatch ImportJobCompletedEvent
```

A `Throwable` while reading the blob or parsing the header/row-count marks the
job `failed` (`job.fail(error, now)`) and dispatches `ImportJobFailedEvent` —
swallowed, never rethrown (the job row already records the terminal state;
rethrowing would only trigger pointless Messenger retries).

## Architecture

- **Presentation** (`src/Import/Presentation/Api`): `ImportJobResource`
  (`Post` multipart `deserialize:false`/`input:false`, `GetCollection`,
  `Get`), `CreateImportJobProcessor`, `ImportJobProvider` /
  `ImportJobCollectionProvider`, `CsvUploadGuard` + `UploadedCsv` (the CSV
  equivalent of `Shared\Presentation\Api\Attachment\MultipartAttachmentGuard`
  — the shared attachment kernel's MIME allow-list is images/PDF only, so
  bulk CSV needs its own policy), `ImportJobOutputFactory`,
  `ImportExceptionMapperTrait`.
- **Application** (`src/Import/Application`): use cases (`CreateImportJob`,
  `ProcessImportJob`, `GetImportJob`, `ListImportJobs`), outbound ports
  (`ImportJobRepositoryPort`, `ImportJobQueuePort`, `CsvRowStreamerPort`), and
  the two row factories (`EquipmentRowFactory`, `FacilityRowFactory` — named
  "Factory", not "Mapper": that suffix is reserved for
  `Infrastructure/Persistence/` Doctrine record mappers in this codebase).
- **Domain** (`src/Import/Domain`): `ImportJob` aggregate, value objects
  (`ImportJobId`, `ImportKind`, `ImportStatus`, `ImportRowError`), events
  (`ImportJobCompletedEvent`, `ImportJobFailedEvent`), exceptions
  (`ImportJobNotFoundException`, `ImportRowValidationException`).
- **Infrastructure** (`src/Import/Infrastructure`): Doctrine
  record/mapper/repository, `Csv/CsvRowStreamer` (streams `fgetcsv()` rows
  from a `php://temp` handle — never materializes every row in memory at
  once), `Adapter/Messenger/MessengerImportJobQueueAdapter`.

### Ports & adapters (`config/modules/import.yaml`)

| Port | Adapter |
| --- | --- |
| `ImportJobRepositoryPort` (outbound) | `ImportJobRepository` |
| `ImportJobQueuePort` (outbound) | `MessengerImportJobQueueAdapter` |
| `CsvRowStreamerPort` (outbound) | `CsvRowStreamer` |
| `Equipment\Application\Port\Inbound\EquipmentProvisioningPort` *(cross-module, consumed here)* | `Equipment\Application\Service\EquipmentProvisioningService` |
| `Facility\Application\Port\Inbound\FacilityProvisioningPort` *(cross-module, consumed here)* | `Facility\Application\Service\FacilityProvisioningService` |

**`ImportJobQueuePort` is deliberately NOT `CommandBusPort`**: it mirrors
`Intervention\Application\Port\Outbound\PublicationQueuePort` — a
fire-and-forget dispatch straight onto the raw Symfony `MessageBusInterface`
(routed to `async` by `config/packages/messenger.yaml`). `CommandBusPort`'s
`dispatch()` waits for a `HandledStamp` and throws `NoHandlerResultException`
when a message is merely sent to a transport rather than handled inline,
which is exactly what happens for every async-routed command — so any
fire-and-forget async dispatch in this codebase needs its own dedicated
outbound port, not `CommandBusPort`.

**Cross-module reuse** — the core of "provision through the existing Create
use cases": `Equipment\Application\Port\Inbound\EquipmentProvisioningPort` and
`Facility\Application\Port\Inbound\FacilityProvisioningPort` are new **inbound**
ports hosted in their respective modules (mirroring
`Intervention\Application\Port\Inbound\InterventionDraftFactoryPort`).
`EquipmentProvisioningService`/`FacilityProvisioningService` dispatch the
existing `CreateEquipmentCommand`/`CreateFacilityCommand` through
`CommandBusPort` — the exact same synchronous path the HTTP API uses, so the
transactional quota check (`OrganizationQuotaPort::assertCanAdd()`) runs
intact — and translate every failure (raised directly, or wrapped in
`MessengerRuntimeException`/`HandlerFailedException`) into a typed
`ProvisionOutcome` (`CREATED`|`QUOTA_EXCEEDED`|`INVALID`) rather than
rethrowing, so Import's row loop never depends on — or has to catch — another
module's Domain exception. `FacilityProvisioningService` additionally
resolves an optional `parentCode` to a parent facility id via
`FacilityRepositoryPort::findByOrganizationId(..., code: $parentCode, limit:
1)` before dispatching; an unknown code is reported as a non-fatal `INVALID`
row outcome. `ProvisionOutcome` is intentionally duplicated as a
self-contained enum in each of Equipment's and Facility's own `Contract`
namespaces (rather than shared) so the two sibling modules never depend on
each other.

Import depends **only** on these two inbound ports and their `Contract`
types — never on `CreateEquipmentCommand`/`CreateFacilityCommand` or any
Equipment/Facility Domain type directly.

## Permissions

Reuses `organization.equipment.write` / `organization.facilities.write` (to
create an import job) and `organization.equipment.read` /
`organization.facilities.read` (to read status/list) — no catalog change, no
`app:authz:sync-permissions` run needed.

## Persistence

- Table: `import_jobs` (**main** database), index
  `(organization_id, status)`, index `(created_at)`. `organization_id` is a
  plain column (not an ORM association) with its foreign key
  (`organizations.id`, `ON DELETE CASCADE`) added directly in the migration,
  mirroring `intervention_attachments.work_item_id`'s precedent
  (`Version20260717111309`).
- Doctrine mapping: `src/Import/Infrastructure/Persistence/Doctrine/Record`.
- Repository: `Import\Infrastructure\Persistence\Doctrine\Repository\ImportJobRepository`.
  `claim()` uses a raw-DBAL conditional `UPDATE ... WHERE status IN
  ('pending', 'processing')` (not the ORM's `find()`/`flush()`), mirroring
  `AutomationRunRepository::reserveRun()` — accepting an already-`processing`
  job (not just `pending`) is deliberate: it lets a Messenger redelivery
  after a worker crash resume the same job instead of being rejected, while a
  `completed`/`failed` (terminal) job is never reclaimed.
- Migration: `migrations/main/Version20260717134458.php`.

## Configuration

- Service wiring: `config/modules/import.yaml`
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`
- Messenger routing: `config/packages/messenger.yaml`
  (`ProcessImportJobCommand` → `async`)
- Module import: `config/packages/modules.yaml`
- Autoload: `composer.json` (`Import\\` → `src/Import/`)
- Cross-module wiring (additive): `config/modules/equipment.yaml`,
  `config/modules/facility.yaml`

## Testing

- Unit: `tests/Unit/Import`, plus the cross-module provisioning services at
  `tests/Unit/Equipment/Application/Service/EquipmentProvisioningServiceTest.php`
  and `tests/Unit/Facility/Application/Service/FacilityProvisioningServiceTest.php`.
- Functional: `tests/Functional/Api/ImportJobApiTest.php`.
- Run module tests: `make test tests/Unit/Import/`

## Error Codes

| Exception | HTTP |
| --- | --- |
| `Organization\Domain\Exception\OrganizationAccessDeniedException` | 403 Forbidden |
| `ImportJobNotFoundException` | 404 Not Found |
| `InvalidArgumentException` | 400 Bad Request |
| Upload rejected by `CsvUploadGuard` (missing field, wrong extension/MIME, oversize) | 400 / 422 |

Row-level failures (`quota_exceeded`, `invalid`, `missing_required`) never
surface as HTTP errors — they are recorded in the job's `errorReport` and the
request that created the job already returned `202`.

## Out of scope (documented follow-ons)

- Facility assignment of imported equipment (an `AssignToFacility` step
  driven by a `facilityCode` column).
- Strict exactly-once provisioning via a per-row reservation table
  (`import_job_rows`, unique `(import_job_id, row_number)`) — today's
  crash-resume bound is "at most one in-flight batch of duplicate creates,"
  acceptable for this lot.
- CSV template reference endpoint, blob retention/GC sweep, and
  multi-delimiter/locale autodetection beyond comma/semicolon.
