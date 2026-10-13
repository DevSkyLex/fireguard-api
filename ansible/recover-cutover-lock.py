#!/usr/bin/env python3
"""Release only a verified, empty lock left by the failed development cutover.

The caller must verify the public GitHub run/job metadata before passing it here.
This helper inspects selected Docker metadata, process identities and aggregate
auth queue counts. Its only runtime mutation is removing the old empty lock.
"""
from __future__ import annotations

import argparse
from datetime import datetime
import json
import os
from pathlib import Path
import re
import stat
import subprocess
import sys
import time

LOCK = ".fireguard-operation.lock"
WRITERS = {"app", "async_worker", "webhook_worker", "assistant_worker", "scheduler_worker"}
SKEW = 60
CONTAINER_FORMAT = '''{"project":{{json (index .Config.Labels "com.docker.compose.project")}},"working_dir":{{json (index .Config.Labels "com.docker.compose.project.working_dir")}},"service":{{json (index .Config.Labels "com.docker.compose.service")}},"image":{{json .Image}},"running":{{json .State.Running}},"exit_code":{{json .State.ExitCode}},"oom_killed":{{json .State.OOMKilled}},"status":{{json .State.Status}},"finished_at":{{json .State.FinishedAt}}}'''
IMAGE_FORMAT = '''{"revision":{{json (index .Config.Labels "org.opencontainers.image.revision")}},"source":{{json (index .Config.Labels "org.opencontainers.image.source")}}}'''
OPERATIONS = {
    "ansible", "ansible-playbook", "ansible-pull", "ansible-runner", "restic", "pg_dump", "pg_restore",
    "docker", "docker-compose", "psql",
    "fireguard-maintenance.sh", "fireguard-backup.py", "fireguard-webhook-cutover.py",
    "webhook-cutover.py", "recover-cutover-lock.py", "fireguard-recover-cutover-lock.py",
}


class RecoveryError(RuntimeError):
    """A fixed error class, with no command output or private diagnostics."""


class SafeParser(argparse.ArgumentParser):
    def error(self, message):
        raise RecoveryError("invalid-arguments")


def run(argv, *, timeout=20):
    try:
        result = subprocess.run(argv, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                                text=True, timeout=timeout, check=False, shell=False)
    except (OSError, subprocess.TimeoutExpired) as error:
        raise RecoveryError("command-unavailable") from error
    if result.returncode:
        raise RecoveryError("command-failed")
    return result.stdout.strip()


def metadata(options):
    if not re.fullmatch(r"[a-z0-9][a-z0-9_-]{0,62}", options.project):
        raise RecoveryError("invalid-project")
    if not re.fullmatch(r"[a-f0-9]{40}", options.failed_source_sha):
        raise RecoveryError("invalid-source")
    if not re.fullmatch(r"[1-9][0-9]{0,19}", options.failed_run_id):
        raise RecoveryError("invalid-run")
    if not re.fullmatch(r"https://github\.com/[A-Za-z0-9][A-Za-z0-9_.-]*/[A-Za-z0-9][A-Za-z0-9_.-]*", options.repository):
        raise RecoveryError("invalid-repository")
    if not all(re.fullmatch(r"[1-9][0-9]{0,10}", value) for value in (options.failed_start, options.failed_end)):
        raise RecoveryError("invalid-window")
    start, end = int(options.failed_start), int(options.failed_end)
    if start > end or end - start > 86400 or end > time.time() + SKEW:
        raise RecoveryError("invalid-window")
    return start, end


def fingerprint(value):
    return (value.st_dev, value.st_ino, value.st_mode, value.st_nlink,
            value.st_mtime_ns, value.st_ctime_ns)


def lock_state(app, start, end):
    try:
        if not app.is_absolute() or app.resolve(strict=True) != app:
            raise RecoveryError("invalid-app-path")
        app_stat = app.lstat()
        lock = app / LOCK
        lock_stat = lock.lstat()
        if not stat.S_ISDIR(app_stat.st_mode) or not stat.S_ISDIR(lock_stat.st_mode):
            raise RecoveryError("invalid-lock-path")
        if not all(start - SKEW <= value <= end + SKEW
                   for value in (lock_stat.st_mtime, lock_stat.st_ctime)):
            raise RecoveryError("lock-outside-failed-window")
        if any(lock.iterdir()):
            raise RecoveryError("lock-not-empty")
        return fingerprint(app_stat), fingerprint(lock_stat)
    except OSError as error:
        raise RecoveryError("lock-unavailable") from error


