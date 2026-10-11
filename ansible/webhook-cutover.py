#!/usr/bin/env python3
"""Drain the installed auth Webhook transport before replacing its worker.

Only installed container IDs and aggregate queue counts are inspected. Compose
files, deployment secrets and queued envelopes are never copied or printed.
The caller owns the installation operation lock and keeps it on any failure.
"""
from __future__ import annotations

import argparse
from pathlib import Path
import re
import subprocess
import sys
import time

WRITERS = {"app", "async_worker", "webhook_worker", "assistant_worker", "scheduler_worker"}
STATE_FORMAT = "{{.State.Running}} {{.State.ExitCode}} {{.State.OOMKilled}}"
TRANSPORT_PROBE = r'''
require 'vendor/autoload.php';
$config = Symfony\Component\Yaml\Yaml::parseFile('config/packages/messenger.yaml');
$transport = $config['framework']['messenger']['transports']['webhook'] ?? null;
$dsn = is_array($transport) ? ($transport['dsn'] ?? '') : $transport;
if (is_string($dsn) && preg_match('/^%env\(([A-Z_]+)\)%$/D', $dsn, $match)) {
  $dsn = getenv($match[1]);
}
echo is_string($dsn) && preg_match('/^doctrine:\/\/(auth|main)(?:\?|$)/D', $dsn, $match)
  ? $match[1] : 'unsupported';
'''
TABLE_PROBE = '''exec psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -AtX -v ON_ERROR_STOP=1 -c "SELECT CASE WHEN to_regclass('public.messenger_messages') IS NULL THEN 'absent' ELSE 'present' END"'''
COUNT_PROBE = '''exec psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -AtX -v ON_ERROR_STOP=1 -c "SELECT COUNT(*) FROM public.messenger_messages WHERE queue_name = 'webhook'"'''
FRESH_PROBE = '''exec psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -AtX -v ON_ERROR_STOP=1 -c "SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public'"'''


class CutoverError(RuntimeError):
    """Sanitized, operator-actionable failure without provider diagnostics."""


def run(argv, *, timeout=30):
    try:
        result = subprocess.run(argv, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                text=True, timeout=timeout, check=False)
    except (OSError, subprocess.TimeoutExpired) as error:
        raise CutoverError("Webhook cutover command failed or timed out; the operation lock remains held.") from error
    if result.returncode:
        raise CutoverError("Webhook cutover command failed; inspect private diagnostics. No messages were discarded.")
    return result.stdout.strip()


def installed(app_dir, projects):
    containers = {}
    for project in sorted(set(projects) - {""}):
        output = run(["docker", "ps", "--all", "--filter",
                      "label=com.docker.compose.project=" + project, "--filter",
                      "label=com.docker.compose.project.working_dir=" + str(app_dir), "--format",
                      '{{.ID}} {{.Label "com.docker.compose.service"}}'])
        for line in output.splitlines():
            match = re.fullmatch(r"([a-f0-9]{12,64}) ([A-Za-z0-9][A-Za-z0-9_.-]*)", line)
            if not match:
                raise CutoverError("Installed container metadata is invalid; cutover was refused.")
            identifier, service = match.groups()
            if service in WRITERS or service == "auth_database":
                containers[identifier] = service
    return containers


def state(identifier):
    value = run(["docker", "inspect", "--format", STATE_FORMAT, identifier])
    match = re.fullmatch(r"(true|false) ([0-9]+) (true|false)", value)
    if not match:
        raise CutoverError("Installed container state is invalid; cutover was refused.")
    return match[1] == "true", int(match[2]), match[3] == "true"


def stop(identifiers, grace):
    if not identifiers:
        return
    run(["docker", "stop", "--time", str(grace), *sorted(identifiers)], timeout=grace + 30)
    for identifier in identifiers:
        running, exit_code, oom = state(identifier)
        if running or exit_code == 137 or oom:
            raise CutoverError("A writer did not stop gracefully; migration was refused and the operation lock remains held.")


