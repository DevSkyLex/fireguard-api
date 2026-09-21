# FireGuard reliability and workflows

Implementation branch: `feat/fireguard-reliability-and-workflows` in API and Web.
The original Workload branches were integrated and removed before implementation.
Bases: API `81a9aa35`, Web `cdc51fdc` (the Workload commits on `develop`).

## Delivery gates

Deliver in order, with compatible backend contracts before frontend consumers.
Keep auth/main transactions and migrations separate. Preserve offline identifiers,
preconditions, deduplication keys and existing local changes.

### Lot 0 — API Platform 5

- [x] Upgrade through stable 4.4, resolve application deprecations, then stable 5.x.
- [x] Migrate public query/header metadata and provider parameter consumption.
- [x] Verify resource discovery, security, errors, serialization and OpenAPI compatibility.
- [x] Verify frontend Hydra transport and field validation with existing API regressions.
- [x] Complete automated backend and frontend gates (browser workflows tracked below).

### Lot 1 — Integrity and security

- [x] Async failure transport, transactional outbox and idempotent consumers.
- [x] Exclusive import leases, atomic row receipts, equipment creation/assignment and resumption.
- [x] Serialize approval decisions and expiry with business effects in main transactions.
- [x] Stripe event journal, current-subscription reconciliation and atomic plan assignment; bounded Web confirmation and access/quota refresh.
- [x] Protect the actual webhook connection against private network destinations.
- [x] Versioned TOTP encryption and rotation with additive auth migration; production key provisioning and data migration remain deployment steps.
- [x] Verify identity revocation and organization/tenant denial paths.

### Lot 2 — Business contracts

- [x] Stable error codes and permission-filtered import totals.
- [x] Composite inbox cursor and existing messaging contributions in the frontend.
- [x] Calendar per-source availability and truncation.
- [x] Maintenance/compliance freshness, recalculation and export filter parity.
- [x] Checklist editing capabilities and linked immutable revisions.
- [x] Geometry/position audit and consistent mutation results.
- [x] Measure intervention/workload projections before optimization.
- [x] Membership invalidation and last-organization navigation.
- [x] Cross-module dependency baseline and contract/documentation checks.

### Lot 3 — Workflows

- [x] Organization webhook integration management.
- [x] CSV templates and idempotent confirmation of retained import simulations.
- [x] Withdrawal of pending approval requests.
- [x] Automation execution history and explicit retries.
- [x] Failed-message outbox entry point.
- [x] Assistant cancellation/retry with isolated attempts and deadlines.

## Validation record

### API Platform migration, 2026-09-20

- Upgraded the 13 API Platform components together: 4.2.3 → 4.4.0 → 5.0.0.
  Only API Platform packages changed in the lockfile; PHP, Symfony and ORM constraints
  remain unchanged. Direct requirements are `^5.0`, with stable minimum stability.
- Added the stable `api-platform/test` development package: the Symfony 5 bundle
  registers its client when BrowserKit is installed. Existing WebTestCase tests
  remain unchanged; the test-environment container lint passes with this package.
- OAuth Client and TrustedDevice had colliding explicit operation names. Prefixing
  them by owner restores `GET /api/clients` without changing public URLs. An
  architecture test prevents future collisions.
- Migrated 292 documented query/header parameters in 46 resources. Existing handlers
  keep their validation, defaulting, pagination clamping and unknown-query tolerance.
  Native casting/constraints are explicitly disabled for these historical parameters
  to preserve OAuth errors and HTTP preconditions. New operations must specify native
  constraints and strict query validation. Parameter values are consumed centrally
  without mutating the original request.
- OpenAPI comparison: all 329 paths retained, no removed methods, response statuses,
  content types or parameter names. Intentional differences: owner-prefixed operation
  IDs; restored client list; form-query `explode` default now true (scalars unchanged,
  existing bracketed/repeated arrays preserved); generated pagination defaults/bounds;
  reusable `HydraCollectionBaseSchemaNoPagination`; unambiguous
  `OtpConfig.Config.ChannelOutput.jsonld` schema name. Framework 5 also generates
  additional descriptions and JSON Schema references. The Web consumes transport
  shapes, not generated schema names.
- Web now accepts both JSON-LD errors and plain Problem Details, retaining `code` and
  `violations`. Tests cover the singular ConstraintViolation JSON-LD context.

Passed:

