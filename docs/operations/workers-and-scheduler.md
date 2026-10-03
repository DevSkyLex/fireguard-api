# Workers and scheduler

A healthy HTTP process does not establish that queued or scheduled work is being consumed. Start and monitor the transports configured for the deployed revision.

**Authoritative references:** [Messenger configuration](../../config/packages/messenger.yaml) · [Production Compose](../../compose.prod.yaml) · [Shared](../../src/Shared/MODULE.md).

## Managed workers

Compose declares four supervised consumers: `async_worker` consumes `main_outbox`
and `async`; `webhook_worker` consumes `webhook`; `assistant_worker` consumes
`assistant`; `scheduler_worker` consumes all five `scheduler_*` receivers. Webhook
and model calls cannot block the ordinary async receiver. Ansible initializes
`main_outbox main_failed async webhook assistant failed` after both migrations and
before starting any current consumer, including on fresh bootstrap. Each transport
uses a separate `messenger:setup-transports` invocation, which accepts one name.

All writers stop before snapshots, migrations and development fixture resets.
Consumers receive SIGTERM and have 120 seconds to finish their current message;
the app has the same deployment drain deadline. A failure leaves writers stopped
and `.fireguard-operation.lock` held for reviewed recovery. Cron maintenance and
backups acquire the same atomic mutex and cannot overlap the rollout.

Docker health checks require a recent receiver-loop heartbeat and a live recorded
PID. Every five minutes, host maintenance also checks both durable queue histories
for messages overdue by more than 900 seconds or any failed messages, and checks
successful sweep timestamps. Hourly sweeps have a two-hour allowance, domain
verification two days, and weekly digests eight days. The first scheduler start
creates a persistent monitoring baseline for the corresponding first-run
allowance. Worker restarts never reset it; stale sweep markers remain unhealthy.
No payload, token or customer identifier is observed.

For bounded queue metadata, run `app:workers:queues --max-age=900`; for sweep
freshness, run `php bin/worker-health.php --sweeps` in `async_worker`. Alert on the
fixed `fireguard-maintenance` failure messages. Do not use HTTP health as evidence
of queue consumption or successful scheduled work.

For an illustrative configured transport:

```sh
php -d memory_limit=1G bin/console messenger:consume <transport> --time-limit=3600 --memory-limit=256M
```

Run workers under the deployed service manager/Compose restart policy, with the same
runtime context and database ownership as the application. Select an actual configured
transport; the placeholder is not an executable target.

## Recovery

Inspect queue age, failed destinations, business outcome projections and reservation
or lease expiry. Business-rejected/skipped operations follow the owner's explicit
retry contract. Technical failures use their configured Messenger retry/failed transport.
Replaying a failed message cannot bypass current policy, tenant access or stable receipt
identity. Keep receipts for the replay horizon and preserve attempt history.

## Messenger Worker & Scheduler (required)

**Purpose**: asynchronous commands (intervention publication, maintenance
sweeps, recurrence materialization, automation rules) and the recurring
schedules provided by the `Maintenance`, `Intervention`, `Approval`,
`Inspection` and `Organization` modules (`#[AsSchedule('maintenance')]`,
`#[AsSchedule('intervention')]`, `#[AsSchedule('approval')]`,
`#[AsSchedule('inspection')]`, `#[AsSchedule('organization')]`).

**The `assistant` transport now has its own container**, `assistant_worker`, in
both `compose.yaml` and `compose.prod.yaml` — separate from the general worker
for the same reason its transport is separate from `async`: an unresponsive
Ollama backend must never starve the other queues. A deployment that runs the
general worker below **and** the compose stack is already covered; a deployment
that runs neither leaves every assistant reply at `pending` forever, with no
cancel endpoint and no server-side deadline to settle it.

**Worker command** (must run permanently, e.g. under supervisor/systemd):

After the independent auth/main migrations, initialize framework-owned tables
before starting the new worker version:

```bash
for transport in main_outbox main_failed async webhook assistant failed; do
  php -d memory_limit=1G bin/console messenger:setup-transports "$transport" --env=prod --no-interaction || exit 1
done
```

`main_outbox` is the PostgreSQL outbox on the main connection; its table is
excluded from ORM schema diffs because Messenger owns its schema. Deployment
does not create a cross-database transaction. Both Compose configurations include
the workflow consumer. Production keys and application configuration must already
be provisioned through the normal deployment environment.

