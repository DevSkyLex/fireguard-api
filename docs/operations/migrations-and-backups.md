# Migrations and backups

Treat auth and main as separate backup and migration histories. A rollback of an image does not undo a persisted schema or data change.

**Authoritative references:** [Deployment](../../DEPLOYMENT.md) · [Makefile](../../Makefile) · [Doctrine mapping](../../config/packages/doctrine.yaml).

## Deployment sequence

Auth and main keep separate migration histories and backups. Apply compatible migrations before starting current workers, then validate dependency and security health.

```mermaid
flowchart TD
  Revision["Verify source, image and quality gate"] --> Identity["Validate environment and installation identity"]
  Identity --> Stop["Lock installation and drain every writer"]
  Stop --> Backup["Snapshot auth, main and matching application files"]
  Backup --> Auth["Apply auth migration history"]
  Auth --> Main["Apply main migration history"]
  Main --> Transports["Initialize persisted transports"]
  Transports --> Start["Start application and every current consumer"]
  Start --> Health["Validate dependencies, security and public health"]
```

The playbook defines the exact sequence and recovery behavior. Retain both backups,
their source revision and environment identity. Review restore compatibility before
replaying a message or changing an image. Development and production keep distinct
projects, databases, volumes and key material.

## Explicit migration configuration

```sh
php -d memory_limit=1G bin/console doctrine:migrations:status --configuration=config/migrations/auth.yaml
php -d memory_limit=1G bin/console doctrine:migrations:status --configuration=config/migrations/main.yaml
php -d memory_limit=1G bin/console doctrine:migrations:migrate --configuration=config/migrations/auth.yaml --dry-run
php -d memory_limit=1G bin/console doctrine:migrations:migrate --configuration=config/migrations/main.yaml --dry-run
```

Apply with `make migrate-auth`, `make migrate-main` or `make migrate-all` in the
deliberately selected environment. Never modify an existing migration. Validate
Doctrine mapping for both entity managers and check the owning module's rollout
requirements before starting workers compatible with the new schema.

## Backup and restore

Without activated off-host backups, managed deployment retains private consistent
pre-migration dumps and matching `files.tar`/`SHA256SUMS` under `VPS_APP_DIR/backups/`
(0700 directories, 0600 files). These local snapshots alone do not protect against
host loss. Activating the encrypted off-host helper replaces new local raw snapshots.
Neither archive includes deployment environment files or JWT key volumes. Secret
recovery must be provisioned independently in an operator-controlled encrypted store.

On migration, fixture reset or health failure, writers stay stopped and the
installation mutex remains held. An image rollback alone cannot repair schema or
data. Verify the failure and restore **both** compatible histories and matching
files, or finish forward-compatible migrations. Check secret recovery, scoped
record access, queues and scheduler execution in isolation before promotion.
Remove the reviewed `.fireguard-operation.lock` directory only after confirming no
maintenance/backup/deployment process still owns it; then start a reviewed rollout.

### Reviewed development deployment recovery

Failed development deployment `37080669400` stopped the writers after migrating
both histories, then failed when initializing several Messenger transports in one
command. The corrected playbook initializes each transport separately. A normal
rollout still refuses to acquire an existing installation lock.

For this specific incident, the manual `deploy-vps.yml` input
`reviewed_lock_recovery_run_id=37080669400` selects a guarded forward recovery.
Use it only after a normal deployment confirms the retained mutex and all other
development deployments have finished. Leave `image_ref` empty and
`reset_development_fixtures=false`; the source must be the current `develop` tip
with its exact successful CI and Sonar gate.

The helper verifies the failed job, source ancestry, immutable image digest and
revision, unchanged historical migration definitions, and the executed auth/main
versions. It also requires the fixed development installation, the original empty
lock identity and timestamps, stopped writers, no competing maintenance process
under the deployment user, and no competing container with access to its storage.
Other privileged operators must refrain from maintenance during recovery.
Missing or ambiguous evidence aborts before
releasing the mutex. It never restores or changes either database during inspection.

A stopped-writer refusal reports only the five fixed writer services, their
matching-container counts and counts by a closed set of Docker states. Unknown
state values are counted without publishing them. This diagnostic distinguishes
missing, duplicated or never-started containers. It includes no container IDs,
foreign labels, paths or configuration.

The reviewed installation retains one exited `app` and one exited
`assistant_worker`, while `async_worker`, `scheduler_worker` and `webhook_worker`
are absent. Only those three exact absences may be admitted for this incident.
Existing writer identities, stopped states and absences must remain identical in
both inventories before the lock is acquired. Missing app/assistant containers,
oneoffs, duplicates, changed identities or states, and newly appearing workers
remain blocking.