- PostgreSQL backend suite: **12,805 tests, 49,886 assertions**.
- PHPStan: no errors. Deptrac: no violations (existing coverage gaps remain Lot 2).
- Container lint and both PostgreSQL schema validations (`auth`, `main`).
- PHP CS Fixer full dry run: zero changes required.
- OpenAPI freshness gate and 46 post-cleanup regression tests (1,091 assertions).
- Web targeted transport tests: **43 tests / 6 files**.
- Web full suite through `ng test`: **6,341 tests / 613 files**.
- Web lint, formatting, review checks, production browser and SSR builds.

The Web build reports pre-existing missing translations. The unit environment reports
unsupported canvas/navigation operations; its tests pass. Browser interaction and
hydration checks remain to be performed on the modified workflows. No production
deployment or data migration has been performed.

### Billing and atomic equipment provisioning, 2026-09-20

- Canonical Billing tests: **138 tests / 565 assertions**, including independent
  PostgreSQL connections for lock exclusion, event deduplication, rollback and refresh.
  Current remote state wins over old event snapshots; environment/customer/organization
  mismatches are rejected. Checkout, cancel and resume use the reconciliation lock.
- Additive event-journal migration applied to the test `main` database only; main
  schema validation, test container lint and PHPStan passed. The older excluded
  `tests/Billing` tree still has an unrelated payment-method provider expectation failure.
- Equipment creation and initial facility assignment now use one command and one
  transaction. The invalid-assignment path creates no equipment. Targeted provisioning,
  creation, import API and organization-setup tests: **57 tests / 251 assertions**.
- Web billing store/settings tests: **68 tests**. Chromium desktop/light and Mobile
  Chrome/dark Checkout return/retry scenarios pass, including keyboard refresh and quota
  reload. Captures inspected under Web `e2e/artifacts/reliability/`.
- Web lint, authored review gate, browser-test typecheck and production/SSR build pass.
  These browser tests mock the backend and disable SSR; real hydration remains a separate gate.

### Recovery and identity boundaries, 2026-09-20

- Main transactional outbox and auth/main consumer receipts cover publication,
  automation, audit, equipment history and webhook fanout. General async and main
  outbox have failure transports. Independent PostgreSQL connections verify commit
  visibility, rollback and deduplication. Operational cutover and recovery are in OPERATIONS.md.
- Import leases fence stale workers. Per-row receipts and progress commit with local
  business creation; failed technical transactions roll back the current row. Resumption
  retains the original job and report. Imported invitations are delivered after commit.
- Approval decisions expose allowed actions and stable conflict codes. The Web retains
  a decision draft across conflicts and failed refreshes. Import polling interruption
  preserves confirmed progress and offers explicit resumption.
- Web import/approval tests: **136 tests / 17 files**. Desktop and mobile browser
  recovery scenarios passed with inspected screenshots. Lint, review and production
  browser/SSR build passed (existing translation warnings remain).
- Interactive tokens require persisted active sessions; refresh atomically rotates
  the current token pair. Revocation, replay, deactivation, MFA pre-auth rejection,
  missing OAuth records and organization/tenant isolation have targeted coverage.
  The current session comes from the authenticated token, not the PHP session cookie.
- Native login exposed an audit bug: the bearer JWT was passed as a token identifier,
  causing an oversized database write and closing the entity manager. All three
  interactive issuance paths now audit the identifier and persist the session first.
  Subsequent tests: **39 tests / 299 assertions**. No bearer values enter the audit.
- Latest complete PostgreSQL suite before that final audit fix: **12,885 tests /
  50,375 assertions**. PHPStan, Deptrac, test container and both schema checks passed.
  File-upload tests require sandbox access to their configured test storage.
- Existing untracked interactive tokens require a new login at rollout. TOTP enrollment
  continuity is preserved. Production key provisioning/migration and worker cutover
  remain deployment steps; no production data has been changed.

### Contracts, unified inbox and Calendar, 2026-09-20

- Import kind permissions filter the repository rows and totals; the Web offers only readable
  kinds. API regressions: 187 tests / 855 assertions; Web import scope/recovery: 71 tests.
- OTP throttling exposes a stable `rate_limit_exceeded` code and structured delay while retaining
  Retry-After. One real HTTP contract (8 assertions), 33 processor tests and 66 Web tests passed.
- Inbox cursors preserve microseconds, source and id across every contributor. Partial pages
  cannot advance. Messaging mentions enforce current conversation access and refill inaccessible
  batches. API: 205 tests / 892 assertions. Web page, bell, transport and scoped store tests passed;
  desktop/light and mobile/dark browser pagination/read/navigation scenarios passed with captures.