def process_parent(directory):
    value = (directory / "stat").read_text()
    # Linux encloses comm in parentheses; it can contain spaces and ')'.
    fields = value[value.rfind(")") + 2:].split()
    if len(fields) < 2 or not fields[1].isdigit():
        raise RecoveryError("process-state-unknown")
    return int(fields[1])


def operation_tokens(argv):
    # Arguments stay internal. Only the presence of an operation is returned.
    return any(Path(os.fsdecode(token)).name in OPERATIONS
               or re.fullmatch(r"AnsiballZ_[A-Za-z0-9_]+\.py", Path(os.fsdecode(token)).name)
               for token in argv if token)


def assert_no_operations(proc=Path("/proc"), pid=None):
    try:
        ancestors = set()
        current = os.getpid() if pid is None else pid
        while current:
            if current in ancestors or len(ancestors) > 128:
                raise RecoveryError("process-state-unknown")
            ancestors.add(current)
            current = process_parent(proc / str(current))
        for directory in proc.iterdir():
            if not directory.name.isdigit() or int(directory.name) in ancestors:
                continue
            try:
                with (directory / "cmdline").open("rb") as stream:
                    command = stream.read(65537)
                if len(command) > 65536 or (command and not command.endswith(b"\0")):
                    raise RecoveryError("process-state-unknown")
                if operation_tokens(command.split(b"\0")):
                    raise RecoveryError("active-operation")
            except FileNotFoundError:
                # A process that exited during enumeration cannot hold the lock.
                continue
    except (OSError, ValueError) as error:
        raise RecoveryError("process-state-unknown") from error


def json_metadata(argv, keys):
    try:
        value = json.loads(run(argv))
    except (ValueError, TypeError) as error:
        raise RecoveryError("container-metadata-invalid") from error
    if not isinstance(value, dict) or set(value) != set(keys):
        raise RecoveryError("container-metadata-invalid")
    return value


def stopped_in_window(value, start, end):
    try:
        if not isinstance(value, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,9})?Z", value):
            return False
        finished = datetime.fromisoformat(value[:-1] + "+00:00").timestamp()
        return start - SKEW <= finished <= end + SKEW
    except ValueError:
        return False


def installed(options, start, end):
    output = run(["docker", "ps", "--all", "--no-trunc", "--filter",
                  "label=com.docker.compose.project=" + options.project, "--filter",
                  "label=com.docker.compose.project.working_dir=" + str(options.app_dir), "--format", "{{.ID}}"])
    identifiers = output.splitlines()
    if len(identifiers) > 128 or len(identifiers) != len(set(identifiers)):
        raise RecoveryError("container-metadata-invalid")
    containers, images = {}, {}
    for identifier in identifiers:
        if not re.fullmatch(r"[a-f0-9]{64}", identifier):
            raise RecoveryError("container-metadata-invalid")
        value = json_metadata(["docker", "inspect", "--format", CONTAINER_FORMAT, identifier],
                              ("project", "working_dir", "service", "image", "running", "exit_code", "oom_killed", "status", "finished_at"))
        if value["project"] != options.project or value["working_dir"] != str(options.app_dir):
            raise RecoveryError("container-scope-mismatch")
        service = value["service"]
        if not isinstance(service, str):
            raise RecoveryError("container-metadata-invalid")
        if service not in WRITERS and service != "auth_database":
            continue
        if service in containers:
            raise RecoveryError("ambiguous-installed-service")
        if type(value["running"]) is not bool or type(value["oom_killed"]) is not bool \
                or type(value["exit_code"]) is not int or value["exit_code"] < 0:
            raise RecoveryError("container-metadata-invalid")
        if value["oom_killed"] or value["exit_code"] == 137:
            raise RecoveryError("writer-not-graceful")
        if service in ("webhook_worker", "auth_database"):
            if not value["running"] or value["status"] != "running":
                raise RecoveryError("required-service-not-running")
        elif value["running"] or value["status"] != "exited" or not stopped_in_window(value["finished_at"], start, end):
            raise RecoveryError("producer-state-ambiguous")
        if service in WRITERS:
            image = value["image"]
            if not isinstance(image, str) or not re.fullmatch(r"sha256:[a-f0-9]{64}", image):
                raise RecoveryError("image-metadata-invalid")
            if image not in images:
                images[image] = json_metadata(["docker", "image", "inspect", "--format", IMAGE_FORMAT, image], ("revision", "source"))
            source = images[image]["source"]
            if images[image]["revision"] != options.failed_source_sha or not isinstance(source, str) \
                    or source.lower() != options.repository.lower():
                raise RecoveryError("installed-source-mismatch")
        containers[service] = (identifier, value)
    if set(containers) != WRITERS | {"auth_database"}:
        raise RecoveryError("installed-services-incomplete")
    return containers