The original and current Compose contracts give the three absent workers only
`app_var` and `jwt_keys`, also referenced by the retained app and assistant. The
exception requires the exact named-volume mounts, destinations, access modes and
canonical sources for app/assistant, both databases and Redis. These retain
`app_var`, `jwt_keys`, `geoip_data`, `auth_database_data`, `main_database_data` and
`redis_data`; their existing references and physical identities must pass the
ordinary repeated proofs. This minimum contract does not replace the global
checks covering every present container and its storage, including Mercure and
Mailpit. The helper neither creates nor starts a missing writer during inspection.
A retained-storage refusal reports the fixed service, bounded mount counts and
closed mismatch categories such as source, destination or access mode. Actual
mount values and configuration remain private; the diagnostic grants no exception.

An identity refusal includes only bounded filesystem metadata: directory/symlink
flags, numeric owner and process IDs, permission bits, device/inode and timestamps.
Use that evidence to review the original lock; no directory contents, environment
values or private command output are reported. Do not chmod or chown the retained
lock to pass the check: that changes its ctime and destroys the original provenance.

A namespace refusal also reports the complete fixed ancestor chain as numeric
metadata, including owner/group, permissions, device/inode and nanosecond times.
Bounded process counts cover those owners and writable groups outside the recovery
process's own ancestry. They contain no process names, arguments, environment,
working directories or file descriptors. Unavailable process evidence is explicit;
neither these counts nor an empty snapshot authorizes a recovery exception.

The historical mutex was created with mode `0775` and the deployment user's
UID/GID `1001`; its mtime/ctime are both `2026-10-03T00:09:10.159681219Z`, inside the
reviewed failed job. The exception is pinned to that exact device, inode, owner,
group, mode and nanosecond timestamps. It also requires the deployment user's real
and effective identities, a protected canonical installation directory and
ancestor chain, and no active peer process with access through any UID/GID or
supplementary group. The protected parent directory owns the mutex namespace;
group access inside the empty historical directory does not allow another UID to
acquire or replace it. These proofs are repeated before removal. An altered
identity, unreviewed ancestor, active host peer or ambiguous process metadata still
blocks recovery; this is not a general exception for group-writable locks.

Diagnostic run `37163724126` also identified the fixed six-level installation
chain: `/srv`, `/srv/apps` and `/srv/apps/fireguard` belong to UID/GID `1000`,
with group writes on the last directory. The additional incident-specific path
requires the exact observed ownership, modes, device/inodes and nanosecond times
for all six ancestors as well as the unchanged historical lock. It neither
changes these permissions nor grants general trust to UID `1000`.

An authority peer is accepted only with a complete, repeated proof that it is
confined by the existing trusted rootful Docker/runc/kernel boundary. Attribute
each task independently through the controlled Docker cgroup and private PID
namespace; check all threads' identities, available capabilities and
`NoNewPrivs`. Validate the exact reviewed production application volumes against
their declarations and effective mounts, requiring the local driver, local scope,
no mount options and disjoint physical storage. A Web process with no mounts can
also qualify. Host peers, unknown volumes, missing evidence, changed relevant
cohorts or privileged processes refuse before release. Unrelated process churn
does not replace or invalidate a proven authority cohort.

The collector uses successful official Docker projections at an effective Engine
API version of at least `1.45`, including any explicit API override. It projects
only required metadata and option-empty flags; it never exports environment,
commands or option values. Container startup timestamps and kernel creation ticks
are retained as independent stable identities, without assuming their wall-clock
ordering. Foreign namespace links are not required or read to obtain this proof.

Physical volume checks use an explicit metadata inspector for this reviewed
shared-namespace incident. The deployment user first verifies the canonical,
protected Docker root and its numeric identity. A source-verified immutable image
then runs a fixed PHP program in an isolated container with no available
capabilities, `NoNewPrivs`, no network, a read-only root filesystem, no healthcheck
and a fixed working directory. Only `/var/lib/docker` is bound at its original
path, read-only and without importing submounts. Docker rejects an explicitly
private bind of its own root, so the request leaves propagation unset. Docker can
use one-way slave propagation for a shared source, or private behavior for a
private source. The actual root bind must be read-only and never shared or
unbindable. Host and inspector mount metadata must exclude mounts covering the
volume parent or any inspected volume, including descendants, before reading
directory metadata and again afterwards. Unrelated overlay and storage mounts
outside the inspected volume paths are ignored.