- Calendar returns availability and truncation only for authorized sources, using 501-row reads
  to detect its 500-row limit. Partial iCal responds 503 so subscribers retain their complete feed.
  API: 128 tests / 692 assertions. Web: 86 tests / 9 files; two browser scenarios passed, with
  inspected desktop/light and mobile/dark captures. PHPStan and Web review checks passed.
- The actual SSR harness (separate from browser mocks) passed four desktop/mobile hydration,
  unauthorized login and anonymous onboarding checks. Authenticated tokens are not serialized.

### Maintenance and compliance, 2026-09-20

- Equipment and policy changes enqueue recomputation after transaction commit. Per-equipment
  PostgreSQL locks also cover first creation, overrides and the periodic catch-up sweep.
  Published inspection history restores missed closure updates without regressing dates.
- Nullable evaluation timestamps distinguish legacy/unevaluated data from report generation.
  Compliance summaries and new PDFs expose evaluation coverage and oldest known freshness;
  archived reports remain immutable. List and CSV export share the inclusive dueBefore filter.
- Additive main migration Version20260920193000 applied to the test database only.
  PostgreSQL tests: **375 tests / 1,935 assertions**. PHPStan and main schema validation pass.
- Web targeted tests: **136 tests / 12 files**. Desktop/light and mobile/dark browser scenarios
  verify export parity and summary freshness. A mobile card/pagination overlap found during
  visual inspection was fixed and covered by a bounding-box regression assertion. Both
  browser scenarios, lint, authored review and E2E typechecking pass.

### Checklist capabilities and revisions, 2026-09-20

- Server capabilities separate metadata and item editing and enforce current actor permissions.
  Draft and published inspection references freeze items; inspection assignment and checklist
  mutation share a PostgreSQL lock. Linked creation retains historical checklist/item identities
  and organization-wide reference uniqueness. Main migration Version20260920205000 applied to tests only.
- HTTP testing caught an automatic API Platform read returning 404 before the PATCH processor;
  checklist mutation metadata now explicitly delegates resource reads to the owning use case.
- Inspection API/unit/integration regressions: **974 tests / 3,262 assertions**, including independent
  PostgreSQL lock exclusion and a complete HTTP revision/history journey. Main schema validation passes.
- Web: **88 tests / 12 files**, desktop/light and mobile/dark metadata/revision/retry scenarios pass.
  The editor sends changed fields only, shows version/reference and retains a failed revision draft.
  Review checks and browser-test typechecking pass; localized source extraction succeeds.

### Spatial mutations and cross-lot regression gates, 2026-09-21

- Shared optimistic revisions now protect legacy and canonical Facility/Equipment writes.
  Detail reads and update/assignment/lifecycle/plan mutation responses return the persisted
  snapshot. Publication revalidates plan kind, ancestry and equipment status.
- Transactional spatial facts carry a durable initiating actor into deduplicated auth audit
  writes. Legacy envelopes and the previous settings-event class remain readable. Public
  contracts remove the newly introduced cross-module Domain imports.
- Full PostgreSQL suite: **12,925 tests / 50,790 assertions**, before the subsequent Workload
  performance changes. PHPStan, main/auth schemas and container pass. The full run caught
  an object-identity date comparison; cursor ties now use value comparison resistant to the formatter.
- Maintenance integration now delivers real outbox envelopes and verifies dispatcher wiring.
- Web: **6,390 tests / 616 files**, lint/review and production browser/SSR build pass. Existing
  translation warnings remain. The real SSR harness passes **8 checks** on Chromium/WebKit.
- Desktop/light and mobile/dark conflict, retry and spatial-audit journeys pass with inspected
  captures. Dialogs close only on confirmed success, preserve Signal Forms drafts on refresh,
  and ignore duplicate pending submissions. Expanded mobile audit cards no longer overlap pagination.
- OpenAPI regenerated. All migrations and test fixture writes remain confined to test databases.

### Workload measurements, 2026-09-21

- Reproducible PostgreSQL benchmark: 1,000 interventions, 20,000 work items, 20,000 time
  entries and 500 members. Full contributions, unknown capacities and independent work/time
  revisions remain part of the projection; before/after fingerprints and view hashes match.