def load_cutover():
    directory = Path(__file__).resolve().parent
    path = directory / "fireguard-webhook-cutover.py"
    if not path.exists():
        path = directory / "webhook-cutover.py"
    try:
        # Avoid generating __pycache__: recovery must not mutate the installation.
        scope = {"__name__": "recovery_cutover_probe", "__file__": str(path)}
        exec(compile(path.read_text(encoding="utf-8"), str(path), "exec"), scope)
        probes = {name: scope[name] for name in ("TRANSPORT_PROBE", "TABLE_PROBE", "COUNT_PROBE")}
        if not all(isinstance(value, str) and value for value in probes.values()):
            raise RecoveryError("probe-unavailable")
        return probes
    except (OSError, SyntaxError, KeyError) as error:
        raise RecoveryError("probe-unavailable") from error


def verify_queues(containers, probes):
    worker = containers["webhook_worker"][0]
    database = containers["auth_database"][0]
    if run(["docker", "exec", worker, "php", "-r", probes["TRANSPORT_PROBE"]]) != "main":
        raise RecoveryError("installed-transport-not-main")
    if run(["docker", "exec", database, "sh", "-c", probes["TABLE_PROBE"]]) != "present":
        raise RecoveryError("auth-queue-unavailable")
    if run(["docker", "exec", database, "sh", "-c", probes["COUNT_PROBE"]]) != "0":
        raise RecoveryError("auth-queue-not-empty")


def remove_verified_lock(app, original):
    descriptor = None
    try:
        if os.name == "posix":
            descriptor = os.open(app, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW)
            if fingerprint(os.fstat(descriptor)) != original[0]:
                raise RecoveryError("lock-state-changed")
            # Pin the parent directory and check the child inode immediately
            # before rmdir. An ordinary mkdir contender can win only afterward.
            if fingerprint(os.stat(LOCK, dir_fd=descriptor, follow_symlinks=False)) != original[1]:
                raise RecoveryError("lock-state-changed")
            os.rmdir(LOCK, dir_fd=descriptor)
        else:
            # The deployed host is Linux; retain local Windows test support.
            if (fingerprint(app.lstat()), fingerprint((app / LOCK).lstat())) != original:
                raise RecoveryError("lock-state-changed")
            os.rmdir(app / LOCK)
    except OSError as error:
        raise RecoveryError("lock-removal-refused") from error
    finally:
        if descriptor is not None:
            os.close(descriptor)


def recover(options):
    start, end = metadata(options)
    original = lock_state(options.app_dir, start, end)
    assert_no_operations()
    containers = installed(options, start, end)
    probes = load_cutover()
    verify_queues(containers, probes)
    # Recheck public runtime evidence and active operations before the final stat.
    if installed(options, start, end) != containers:
        raise RecoveryError("installed-state-changed")
    verify_queues(containers, probes)
    assert_no_operations()
    if lock_state(options.app_dir, start, end) != original:
        raise RecoveryError("lock-state-changed")
    remove_verified_lock(options.app_dir, original)
    return {"outcome": "unlocked", "failed_run_id": options.failed_run_id,
            "failed_source_sha": options.failed_source_sha, "writers": len(WRITERS),
            "transport": "main", "auth_queue_count": 0}


def main(argv=None):
    parser = SafeParser(description=__doc__, allow_abbrev=False)
    parser.add_argument("--app-dir", required=True, type=Path)
    for name in ("project", "failed-source-sha", "failed-run-id", "failed-start", "failed-end", "repository"):
        parser.add_argument("--" + name, required=True)
    try:
        proof = recover(parser.parse_args(argv))
    except RecoveryError as error:
        print(json.dumps({"outcome": "refused", "reason": str(error)}, sort_keys=True))
        return 1
    except Exception:
        print(json.dumps({"outcome": "refused", "reason": "verification-failed"}, sort_keys=True))
        return 1
    print(json.dumps(proof, sort_keys=True))
    return 0


if __name__ == "__main__":
    sys.exit(main())