Future host mounts elsewhere below the root can propagate into a slave bind and
may be writable. The fixed, source-verified PHP program retains zero capabilities
throughout its lifetime and performs only the specified directory metadata and
process/mount-proof reads; it never reads or writes application files or traverses
unrelated submounts. This guard relies on that restricted program and the existing
no-maintenance assumption, not a claim that the entire Docker root remains private
or read-only forever. The helper does not change host mounts, permissions or LSM
profiles, and no bootstrap capabilities are granted.

Each inspected local volume is also mounted separately at a fixed proof target,
read-only with copying disabled. Docker can inherit one-way slave propagation for
these targets when preparing its root mount namespace; a named volume has no
explicit private-propagation guarantee. Each effective target must be private or
strictly one-way slave, never shared or unbindable, and have no submounts. Slave
peer metadata must be well formed and stable. The program repeats the relevant
mount signatures and complete directory identities before and after its reads.
An inherited future submount can be writable; the restricted program and
no-maintenance assumption also apply to these proof targets. Its directory
identity must match the `_data` directory observed below the nonrecursive
Docker-root bind. This checks Docker's actual volume view even
when the daemon and deployment process use different mount namespaces. Existing
volume metadata and immutable source-container references are checked before
creation and again before the inspector starts. Copy suppression alone does not
prevent Docker from creating a missing volume. The guard therefore requires
stable existing references under the no-maintenance assumption; it makes no
claim of an atomic inspect-and-create operation. Cleanup removes only the owned
inspector container and never its volumes.

The program checks directory metadata without following symlinks or reading
application files. It compares the root identity with the host, rejects aliases
and relevant nested mounts, and returns bounded numeric evidence and fixed
diagnostics. Physical evidence is repeated with both isolation inventories;
active-volume timestamps do not need to remain unchanged. This does not grant
filesystem permissions to the deployment user or relax the storage proof.

After repeating those checks, recovery replaces the reviewed empty lock and
immediately acquires it once. A competing owner is never removed or retried.
The regular backup, auth/main migrations, transport setup, startup and health checks
then run in full. A subsequent failure retains the new mutex; this incident-specific
input cannot release a newer lock. Uncatchable termination or host loss requires a
new manual inspection. Production, other failed runs, fixture resets and image
rollbacks are excluded from this recovery path.

## Encrypted periodic off-host snapshots

Provision `restic`, an initialized off-host repository, its independent encryption
password file (0600), and provider credentials privately. Copy
`ansible/backup.example.json` to the installation's `backup.json` (0600) and fill
the operator-reviewed values. The empty example fails closed. Select `storage_mode`
explicitly: `local` archives persistent application files; `external` additionally
requires `storage_export_argv`, which must export a version-consistent object-store
snapshot to the private `FIREGUARD_EXPORT_DIR` while writers are paused. Other
writers to that object store must be paused by the operator's export mechanism.
Set `secret_recovery_reference` to the independently tested encrypted secret store;
the helper records this reference but does not claim those secrets were restored.

`repository` accepts only off-host restic backends (`sftp:`, `s3:`, HTTPS `rest:`,
`b2:`, `azure:`, `gs:`). Optional `restic_env` supplies private provider credentials
at runtime; it is excluded from all snapshots and diagnostics. A missing binary,
destination, repository key, external export or secret-recovery reference fails
before any service is stopped. `validate` probes the repository; it never creates
one or invents backup success.

After prerequisites pass, explicitly set `FIREGUARD_BACKUP_ENABLED=true` and select
`FIREGUARD_BACKUP_CONFIG` if needed. Ansible then installs the 02:17 daily job
(host timezone) and requires an encrypted off-host snapshot before migrations.
Activation defaults to false. A periodic snapshot restores exactly the set of
writers previously running and verifies that state; a resume failure stops every
managed writer again and retains the installation mutex for reviewed recovery.
Activation does not delete existing local raw snapshots. Retire them privately
after a successful isolated recovery exercise and a reviewed retention decision.
The private manifest includes the source image, auth/main table counts, archive
checksums, storage mode and timestamp. Retention is seven daily, five weekly and
twelve monthly snapshots, grouped by installation rather than temporary archive path.
Monitor `backup-status.prom`: alert on failed attempts and a last successful snapshot
capture age greater than the accepted RPO. The completion timestamp is recorded
separately, so a long upload cannot make old source data appear recent. Upload/prune/resume failures
do not advance its last-success metric.