- Scalar reads avoid hydrating 40,753 ORM entities. Local median full projection fell from
  3.55 s to 0.83 s; these indicative timings are not a production SLA. Member indexing avoids
  repeatedly scanning the entire organization for each person.
- Workload/Intervention regressions: **241 tests / 1,638 assertions**; benchmark **522 assertions**;
  PHPStan passes. No transport or frontend contract changed.

### Membership freshness and navigation, 2026-09-21

- Authorization no longer trusts shared cached grants or member profiles. One request-local
  invalidator covers member/role writes and organization status/ownership changes. PostgreSQL
  integration verifies warm-cache revocation, reactivation, role removal and suspension.
- Organization regressions: **1,619 tests / 7,435 assertions** plus **12 freshness assertions**.
  PHPStan and the container pass. No schema or HTTP shape changes were required.
- Web: **115 tests / 7 files**, desktop/light and mobile/dark departure journeys pass. A failed
  access refresh retries the read without repeating DELETE; navigation uses server totals.
  The last access opens `/onboarding/workspace`, remaining access `/organizations/select`.
- Membership events clear permission/onboarding caches and selected context. Late guard responses
  and previous-session organization-list responses cannot repopulate cleared state. New copy is
  translated into French and Spanish; lint/review and browser-test typechecking pass.

### Lot 2 architecture and regression gates, 2026-09-21

- Deptrac enforces all 28 module boundaries with exact historical class-pair exceptions.
  New private dependencies fail, including Audit. The independent frontend AST gate checks
  6,591 local imports with no exceptions. Deliberately invalid probes fail both gates.
- Architecture unit checks: **3 tests / 3,473 assertions**. PHPStan, lint and review pass.
- Full PostgreSQL regression: **12,921 tests / 54,134 assertions**. Web: **6,395 tests /
  616 files**. Production browser/SSR build passes with existing translation warnings.

### Approval withdrawal, 2026-09-21

- Active requesters can withdraw under the shared decision/expiry lock. Main stores
  status, actor, time, reason and the durable event atomically. Repeated or late withdrawal
  conflicts; another organization's request stays hidden. Auth audit consumes the public event.
- PostgreSQL/API/unit checks: **354 tests / 2,182 assertions**, including a competing
  decision on an independent connection. PHPStan, container and module boundaries pass.
- Web: **123 tests / 15 files**, lint/review and E2E typecheck pass. Desktop/light and
  mobile/dark confirmation/history journeys pass, with inspected captures and keyboard submission.
- French/Spanish copy and OpenAPI updated. No schema change was required.

### Import templates and simulation confirmation, 2026-09-21

- Three authorized CSV templates and a locked confirmation reuse the retained file.
  Source link, real job and durable queue message commit atomically; repeated confirmation
  returns the same import. Current permissions, quotas and references are revalidated.
- PostgreSQL/unit/API: **222 tests / 1,081 assertions**. Independent connections verify
  exclusive confirmation and message visibility; enqueue failure rolls everything back.
  PHPStan, module boundaries, container and main schema pass. OpenAPI regenerated.
- Web: **75 tests / 9 files**, lint/review and E2E typecheck pass. Two desktop/mobile
  journeys cover download, default simulation, lost confirmation response and final report.
  Captures inspected; stale loading content is covered by a browser assertion. French and
  Spanish copy updated. Main migration `Version20260921230000` applied to tests only.

### Failed messaging sends, 2026-09-21

- Messages exposes a dedicated local-failure list after checking conversation access in
  the selected organization. Account/org changes discard late reads; SSR never reads drafts.
  Canonical account IDs now bind local storage, with legacy subject compatibility.
- Opening a conversation restores durable pending/failed rows after authorized reads.
  Original operation/client IDs and timestamps survive; confirmed messages and server totals
  are preserved. Retry uses the existing PUT precondition and outbox rather than a new send.
- Collaboration regressions: **471 tests / 52 files**. Desktop/light and mobile/dark journeys
  verify inaccessible drafts remain hidden and one retry uses the original client ID.
  Captures inspected. Lint, review, E2E typecheck and French/Spanish copy pass.

### Assistant attempts, 2026-09-21

- Main migrations `20260921233000` and `20260921233100` add attempt identity/number/sequence/deadline and retained
  question/temperature. Cancellation, retry and worker writes share a PostgreSQL row lock.
  Queue insertion uses the owning main transaction. Legacy completed replies remain readable.
