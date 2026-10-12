# Onboarding Module

**Reading guide:** [Documentation index](../../docs/README.md) · [Related guide](../../docs/guides/module-development.md).

## Overview

Onboarding orchestrates cross-module setup flows for authenticated users.
It does not own domain data. It aggregates state from domain modules and
returns actionable steps for frontend clients.

Current flow:

- Organization onboarding

## API Endpoints

| Method | Path                                                   | Description                                                                     |
| ------ | ------------------------------------------------------ | ------------------------------------------------------------------------------- |
| GET    | `/api/onboarding/organization`                         | Get persisted organization onboarding status and actionable steps               |
| POST   | `/api/onboarding/organization/start`                   | Start or reset organization onboarding session                                  |
| POST   | `/api/onboarding/organization/setup-operations`        | Prepare a durable bounded creation batch and return its resumable item receipts |
| POST   | `/api/onboarding/organization/steps/{stepKey}/execute` | Confirm the current onboarding step                                             |
| POST   | `/api/onboarding/organization/steps/{stepKey}/skip`    | Skip an optional onboarding step                                                |
| POST   | `/api/onboarding/organization/rollback`                | Rollback the last rollbackable onboarding step                                  |
| POST   | `/api/onboarding/organization/dismiss`                 | Hide the activation flow without completing it (progression preserved)          |
| POST   | `/api/onboarding/organization/resume`                  | Clear a previous dismissal so the activation flow is visible again              |

The GET initializes or synchronizes the persisted session, so it participates in HTTP
idempotency together with all seven POST operations. Clients allocate one `Idempotency-Key`
per logical request and retain it for retries, including this exact GET path. The deployment
stages the mandatory header as described in [the operating guide](../../OPERATIONS.md).

Each HTTP transition dispatches a typed [owner command](Application/UseCase/Command/Flow/).
The preparation command also returns its final state. Their immutable result contains the
complete [session projection](Application/Service/OrganizationOnboardingSessionState.php),
including setup receipts and registered step descriptors, before the final `main` commit.
Presentation only assembles that result; a committed retry reuses it without synchronizing,
resetting, executing or rolling back the session again. Existing fields, start defaults,
step order and historical open keys remain unchanged. A schema or snapshot failure rolls
back the state change and cannot leave a successful mutation without its receipt.

The [owner manifest](Infrastructure/Adapter/Idempotency/OnboardingHttpIdempotencyOperationContributionAdapter.php)
declares an explicitly nullable original organization identifier: a new flow can have no
workspace yet. When a result includes a workspace, its signed proof binds that identifier.
The [owner replay gate](Infrastructure/Adapter/Idempotency/OnboardingHttpIdempotencyReplayAuthorizationAdapter.php)
rechecks active membership and the common current principal/tenant requirements before
opening the saved state. Removed membership or inconsistent original context fails closed.

## Flows

Organization onboarding is stateful and contains five sequential steps:

1. `create_organization` — user first creates an org via `POST /api/organizations`, then confirms
   the step via `POST /api/onboarding/organization/steps/create_organization/execute` (empty payload).
   **Required.** Rollbackable (archives the organization created by this session).
2. `select_plan` — user reviews the available plans (subscribing to a paid plan happens out-of-band
   through Billing; a new org already defaults to the free plan), then confirms via
   `POST /api/onboarding/organization/steps/select_plan/execute` (empty payload), or skips via
   `POST /api/onboarding/organization/steps/select_plan/skip`. **Optional / skippable.**
3. `invite_members` — user optionally invites members via `POST /api/organizations/{id}/invitations`,
   then confirms via `POST /api/onboarding/organization/steps/invite_members/execute` (empty payload),
   or skips via `POST /api/onboarding/organization/steps/invite_members/skip`.
   **Optional / skippable.**
4. `create_first_facility` — user creates a facility via `POST /api/organizations/{id}/facilities`,
   then confirms via `POST /api/onboarding/organization/steps/create_first_facility/execute`.
   **Required.** Requires at least one facility on the target organization as precondition.
5. `create_first_equipment` — user creates equipment via `POST /api/organizations/{id}/equipment`,
   then confirms via `POST /api/onboarding/organization/steps/create_first_equipment/execute`.
   **Required.** Requires at least one equipment item on the target organization as precondition.
   This is the final step; onboarding completes once it is confirmed.

The persisted session stores:

