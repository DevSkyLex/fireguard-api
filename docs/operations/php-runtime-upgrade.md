# PHP runtime security upgrade

This change prepares the response to CVE-2026-109618 and CVE-2026-109619.
It does not authorize a deployment. Review the two branch-specific PRs before
merging: a push to `main` or `develop` can trigger delivery after successful CI.

## Verified base and application image

The selected official Docker Hub base is:

```text
dunglas/frankenphp:1.13.2-php8.4.27-bookworm@sha256:49369ff211d81dc30a15890d58c0651d639aef0d9c0a9b91bcc6f387a77bd7a2
```

This immutable multi-platform reference selects FrankenPHP 1.13.2, PHP 8.4.27
and Debian 12 Bookworm. See the [official FrankenPHP 1.13.2 release](https://github.com/php/frankenphp/releases/tag/v1.13.2).
The image check, `sh /usr/local/bin/check-runtime`, verifies the PHP CLI and
FrankenPHP's embedded PHP, rejects PHP outside 8.4 or below 8.4.27, and verifies
FrankenPHP 1.13.2 and Bookworm. A successful CLI version check alone is insufficient.

The base digest above is **not** the application digest. `Deploy VPS` builds the
reviewed commit, publishes `ghcr.io/<owner>/<repository>@sha256:<application-digest>`,
and passes that application digest to Ansible. Record the resolved application
digest, source SHA, branch, CI/SonarQube results and runtime-check output for each environment.
Do not deploy the base directly or use a moving `latest`/`develop` tag as evidence.

## Separate rollout plans

Apply the focused runtime change independently to both branches. `main` previously
used the moving `1-php8.4-bookworm` base; `develop` already pinned its older base.
Keep each branch's application code and deployment safeguards when updating the
base and build checks.

| Environment | Branch | VPS directory | Compose project | Long-running PHP services to recreate |
| --- | --- | --- | --- | --- |
| `production` | `main` | `/srv/apps/fireguard/production/back` | `fireguard-production-back` | `app assistant_worker` |
| `development` | `develop` | `/srv/apps/fireguard/development/back` | `fireguard-dev-back` | `app async_worker webhook_worker assistant_worker scheduler_worker` |

These are repository installation records, not a live VPS inventory. Confirm them
against the selected GitHub environment and installed services before rollout.
Production retains the volume prefix `back`; development retains
`fireguard-dev-back`. Ansible installs the repository's `compose.prod.yaml` as
`compose.yaml` on the VPS and additionally loads `compose.dev.yaml` in development.
The local checkout's `compose.yaml` is a different development configuration.

Rebuild and validate each branch's application image before a later approved
rollout. Recreate **all** PHP services in the corresponding row from its new
application digest; a restart retains the old image. Reconcile the installed
service inventory with the row so an additional PHP worker cannot be missed.
Check development first, then production within its own reviewed window; a green
development image does not authorize deployment of that image to production.

On-demand services also inherit this runtime: both branches have `app_var_init`
and `jwt_init`; `develop` additionally has `geoip_maintenance`, `geoip_volume_init`
and `backup_files`. Their future containers must resolve the patched application
image. The optional development `fixture_loader` uses a separate fixture image,
which must also be rebuilt from the patched base before any independently
authorized future use. Do not launch these tools to check this upgrade.

The standard `Deploy VPS` playbook performs database migrations, RBAC
synchronization, volume-permission initialization and, in managed mode, environment
rendering. It is not an image-only operation and must not run under the present
instruction to leave databases, volumes, secrets and fixtures untouched. Plan and
approve those effects separately before delivery. Keep fixture reset disabled.
A separate image-only replacement requires prior verification that the installed
application source, schema and transport contracts already match the rebuilt
image. It must retain all installed operation-lock, Webhook drain and recovery
safeguards; this guide does not bypass them or prescribe a cutover command.

## Read-only verification after an authorized rollout

Run on the VPS with an account allowed to inspect the relevant containers.
Choose exactly one environment block, then replace `app_image_ref` with the
application digest recorded from that environment's deployment. The commands
below print selected image/state/version fields, without dumping environment
variables or application logs.

Production:

```sh
cd /srv/apps/fireguard/production/back
project=fireguard-production-back
runtime_services='app assistant_worker'
api_host=api.fireguard.valentin-fortin.pro
```

Development:

```sh
cd /srv/apps/fireguard/development/back
project=fireguard-dev-back
runtime_services='app async_worker webhook_worker assistant_worker scheduler_worker'
api_host=dev.api.fireguard.valentin-fortin.pro
```

Verify every service uses the expected application image ID, is running and
healthy, and passes both PHP version checks and the FrankenPHP/OS checks:

```sh
set -eu
app_image_ref='ghcr.io/<owner>/<repository>@sha256:<application-digest>'
expected_image_id=$(docker image inspect --format '{{.Id}}' "$app_image_ref")
for service in $runtime_services; do
  containers=$(docker ps -aq \
    --filter "label=com.docker.compose.project=$project" \
    --filter "label=com.docker.compose.service=$service" \
    --filter 'label=com.docker.compose.oneoff=False')
  test -n "$containers"
  for container in $containers; do
    printf '\n%s %s\n' "$service" "$container"
    docker inspect --format 'ref={{.Config.Image}} image={{.Image}} status={{.State.Status}} health={{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container"
    test "$(docker inspect --format '{{.Image}}' "$container")" = "$expected_image_id"
    test "$(docker inspect --format '{{.State.Status}}' "$container")" = running
    test "$(docker inspect --format '{{.State.Health.Status}}' "$container")" = healthy
    docker exec "$container" sh /usr/local/bin/check-runtime
  done
done
```

Check internal and public HTTP health, then inspect dependency health without
recreating dependencies:

```sh
for container in $(docker ps -q \
  --filter "label=com.docker.compose.project=$project" \
  --filter 'label=com.docker.compose.service=app' \
  --filter 'label=com.docker.compose.oneoff=False'); do
  docker exec "$container" curl --fail --silent --show-error http://localhost:8000/api/health
done
curl --fail --silent --show-error "https://$api_host/api/health"
docker ps --filter "label=com.docker.compose.project=$project" \
  --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}'
```

Require all expected containers to be present and running, including both database
services, Redis, Mercure and development Mailpit. Require `healthy` wherever a
health probe is configured; all persistent PHP services above have one. Worker health is a liveness
signal: also review private operational monitoring for successful queue processing,
new failures and restart loops without inserting test messages or fixtures.
The unchanged other environment must retain its own image and healthy containers.

If a check fails, stop rollout progression and use the installation's reviewed
recovery procedure. Do not roll back to an image containing vulnerable PHP.
Rebuild the last application revision compatible with the installed schema and
transport generation on this patched base, validate it through the matching
branch's gates, and deliver its new immutable application digest. An image
rollback cannot undo database, transport or file compatibility boundaries.