- Tested cancellation versus a fragment, duplicate workers, obsolete completion, expiry,
  retry queue rollback, lost retry responses and private thread ownership on PostgreSQL.
  HTTP validation and stable 409 conflict contracts pass. Web: 30 targeted tests and two
  desktop/mobile browser flows with draft preservation and rejection of late frames.
- Deployment: apply the additive main migration before new workers; drain/restart old assistant
  workers before enabling retry, since an old binary cannot check attempt identities. Keep old
  queued commands readable. The queue now uses the main connection for atomic dispatch.

### Automation execution history and retry, 2026-09-21

- Added paginated organization history, effective rule status, scoped attempt reads and explicit
  202 retry with an expected attempt identity. Read/manage permissions are independent.
- Main migration `20260921234000` preserves each attempt while retaining the original unique action.
  Retry and native Doctrine queue insertion commit together. Legacy failures without retained
  payloads remain readable but cannot be retried. Policy is rechecked before queuing and execution.
- PostgreSQL tests cover concurrent retries, stale/lost responses, queue rollback, native transport
  atomicity, repeated delivery, policy changes, failed retries and preserved history. HTTP tests
  verify pagination, strict parameters, permissions, safe errors and the 409 conflict code.
  37 backend tests / 217 assertions; 39 web state/navigation tests and two desktop/mobile flows.
- New `/organizations/:organizationId/automations` UI uses server totals/capabilities and preserves
  previous outcomes. Scope changes discard stale responses; manual refresh follows queued work.

### Webhook integration management, 2026-09-21

- Added `/organizations/:organizationId/integrations/webhooks`, with independent read/manage
  grants, server event selection, partial updates, test delivery, secret rotation, deletion,
  paginated history/status filters and explicit redelivery using the original delivery ID.
- Native Signal Forms/sheet, alert dialog and one-time secret dialog preserve drafts on refusal.
  Secret values never enter entity state or storage; scope changes discard obsolete responses.
- Existing authenticated API lifecycle plus safe-error regression: **205 tests / 772 assertions**.
  Delivery `errorCode` is additive; legacy transport diagnostics are sanitized before exposure.
  No new schema change. New French/Spanish copy and OpenAPI are synchronized.
- Webhook state/form/transport and switcher regressions: **32 tests / 4 files**, plus the
  **33 navigation tests**. Desktop/light and mobile/dark journeys cover keyboard creation,
  validation, draft retention, secrets, filtering/paging, testing, redelivery and read-only access.
  Captures inspected under the web repository's `e2e/artifacts/reliability/`.

## Final integrated validation — 2026-09-21

All implementation checkboxes above are complete on the two local feature branches.

- API: **12,994 tests / 54,654 assertions**, PostgreSQL, four isolated workers. PHPStan,
  both Deptrac configurations, container, YAML, PHP style and both auth/main schemas pass.
  A fresh OpenAPI export equals the checked-in contract. The final architecture run removed
  an unnecessary Assistant legacy mapping; the new conflict remains owned by its subscriber.
- Web: **6,434 tests / 622 files**, lint, feature boundaries and authored-code review pass.
  Formatting passes for all **305 changed authored files**; the E2E TypeScript check passes.
  Production build passes. Existing translation warnings remain outside the changed message IDs;
  the new Webhook messages are translated in French and Spanish.
- **30 reliability browser journeys** pass across desktop Chromium and Mobile Chrome.
  These cover inbox/calendar completeness, import recovery/confirmation, billing confirmation,
  approval conflicts/withdrawal, maintenance freshness, checklist revisions, plan conflicts,
  membership changes, failed sends, Assistant attempts, Automations and Webhooks.
- **10 real-host checks** pass in Chromium/WebKit: raw SSR login HTML, desktop/mobile hydration,
  login submission, anonymous onboarding redirect and authenticated Webhook/Automation startup.
  Organization routes intentionally retain their existing client rendering mode; their private
  collections and credentials are not serialized by the server. The hosted E2E build now uses
  the production runtime-config bootstrap, with bounded local HTTPS fixtures and egress checks.

## Deployment boundary

No production migration, deployment, commit or push was performed. Apply additive auth/main
migrations with their separate configurations, provision the dedicated TOTP key ring, and run
and verify encryption before a later plaintext-column removal. Drain/restart old Assistant and
Automation workers before enabling attempt controls; keep backward-compatible readers during
rollback. Deploy the API contracts before the Web consumer. Operational recovery and metrics
remain documented in `OPERATIONS.md` and the owning module documents.