- global state (`in_progress`, `completed`, `blocked`)
- `nextStep`
- `blockedReason`
- `targetOrganizationId` and `targetOrganizationName`
- `completedSteps`
- `skippedSteps`
- rollback stack metadata (`canRollback`, `lastRollbackableStep`)

Step execution is sequential:

1. `create_organization` can be confirmed only after the org was created via `POST /api/organizations`
2. `select_plan` can be confirmed or skipped only after `create_organization` is completed
3. `invite_members` can be confirmed or skipped only after `select_plan` is completed or skipped
4. `create_first_facility` can be confirmed only after at least one facility exists on the target organization
5. `create_first_equipment` can be confirmed only after at least one equipment item exists on the target organization

Rollback uses LIFO semantics:

- rollback of `create_organization` archives the created organization through its owning module and clears setup receipts; it does not permanently delete domain data
- no destructive rollback is supported for facility or equipment steps in the current version

## Target Organization Pinning

Once a target organization is selected during onboarding, it is pinned in the session.
If the pinned organization is deleted externally, the flow resets to `create_organization`
and does NOT silently switch to another organization the user may belong to.

Only an organization created during the session by the current user, who remains its owner, is adopted as the pinned target. The destructive rollback independently rechecks those ownership and creation-time conditions. A
pre-existing one is never pinned, because `rollback` of `create_organization` deletes
the organization it pinned — adopting a production organization would put it within
reach of that deletion.

## Members Who Arrive Through an Invitation

A member who accepted an invitation already has a workspace, and no organization was
created during their session for the rule above to adopt. Their flow therefore completes
immediately against the organization they belong to: `state` is `completed`, every step
is marked done, and `rollbackStack` is cleared so that organization can never be deleted
by a later rollback.

Without this, such a member is sent to `create_organization` on every request. The
frontend's `onboardingRequiredGuard` holds any non-completed record on the wizard, so
they never reach a single page of the product — the whole seeded staff was locked out
this way.

Only a session that never pinned an organization and has no explicit creation intent qualifies. A pinned organization that
disappeared keeps resetting the flow, as described above.

## Architecture

Flow orchestration consumes Organization's published onboarding directory and archive
capability and a registry of owner-published step contributions. Foreign queries, results,
aggregates and archive commands remain inside their owning adapters. Rollback keeps its
independent creator, owner and creation-time checks before delegating archive.
Completion uses the pure event dispatcher port; notification lookup uses User's
published account directory and the stable integration-event name.

- Presentation: API resource, provider, processors, DTOs
- Domain: onboarding session aggregate + flow state/step value objects
- Infrastructure: Doctrine session record/mapper/repository
- Dependencies:
  - `Organization` module for organization queries
  - `Facility` module for facility existence checks
  - `Equipment` module for equipment existence checks
  - Shared command/query bus and transaction manager

Organization, Facility and Equipment register their own step descriptors and authoritative
precondition checks through `OrganizationOnboardingStepContributionPort`. Its pure static
`definition()` supplies the stable key, label, execution order, required flag and advertised
action. `evaluate()` and `execute()` receive a typed authenticated session and pinned
organization context; the engine controls sequential progression and confirmation history.
The five FireGuard contributions preserve the documented order and API values. A new owner
can add a step without editing the engine or HTTP assembler.

The domain validates key syntax rather than installed contributions. Historical completed,
skipped and audit entries survive a contributor's removal; new execution and skipping of an
unregistered key fail closed. Organization creation retains its independent guarded archive
rollback; contributions cannot bypass creator, owner or creation-time checks.

## Notes

- Onboarding should remain orchestration-only.
- Business writes stay in the owning modules (`Organization`, `Facility`, etc.).

## Workspace resolution and explicit creation

The frontend workspace choice does not call `start`. `POST /api/onboarding/organization/start` accepts optional `intent: "create"` only after the user explicitly chooses creation. This starts a new creation from a completed flow, preserves an unfinished pinned creation, and records `creation_intent` so existing external memberships cannot complete the creator wizard implicitly.

Every state projection includes nullable `accessibleOrganizationId`, independently of the pinned `organizationId`. An active membership elsewhere can therefore open a workspace while an unfinished creator flow remains resumable. A joined organization is never adopted into the creator rollback stack. The five creation steps remain organization, plan, invitations, first facility and first equipment. Auth/User retain address possession and MFA; Organization retains discovery, invitations, requests and admission. Onboarding only resolves and orchestrates these boundaries.