def queue_count(database, *, fresh):
    table = run(["docker", "exec", database, "sh", "-c", TABLE_PROBE])
    if table == "absent" and fresh and run(["docker", "exec", database, "sh", "-c", FRESH_PROBE]) == "0":
        return 0
    if table != "present":
        raise CutoverError("The installed auth queue table is unavailable; cutover was refused.")
    count = run(["docker", "exec", database, "sh", "-c", COUNT_PROBE])
    if not re.fullmatch(r"[0-9]+", count):
        raise CutoverError("The installed auth queue count is invalid; cutover was refused.")
    return int(count)


def cutover(app_dir, projects, *, deadline=900, poll=5, grace=120, defer_bootstrap=False, already_drained=False):
    containers = installed(app_dir, projects)
    writers = [identifier for identifier, service in containers.items() if service in WRITERS]
    databases = [identifier for identifier, service in containers.items() if service == "auth_database"]
    workers = [identifier for identifier, service in containers.items() if service == "webhook_worker"]
    if not databases and not writers and defer_bootstrap:
        # This is not evidence of an empty volume. The caller MUST invoke this
        # guard again after bringing the installed database volume online.
        return "bootstrap-deferred"
    if len(databases) != 1:
        raise CutoverError("Exactly one installed auth database is required before cutover.")
    if len(workers) > 1:
        raise CutoverError("Multiple installed Webhook workers were found; cutover was refused.")
    database = databases[0]
    # HTTP and every producer, including main_outbox, finish before the drain.
    stop([identifier for identifier in writers if identifier not in workers], grace)
    connection = None
    if workers:
        if state(workers[0])[0]:
            connection = run(["docker", "exec", workers[0], "php", "-r", TRANSPORT_PROBE])
            if connection not in ("auth", "main"):
                raise CutoverError("The installed Webhook transport is unsupported; cutover was refused without discarding its work.")
        elif not already_drained:
            raise CutoverError("The installed Webhook worker is stopped and its transport is unverified; restore that exact worker before retrying.")
    remaining = queue_count(database, fresh=not writers)
    if remaining:
        if len(workers) != 1 or not state(workers[0])[0]:
            raise CutoverError("Auth Webhook work remains without one running legacy worker; restore that exact worker before retrying.")
        if connection != "auth":
            raise CutoverError("Auth Webhook work remains but the installed worker is not confirmed on auth; cutover was refused.")
        expires = time.monotonic() + deadline
        while remaining:
            if time.monotonic() >= expires:
                raise CutoverError("Auth Webhook drain deadline exceeded; messages and the legacy worker were retained, producers remain stopped.")
            if not state(workers[0])[0]:
                raise CutoverError("The legacy Webhook worker stopped before the auth queue drained; messages were retained.")
            time.sleep(min(poll, max(0, expires - time.monotonic())))
            remaining = queue_count(database, fresh=False)
    # Received rows are counted too. Wait for the process to finish and then check
    # again because its final ACK or retry may have changed the durable queue.
    stop(workers, grace)
    if queue_count(database, fresh=not writers) != 0:
        raise CutoverError("Auth Webhook work appeared during graceful shutdown; messages were retained and migration was refused.")
    return "drained" if writers else "fresh"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--app-dir", required=True, type=Path)
    parser.add_argument("--project", action="append", required=True)
    parser.add_argument("--deadline", type=int, default=900)
    parser.add_argument("--poll", type=int, default=5)
    parser.add_argument("--grace", type=int, default=120)
    parser.add_argument("--defer-bootstrap", action="store_true")
    parser.add_argument("--already-drained", choices=("true", "false"), default="false",
                        help="Only for the mandatory second guard after the initial runtime guard succeeds")
    options = parser.parse_args()
    if options.deadline < 1 or options.poll < 1 or options.grace < 90:
        parser.error("Positive drain budgets and a graceful shutdown of at least 90 seconds are required.")
    try:
        outcome = cutover(options.app_dir, options.project, deadline=options.deadline,
                          poll=options.poll, grace=options.grace, defer_bootstrap=options.defer_bootstrap,
                          already_drained=options.already_drained == "true")
    except CutoverError as error:
        print(str(error), file=sys.stderr)
        return 1
    if outcome == "bootstrap-deferred":
        print("Installed runtime is absent; auth volume verification is deferred until dependencies start.")
    else:
        print("Installed Webhook cutover verified: " + outcome + "; auth queue is empty.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