Use the [canonical worker command in OPERATIONS.md](../../OPERATIONS.md#messenger-worker--scheduler-required).
Its complete transport list is checked against the configured queues and schedule
providers by the architecture tests; this guide does not maintain a second copy of the command.

Monitor `messenger:stats main_outbox main_failed async failed`, the age of the
oldest undelivered message, and business jobs that remain pending/processing.
Inspect failures with `messenger:failed:show --transport=main_failed` (or `failed`
for general work). After correcting the cause, retry selected message IDs with
`messenger:failed:retry <id> --transport=main_failed`. Replays retain event identity;
do not delete consumer receipts while messages or backups can still replay them.

For the import cutover, drain or stop the previous worker version before starting
new workers. Reconcile legacy processing jobs before allowing automatic resumption:
the previous implementation could create rows beyond its last saved counter and
cannot retroactively supply atomic receipts for them. New jobs commit every row's
creation, receipt and progress together. Keep row receipts for the lifetime of the
job and any replayable message/backup. A stopped worker's lease expires after 120
seconds; live row transactions remain protected by a database lock.

Imported invitations are delivered from `main_outbox` after their creation commits.
Delivery failures do not erase confirmed import rows. Retry the delivery message;
expired, revoked or rotated invitations are ignored. Restrict access to queue storage
and failure inspection: invitation messages contain the accept URL until consumed.
Never copy payloads into logs. External email delivery is at least once; a provider
acknowledgement lost after delivery can cause a repeat.

**The transport list must be kept in sync with `config/packages/messenger.yaml`.**
A transport with no consumer does not error — it silently accumulates messages
that are never processed, which is indistinguishable from "the feature does not
work" and produces no log line to investigate. Concretely, per transport:

| Transport                | Omitting it means                                                                                                                         |
| ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------- |
| `main_outbox`            | Publications, durable domain events, automation rules, CSV imports and deferred invitations never execute                                 |
| `async`                  | Recurrence materialization and maintenance recompute never execute                                                                        |
| `webhook`                | No outbound webhook is ever delivered; subscriptions look healthy and fire nothing                                                        |
| `assistant`              | **Every assistant question stays `pending` forever** — the user asks, no answer and no error ever arrives                                 |
| `scheduler_maintenance`  | Inspection due dates are never recomputed; no due/overdue reminders                                                                       |
| `scheduler_intervention` | Recurring interventions are never materialized                                                                                            |
| `scheduler_approval`     | Pending four-eyes approval requests never expire; they accumulate until manually decided                                                  |
| `scheduler_inspection`   | Non-conformity SLA breaches are never escalated; the per-severity SLAs configured in the organization compliance settings stay decorative |
| `scheduler_organization` | The weekly organization digest email is never sent; the `weeklyDigest` toggle in the organization notification settings stays decorative  |

The `webhook` and `assistant` transports are deliberately isolated from `async`
so a slow or unreachable third party (or a cold model load) cannot starve
ordinary async work. `assistant` runs with `retry_strategy.max_retries: 1`
because replaying a partially streamed reply would republish its fragments —
the `assistant_messages.status` state machine is what makes a retry replace
rather than append.

Notes:

- Each `#[AsSchedule('<name>')]` provider exposes a `scheduler_<name>`
  transport that the worker must consume for its ticks to fire; consuming
  `async` alone runs the routed commands but never triggers the schedules.
- Schedules are `stateful()` (missed runs recovered from cache) and
  `lock()`-guarded, so multiple workers can consume the scheduler transports
  without duplicate ticks — **provided `LOCK_DSN` points to a shared store in
  production** (e.g. `pgsql+advisory://…` on the main database). The dev
  default `flock` only protects a single host.
- List schedules and next run dates: `php -d memory_limit=1G bin/console debug:scheduler`.
- The `organization` schedule is weekly, not hourly: `SendWeeklyDigestsCommand`
  fires every **Monday at 06:00 UTC** (an anchored 1-week periodical
  trigger). Per organization it
  aggregates overdue interventions, maintenance deadlines (next 7 days +
  overdue) and unresolved non-conformities, and emails the digest to the
  members holding `organization.settings.write`. An organization whose
  counters are all zero receives **no email at all** — silence is deliberate,
  not a delivery failure. The org-level `weeklyDigest` and `emailEnabled`
  toggles and each recipient's own `organization`-category email preference
  all suppress delivery.

## Data Cleanup

Ansible requires `crontab` and a running `cron`/`crond` service before changing services.
It installs three independent daily jobs through the deployment user's private
`fireguard-maintenance.sh` helper before application shutdown: GeoIP update and freshness
check at 03:17, revoked-session location cleanup at 03:37, and auth retention at 03:47
(host timezone). Auth retention runs in `app` with the `php` entrypoint, `memory_limit=1G`
and `--env=prod`; a failed GeoIP purge does not prevent it.

The one-shot `geoip_maintenance` service mounts the database volume writable; the serving
app mounts it read-only. Updates/checks use `--if-enabled` and skip when collection is disabled.
Preserve the host helper and cron entries during a historical rollback: GeoIP jobs skip a
valid Compose configuration without their service, while auth retention still runs through
`app`. Invalid Compose configuration and maintenance failures log fixed messages under
`fireguard-maintenance` and return nonzero. Do not install the illustrative cron below a
second time on an Ansible-managed host. See
[GeoIP operations and privacy](../guides/geoip-operations-and-privacy.md).


**Purpose**: Remove expired tokens, sessions, OTPs, and revoked data.

**Command**:

```bash
php -d memory_limit=1G bin/console app:cleanup:auth-data --days=90
```

**Dry run** (preview):

```bash
php -d memory_limit=1G bin/console app:cleanup:auth-data --days=90 --dry-run
```

**Illustrative cron schedule** (choose an installed policy and application directory):

```cron
0 3 * * * cd /path/to/application && php -d memory_limit=1G bin/console app:cleanup:auth-data --days=90 >> /var/log/cleanup.log 2>&1
```

**Data cleaned**:

| Dataset         | Retention Rule                        |
| --------------- | ------------------------------------- |
| Sessions        | Revoked > X days OR inactive > X days |
| Consents        | Revoked > X days                      |
| Access Tokens   | Expired > X days                      |
| Refresh Tokens  | Expired > X days                      |
| Auth Codes      | Expired > X days                      |
| OTPs            | Expired > X days                      |
| Trusted Devices | Expired OR revoked > X days           |