```sh
python3 fireguard-backup.py validate --config /operator/selected/backup.json
python3 fireguard-backup.py snapshot --config /operator/selected/backup.json --app-dir /operator/selected/installation
```

The destination, encryption key, provider access, secret store and storage export
are operator prerequisites. Repository support and synthetic tests do not establish
an installed or successful production backup service.

## Isolated restore exercise

Select an actual encrypted snapshot ID and the immutable PostgreSQL image used by
that installation. The exercise decrypts to a private temporary directory and
restores auth/main into uniquely named, disposable containers with no network,
published ports or production volumes. It rehydrates regular application files to
the private temporary directory and verifies each checksum. It verifies all public
table counts and validated database constraints, then removes only its resources.

```sh
python3 fireguard-backup.py restore-drill --config /operator/selected/backup.json \
  --app-dir /operator/selected/installation --snapshot-id SELECTED_SNAPSHOT \
  --postgres-image 'postgres:16-alpine@sha256:721873c34ceb9f8d8fc265984940dc982404c105f19ad51be9fdc5970a6080ea'
```

`restore-drill.json` records the snapshot, elapsed recovery time and verified checks.
Use that measured duration to set the installation's RTO; compare actual snapshot
age with its RPO. This database/file exercise explicitly leaves application smoke
checks and secret recovery unverified. Before promotion, restore the independent
secret store and object storage to isolated equivalents; verify authentication,
tenant denial, representative file downloads, idempotent message replay and the
next scheduled sweep. Record those checks alongside the drill evidence.

## Database Migrations

**Pre-deployment check**:

```bash
# List pending auth migrations
php -d memory_limit=1G bin/console doctrine:migrations:status --configuration=config/migrations/auth.yaml

# List pending main migrations
php -d memory_limit=1G bin/console doctrine:migrations:status --configuration=config/migrations/main.yaml

# Dry-run auth migrations (review SQL)
php -d memory_limit=1G bin/console doctrine:migrations:migrate --dry-run --configuration=config/migrations/auth.yaml

# Dry-run main migrations (review SQL)
php -d memory_limit=1G bin/console doctrine:migrations:migrate --configuration=config/migrations/main.yaml --dry-run
```

**Execute migrations**:

```bash
# Apply auth migrations
php -d memory_limit=1G bin/console doctrine:migrations:migrate --no-interaction --configuration=config/migrations/auth.yaml

# Apply main migrations
php -d memory_limit=1G bin/console doctrine:migrations:migrate --configuration=config/migrations/main.yaml --no-interaction

# Verify auth schema
php -d memory_limit=1G bin/console doctrine:schema:validate --em=auth

# Verify main schema
php -d memory_limit=1G bin/console doctrine:schema:validate --em=main
```

## Backup Strategy

The managed playbook backs up both databases before migrations. For an independent
backup, select the actual connections without putting passwords in shell history:

```sh
pg_dump --format=custom --dbname="$AUTH_DATABASE_URL" --file="$BACKUP_DIR/auth.dump"
pg_dump --format=custom --dbname="$MAIN_DATABASE_URL" --file="$BACKUP_DIR/main.dump"
```

`BACKUP_DIR` is an operator-selected private destination, not an application variable.
Use a credential mechanism supported by your PostgreSQL tooling and validate that
the URLs/options are suitable for libpq. Restore into separately selected recovery
databases first; record the schema/source version, verify access and queues, and
promote only after the recovery checks. Never treat an image rollback as a schema restore.

Back up JWT/encryption material through the installation's independently protected
secret mechanism. Keep its restore exercise separate from ordinary archives and
retain receipts for every message and snapshot that can still replay.

## Maintenance evaluation rollout

Apply main migration `Version20260920193000` before deploying evaluation-aware consumers.
Keep the main outbox worker and the existing maintenance sweep running: equipment and
compliance-policy events trigger recomputation, while the sweep repairs missed events and
initializes legacy schedules. A null `evaluatedAt` intentionally means not yet evaluated;
report generation time must not be used as data freshness. Monitor outbox backlog and
unevaluated equipment counts until the first complete sweep has finished. No existing
archived safety register is regenerated.

## Import simulation confirmation rollout

Apply main migration `Version20260921230000` before deploying confirmation consumers.
Preserve retained CSV files while a simulation or its linked real import can still be
confirmed, resumed or replayed. Both jobs intentionally reference the same immutable
bytes; cleanup must account for both references. Monitor failed main outbox deliveries
and import progress. A successful simulation does not reserve quotas or resource references.
