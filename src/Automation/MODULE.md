# Automation Module

## Purpose and ownership

Automation turns committed domain events into policy-controlled system actions.
The current rule is `auto_create_intervention_on_critical_nc`: a critical
non-conformity can create a corrective intervention draft. The organization opts
in through its automation policy; the default is disabled. Policy is reread when
executing, never inferred from the triggering event or the browser.

## Delivery and local atomicity

`AddNonConformityHandler` records its event in the main transactional outbox.
`AutomationTriggerSubscriber` recognizes critical severity and records the rule
command in `main_outbox`. Its main-owned consumption receipt and command commit
together. Durable delivery errors propagate for retry; legacy synchronous event
sources retain their best-effort behavior.

Execution reserves the unique `(rule_key, subject_id)` action inside the same main
transaction as its draft, work items, outcome and durable outcome event. Duplicate
commands are no-ops. An interruption rolls everything back; a caught action failure
records a failed run and event in a fresh conditional reservation. A competing
worker's committed success cannot be replaced by that failure. Technical failures
while recording the outcome reach Messenger's retry and `main_failed` destination.
Delivery is at least once, with at most one committed local draft per action.

## Rule contract

The trigger carries `organizationId`, `inspectionId`, `nonConformityId` and
`severity`. It has no facility/equipment identity, so the resulting draft has no
inferred site. The draft uses `InterventionDraftFactoryPort`, a system actor,
`origin: automation:<rule key>`, `type: inspection_campaign`, urgent priority and
one required inspection work item targeting the non-conformity and inspection.
Its due date uses the current organization SLA for the triggering severity.
Disabled rules record a skipped run without creating a draft.

## Boundaries

- `AutomationPolicyPort` is implemented by Organization's public adapter.
- `AutomationRuleQueuePort` uses the raw Messenger bus because an asynchronous
  send has no synchronous command result.
- `AutomationRunPort` owns the main run log and unique action reservation.
- Outcome events are system-attributed and consumed by Audit with auth-owned
  receipts, independently of the producer's main transaction.
- Organization-scoped policy, paginated attempt history and attempt detail require
  `organization.automation.read`; explicit retry additionally requires
  `organization.automation.manage`. Existing administrator wildcards include both.
  Policy editing stays under Organization's settings permission.
- Only the owning module's safe output is published: raw exception details and retained
  trigger payloads never reach history responses. Failures expose `automation_action_failed`.

## Persistence and operations

`automation_runs` lives in main, with unique `(rule_key, subject_id)` and an
organization index. Organization identity is denormalized; no cross-database
foreign key exists. `consumed_events` receipts and the framework-owned
`messenger_messages` table support durable triggers and outcomes.

Initialize transports before workers, consume `main_outbox`, monitor failed run
counts and `main_failed`, and keep receipts while messages can be replayed. See
`Shared/MODULE.md` and `OPERATIONS.md` for setup and recovery commands.

## Validation

Unit tests cover policy, deduplication, draft shape and outcomes. PostgreSQL tests
cover real reservations, native transport rollback/commit, competing consumers,
auth/main receipt isolation and publication replay. Tests use WebTestCase or
KernelTestCase; no external Stripe, webhook or mail delivery is required.

## Explicit retry and history

`GET /organizations/{organizationId}/automation` reports the effective rule. `/automation/runs`
lists attempts with server totals; `/automation/attempts/{id}` reads one scoped attempt.
`POST /automation/runs/{runId}/retry` requires the failed `attemptId` and returns 202 with a new
identity. The action row retains its unique `(rule_key, subject_id)` reservation permanently.
The retry locks that row, rechecks policy/access, appends one pending attempt and enqueues its
identity on `main_outbox` in the same transaction. A stale or non-retryable request returns
409 `automation_retry_conflict`; clients reread rather than silently resubmit.

Workers claim only the expected pending attempt, recheck policy and atomically commit its action
and outcome. Duplicated old triggers and completed attempt commands cannot replay an action.
Historical attempts remain unchanged when a retry starts. Disabled policies prevent retry and
cause already-queued attempts to be skipped. Policy changes are effective at worker execution.

Main migration `20260921234000` creates `automation_attempts` and retained trigger/current-attempt
columns. Existing outcomes are backfilled as first attempts, without invented trigger payloads;
those legacy failures remain readable but cannot be retried. Deploy the migration, drain/restart
old workers, then enable the frontend. Do not roll back to a worker that ignores retry identity.
