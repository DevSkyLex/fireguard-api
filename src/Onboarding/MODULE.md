# Onboarding Module

## Overview

Onboarding orchestrates cross-module setup flows for authenticated users.
It does not own domain data. It aggregates state from domain modules and
returns actionable steps for frontend clients.

Current flow:

- Organization onboarding

## API Endpoints

| Method | Path | Description |
| --- | --- | --- |
| GET | `/api/onboarding/organization` | Get persisted organization onboarding status and actionable steps |
| POST | `/api/onboarding/organization/start` | Start or reset organization onboarding session |
| POST | `/api/onboarding/organization/steps/{stepKey}/execute` | Confirm the current onboarding step |
| POST | `/api/onboarding/organization/steps/{stepKey}/skip` | Skip an optional onboarding step |
| POST | `/api/onboarding/organization/rollback` | Rollback the last rollbackable onboarding step |

## Flow Behavior

Organization onboarding is stateful and contains five sequential steps:

1. `create_organization` — user first creates an org via `POST /api/organizations`, then confirms
   the step via `POST /api/onboarding/organization/steps/create_organization/execute` (empty payload).
   **Required.** Rollbackable (deletes the created organization).
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

- rollback of `create_organization` deletes the created organization (and all cascaded data)
- no destructive rollback is supported for facility or equipment steps in the current version

## Target Organization Pinning

Once a target organization is selected during onboarding, it is pinned in the session.
If the pinned organization is deleted externally, the flow resets to `create_organization`
and does NOT silently switch to another organization the user may belong to.

## Architecture

- Presentation: API resource, provider, processors, DTOs
- Domain: onboarding session aggregate + flow state/step value objects
- Infrastructure: Doctrine session record/mapper/repository
- Dependencies:
  - `Organization` module for organization queries
  - `Facility` module for facility existence checks
  - `Equipment` module for equipment existence checks
  - Shared command/query bus and transaction manager

## Notes

- Onboarding should remain orchestration-only.
- Business writes stay in the owning modules (`Organization`, `Facility`, etc.).