## Durable setup recovery

### Operating profile in organization setup

Prepared `create_organization` inputs include `operatingProfile`, restricted to
`operator` and `service_provider`. Omission normalizes to `operator`, so pre-profile
receipts remain replayable with the explicit default. The chosen profile remains part
of the prepared payload: creation and replay must submit the same normalized profile.
A completed item key cannot be reused to change this choice, and a missing provider
profile on replay returns `onboarding_setup_conflict` rather than silently substituting
operator defaults. A settings change after creation uses Organization's existing
permission-controlled PATCH and does not rewrite the onboarding receipt.

`POST /api/onboarding/organization/setup-operations` prepares `{sessionId, stepKey, items:[{itemKey,payload}]}` and returns the onboarding state. `sessionId` and `setupOperations` are returned by every flow projection; operations carry `stepKey`, `itemKey`, the bounded whitelisted payload, nullable `resourceId`, and `prepared` / `completed` status. Inputs contain no tokens. This authenticated projection is not an SSR transfer payload.

Preparation replaces omitted pending items of the current step, preserves completed results, and refuses changed payloads for existing keys. It permits one organization/equipment and up to five invitations/facilities per session step. Reset starts a different session, invalidating previous receipts. Preparing a batch alone creates no domain resource and consumes no quota.

Owner resource POSTs consume the session/item receipt. The session row is locked in `main`; resource insertion and journal completion commit together. A repeat returns the existing resource without another creation, quota charge, domain event, or invitation email. Equipment placement commits with the creation. Invitations still send after commit; delivery failure retains the existing revoked invitation as the operation result, and requires explicit resend rather than creating a duplicate. Confirmation refuses an unfinished prepared batch and is idempotent after successful execution. Reload exposes partial successes and remaining inputs.

Onboarding only owns the operation journal. Domain invariants, authorization and resource results stay in Organization / Facility / Equipment. Joined organizations cannot be targeted by a creator operation or destructive rollback. Deploy the additive `main` setup journal migration before dependent clients. `OrganizationSetupRepository` is explicitly wired to the main entity manager.

Error: `onboarding_setup_conflict` (409) covers stale/foreign sessions, unavailable steps, missing preparation, reused keys, changed payloads and batch limits. Existing resource authorization denies first, including on replay. The new operation's API metadata is the OpenAPI source.

When a setup journal exists, only its completed `create_organization.resourceId` may pin the creator flow and become its rollback target. A pending item cannot adopt a different creation. If the recorded organization is absent, inactive or no longer owned by the creator, recovery returns `onboarding_setup_conflict` without discarding the receipt; an explicit session reset is required. Date-based legacy adoption is limited to sessions without a journal.

The existing JSON journal column retains a versioned envelope after its items are cleared, so rollback cannot re-enable legacy adoption on the next read. Existing array journals remain readable. This internal marker does not change the API payload or require another migration.

## Configuration

Module selection loads [the service entry point](../../config/modules/onboarding.yaml)
together with its [owner configuration fragments](../../config/modules/onboarding/).
Those fragments contribute owned mappings, API/error metadata and configured
framework/seed registrations. Shared package configuration supplies connections and
manager definitions; this module's persistence remains explicitly wired to `main`.
See the [composition standard](../../ARCHITECTURE.md#service-wiring).

Bindings are defined in [Onboarding configuration](../../config/modules/onboarding.yaml). Persistence consumers name their entity manager explicitly according to [Doctrine mapping](../../config/packages/doctrine.yaml). Runtime and recovery requirements in the sections above remain part of this contract.

The registry receives the `onboarding.organization_step_contribution` tagged iterator.
Owners register the three Organization steps and the Facility/Equipment first-resource steps
in their own module configuration. Composition validates the pure static manifests during
container compilation and rejects duplicate step keys before boot; the registry repeats the
duplicate guard when composed directly in unit tests.

## Testing

Use [Onboarding unit tests](../../tests/Unit/Onboarding) and the endpoint/integration/E2E suites for this capability. Tests cover owned results and denial paths; PostgreSQL checks establish actual transactions, isolation and replay. See [testing procedures](../../docs/guides/testing.md).

## Error Codes

No additional stable module-specific error-code catalog is declared here. The resource security, validation and exception translation define the public HTTP responses; consult this module's endpoint contracts and [OpenAPI schema](../../openapi.json). A future distinct public code must be documented in this section with its triggering condition.
