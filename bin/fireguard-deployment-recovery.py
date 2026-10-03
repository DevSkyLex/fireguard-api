#!/usr/bin/env python3
"""Explicit, development-only recovery of the reviewed 37080669400 directory mutex.

Controller provenance runs after the unchanged CI/Sonar guard and immutable build.
The host reads only process/container/schema metadata. It never starts writers,
changes databases, reads deployment secrets, recursively removes a lock, or retries
an ambiguous recovery. Ordinary deployment still acquires its lock with mkdir.
"""

import argparse
from contextlib import contextmanager
from datetime import datetime
import hashlib
import json
import os
from pathlib import Path
import posixpath
import re
import signal
import stat
import subprocess
import sys
from typing import Literal, TypedDict
import urllib.request


REPOSITORY = "DevSkyLex/fireguard-api"
CANDIDATE = "37080669400"
FAILED_SHA = "009d93d6c61ed6665d85722efb2a15d8c0dc2666"
APP_DIR = "/srv/apps/fireguard/development/back"
PROJECT = "fireguard-dev-back"
PREFIX = "fireguard-dev-back"
WRITERS = {"app", "async_worker", "webhook_worker", "assistant_worker", "scheduler_worker"}
DEPENDENCIES = {"auth_database", "main_database", "redis", "mercure", "mailpit"}
SHA = re.compile(r"[a-f0-9]{40}\Z")
IMAGE = re.compile(r"ghcr\.io/devskylex/fireguard-api@sha256:[a-f0-9]{64}\Z")
VERSION_FILE = re.compile(r"Version[0-9]{14}\.php\Z")
SCHEMA_PREFIX = {"auth": "DoctrineMigrations\\Auth\\", "main": "DoctrineMigrations\\Main\\"}
ACTIVE = {"queued", "in_progress", "waiting", "pending", "requested"}
POTENTIAL_OWNER = re.compile(r"(?:python.*|ansible.*|sh|bash|dash|zsh|fish|ssh|sshd|scp|sftp.*|rsync|restic|pg_dump|pg_restore|docker|podman|tar)\Z")


class RecoveryBlocked(RuntimeError):
    """A public, fixed diagnostic; never include child stderr, argv or secret values."""


class LockIdentityDiagnostic(TypedDict):
    appDir: Literal["/srv/apps/fireguard/development/back"]
    isDirectory: bool
    isSymlink: bool
    lockUid: int
    processUid: int
    effectiveUid: int | None
    lockGid: int
    processGid: int | None
    effectiveGid: int | None
    mode: int
    modeOctal: str
    groupWritable: bool
    worldWritable: bool
    device: int
    inode: int
    mtimeNs: int
    ctimeNs: int
    empty: bool | None


class LockWindowDiagnostic(TypedDict):
    appDir: Literal["/srv/apps/fireguard/development/back"]
    device: int
    inode: int
    uid: int
    mtimeNs: int
    ctimeNs: int
    expectedStartSeconds: float
    expectedEndSeconds: float


class LockInspectionBlocked(RecoveryBlocked):
    """Only bounded filesystem metadata; never resolve a link or export entry names."""

    def __init__(self, code: Literal["lock-identity-unverified", "historical-lock-not-empty",
                                   "lock-not-from-reviewed-failure-window"],
                 diagnostic: LockIdentityDiagnostic | LockWindowDiagnostic):
        super().__init__(code)
        self.diagnostic = diagnostic

    def __str__(self):
        return super().__str__() + " " + json.dumps(self.diagnostic, sort_keys=True)


def require(condition, code):
    if not condition:
        raise RecoveryBlocked(code)


def timestamp(value):
    try:
        result = datetime.fromisoformat(value.replace("Z", "+00:00"))
        require(result.tzinfo is not None, "invalid-run-timestamp")
        return result.timestamp()
    except (TypeError, ValueError, AttributeError):
        raise RecoveryBlocked("invalid-run-timestamp") from None


def command(argv, *, cwd=None):
    """Capture diagnostics privately; child failures never echo configuration or credentials."""
    try:
        result = subprocess.run(argv, cwd=cwd, capture_output=True, timeout=120, check=False)
    except (OSError, subprocess.TimeoutExpired):
        raise RecoveryBlocked("metadata-command-unavailable") from None
    require(result.returncode == 0, "metadata-command-failed")
    require(len(result.stdout) <= 2 * 1024 * 1024, "metadata-output-too-large")
    return result.stdout.decode("utf-8")


class GitHub:
    def __init__(self, token):
        require(bool(token), "github-credential-unavailable")
        self.token = token

    def get(self, path):
        require(path.startswith("/repos/" + REPOSITORY + "/"), "invalid-github-path")
        request = urllib.request.Request("https://api.github.com" + path, headers={
            "Authorization": "Bearer " + self.token,
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": "2022-11-28",
        })
        try:
            with urllib.request.urlopen(request, timeout=30) as response:
                require(response.status == 200, "github-metadata-unavailable")
                return json.load(response)
        except (OSError, ValueError):
            raise RecoveryBlocked("github-metadata-unavailable") from None

    def pages(self, path, key):
        records = []
        separator = "&" if "?" in path else "?"
        for page in range(1, 101):
            body = self.get(path + separator + "per_page=100&page=" + str(page))
            items = body.get(key)
            require(isinstance(items, list), "github-pagination-invalid")
            records.extend(items)
            if len(items) < 100:
                return records
        raise RecoveryBlocked("github-pagination-incomplete")


def trusted_run(run, sha, workflow):
    return (run.get("event") in {"push", "workflow_dispatch"}
            and run.get("head_branch") == "develop" and run.get("head_sha") == sha
            and run.get("repository", {}).get("full_name") == REPOSITORY
            and run.get("head_repository", {}).get("full_name") == REPOSITORY
            and run.get("path") == ".github/workflows/" + workflow)


def migration_manifest(root, source_sha, run=command):
    """Read the exact verified tree; reject changed/deleted historical definitions and config."""
    require(run(["git", "rev-parse", "HEAD"], cwd=root).strip() == source_sha, "checkout-source-mismatch")
    run(["git", "merge-base", "--is-ancestor", FAILED_SHA, source_sha], cwd=root)
    changes = run(["git", "diff", "--name-status", "--no-renames", FAILED_SHA, source_sha, "--",
                   "migrations/auth", "migrations/main", "config/migrations/auth.yaml", "config/migrations/main.yaml"], cwd=root)
    for line in changes.splitlines():
        fields = line.split("\t")
        require(len(fields) == 2 and fields[0] == "A" and fields[1].startswith("migrations/"),
                "historical-migration-or-config-changed")
    result = {"auth": {}, "main": {}}
    for history in result:
        paths = run(["git", "ls-tree", "-r", "--name-only", source_sha, "--", "migrations/" + history], cwd=root)
        for file in paths.splitlines():
            require(file.startswith("migrations/" + history + "/") and VERSION_FILE.fullmatch(Path(file).name),
                    "migration-manifest-ambiguous")
            contents = run(["git", "show", source_sha + ":" + file], cwd=root)
            version = SCHEMA_PREFIX[history] + Path(file).stem
            result[history][version] = hashlib.sha256(contents.encode()).hexdigest()
        require(bool(result[history]), "migration-history-empty")
    return result


def provenance(env, api, root, run=command):
    require(env.get("GITHUB_EVENT_NAME") == "workflow_dispatch", "manual-recovery-only")
    require(env.get("SOURCE_BRANCH") == "develop" and env.get("DEPLOY_ENVIRONMENT") == "development",
            "development-recovery-only")
    require(env.get("REVIEWED_LOCK_RECOVERY_RUN_ID") == CANDIDATE, "unreviewed-failure-run")
    require(env.get("GITHUB_REPOSITORY") == REPOSITORY, "repository-mismatch")
    require(not env.get("IMAGE_INPUT") and env.get("RESET_DEVELOPMENT_FIXTURES") == "false",
            "forward-recovery-without-fixture-reset-required")
    source = env.get("SOURCE_SHA", "")
    image = env.get("IMAGE_REF", "")
    current_id = env.get("GITHUB_RUN_ID", "")
    ci_id = env.get("VERIFIED_CI_RUN_ID", "")
    require(SHA.fullmatch(source) and IMAGE.fullmatch(image), "source-or-image-invalid")
    require(current_id.isdecimal() and ci_id.isdecimal() and current_id != CANDIDATE,
            "verified-run-identifiers-required")
    base = "/repos/" + REPOSITORY
    require(api.get(base + "/git/ref/heads/develop").get("object", {}).get("sha") == source,
            "source-no-longer-branch-tip")
    current = api.get(base + "/actions/runs/" + current_id)
    require(trusted_run(current, source, "deploy-vps.yml") and current.get("event") == "workflow_dispatch"
            and current.get("status") == "in_progress", "current-deployment-provenance-invalid")
    old = api.get(base + "/actions/runs/" + CANDIDATE)
    require(trusted_run(old, FAILED_SHA, "deploy-vps.yml") and old.get("event") == "workflow_dispatch"
            and old.get("status") == "completed" and old.get("conclusion") == "failure"
            and old.get("run_attempt") == 1, "reviewed-failure-provenance-invalid")
    old_jobs = api.pages(base + "/actions/runs/" + CANDIDATE + "/attempts/1/jobs", "jobs")
    deploy_jobs = [job for job in old_jobs if job.get("name") == "Deploy to VPS"]
    require(len(deploy_jobs) == 1, "reviewed-failure-job-ambiguous")
    job = deploy_jobs[0]
    require(job.get("status") == "completed" and job.get("conclusion") == "failure",
            "reviewed-failure-job-not-finished")
    failed_steps = [step for step in job.get("steps", []) if step.get("name") == "Run Ansible deployment"]
    require(len(failed_steps) == 1 and failed_steps[0].get("conclusion") == "failure"
            and failed_steps[0].get("status") == "completed", "reviewed-ansible-failure-unverified")
    ci = api.get(base + "/actions/runs/" + ci_id)
    require(trusted_run(ci, source, "ci.yml") and ci.get("status") == "completed"
            and ci.get("conclusion") == "success", "verified-ci-provenance-invalid")
    attempt = ci.get("run_attempt")
    require(isinstance(attempt, int) and attempt > 0, "verified-ci-attempt-invalid")
    ci_jobs = api.pages(base + "/actions/runs/" + ci_id + "/attempts/" + str(attempt) + "/jobs", "jobs")
    gates = [item for item in ci_jobs if item.get("name") == "SonarQube Quality Gate (fireguard-api-develop)"]
    require(len(gates) == 1 and gates[0].get("status") == "completed"
            and gates[0].get("conclusion") == "success", "sonar-gate-not-successful")
    for name in ["Run SonarQube scan", "Enforce SonarQube quality gate"]:
        steps = [step for step in gates[0].get("steps", []) if step.get("name") == name]
        require(len(steps) == 1 and steps[0].get("status") == "completed"
                and steps[0].get("conclusion") == "success", "sonar-step-not-successful")
    runs = api.pages(base + "/actions/workflows/deploy-vps.yml/runs?branch=develop", "workflow_runs")
    require(not any(str(item.get("id")) != current_id and item.get("status") in ACTIVE for item in runs),
            "concurrent-development-deployment")
    started, completed = timestamp(job.get("started_at")), timestamp(job.get("completed_at"))
    require(started < completed, "reviewed-failure-window-invalid")
    return {"format": 1, "repository": REPOSITORY, "candidateRunId": CANDIDATE, "failedSourceSha": FAILED_SHA,
            "currentRunId": current_id, "sourceSha": source, "image": image, "appDir": APP_DIR,
            "project": PROJECT, "volumePrefix": PREFIX, "lockWindow": [started, completed],
            "migrations": migration_manifest(root, source, run)}


def validate_proof(proof, *, app_dir, project, prefix):
    require(proof.get("format") == 1 and proof.get("repository") == REPOSITORY
            and proof.get("candidateRunId") == CANDIDATE and proof.get("failedSourceSha") == FAILED_SHA,
            "recovery-proof-invalid")
    require(app_dir == proof.get("appDir") == APP_DIR and project == proof.get("project") == PROJECT
            and prefix == proof.get("volumePrefix") == PREFIX, "installation-identity-mismatch")
    require(SHA.fullmatch(proof.get("sourceSha", "")) and IMAGE.fullmatch(proof.get("image", "")),
            "recovery-source-or-image-invalid")
    require(isinstance(proof.get("currentRunId"), str) and proof["currentRunId"].isdecimal()
            and proof["currentRunId"] != CANDIDATE, "current-recovery-run-invalid")
    window = proof.get("lockWindow")
    require(isinstance(window, list) and len(window) == 2 and all(type(item) in {float, int} for item in window)
            and window[0] < window[1], "recovery-window-invalid")
    manifest = proof.get("migrations")
    require(isinstance(manifest, dict) and set(manifest) == {"auth", "main"}, "recovery-migrations-invalid")
    for history, versions in manifest.items():
        require(isinstance(versions, dict) and bool(versions), "recovery-migrations-invalid")
        for version, digest in versions.items():
            require(version.startswith(SCHEMA_PREFIX[history])
                    and VERSION_FILE.fullmatch(version.rsplit("\\", 1)[1] + ".php")
                    and isinstance(digest, str) and re.fullmatch(r"[a-f0-9]{64}", digest), "recovery-migrations-invalid")


CONTAINER_FORMAT = ('{"id":{{json .Id}},"running":{{json .State.Running}},'
                    '"restarting":{{json .State.Restarting}},"status":{{json .State.Status}},'
                    '"project":{{json (index .Config.Labels "com.docker.compose.project")}},'
                    '"service":{{json (index .Config.Labels "com.docker.compose.service")}},'
                    '"oneoff":{{json (index .Config.Labels "com.docker.compose.oneoff")}},'
                    '"workingDir":{{json (index .Config.Labels "com.docker.compose.project.working_dir")}},'
                    '"mounts":[{{range $i,$m := .Mounts}}{{if $i}},{{end}}'
                    '{"name":{{json $m.Name}},"source":{{json $m.Source}}}{{end}}]}')
IMAGE_FORMAT = ('{"digests":{{json .RepoDigests}},'
                '"source":{{json (index .Config.Labels "org.opencontainers.image.source")}},'
                '"revision":{{json (index .Config.Labels "org.opencontainers.image.revision")}}}')
IMAGE_MANIFEST_PHP = r'''
$result = [];
foreach (['auth' => 'DoctrineMigrations\\Auth\\', 'main' => 'DoctrineMigrations\\Main\\'] as $history => $namespace) {
  $versions = [];
  foreach (glob('/var/www/html/migrations/' . $history . '/Version*.php') as $file) {
    $versions[$namespace . basename($file, '.php')] = hash_file('sha256', $file);
  }
  $result[$history] = $versions;
}
echo json_encode($result, JSON_THROW_ON_ERROR);
'''


def source_path(value):
    require(isinstance(value, str) and value.startswith("/") and "\x00" not in value,
            "mount-source-path-unverified")
    return posixpath.normpath("/" + value.lstrip("/"))


def paths_overlap(left, right):
    """Compare complete path components in either direction; a parent bind contains child storage."""
    return left == right or left.startswith(right.rstrip("/") + "/") or right.startswith(left.rstrip("/") + "/")


def check_containers(containers):
    own = [item for item in containers if item.get("project") == PROJECT]
    storage_sources = {APP_DIR}
    for item in own:
        require(isinstance(item.get("mounts"), list), "container-state-ambiguous")
        for mount in item["mounts"]:
            require(isinstance(mount, dict), "container-mount-state-ambiguous")
            storage_sources.add(source_path(mount.get("source")))
    for item in containers:
        require(isinstance(item.get("running"), bool) and isinstance(item.get("restarting"), bool)
                and isinstance(item.get("mounts"), list), "container-state-ambiguous")
        active = item["running"] or item["restarting"]
        mounts = item["mounts"]
        shared = any(str(mount.get("name", "")).startswith(PREFIX + "_")
                     or any(paths_overlap(source_path(mount.get("source")), owned_source) for owned_source in storage_sources)
                     for mount in mounts)
        require(not (active and shared and item.get("project") != PROJECT), "foreign-writer-mounts-development-storage")
        if item.get("project") != PROJECT:
            continue
        require(item.get("workingDir") == APP_DIR, "compose-installation-identity-mismatch")
        require(not (active and (item.get("oneoff") != "False" or item.get("service") not in DEPENDENCIES)),
                "writer-or-oneoff-active")
    for service in WRITERS:
        matches = [item for item in own if item.get("service") == service and item.get("oneoff") == "False"]
        require(len(matches) == 1 and matches[0].get("status") == "exited", "stopped-writer-state-unverified")
    databases = {}
    for history in ["auth", "main"]:
        service = history + "_database"
        matches = [item for item in own if item.get("service") == service and item.get("oneoff") == "False"]
        require(len(matches) == 1 and matches[0].get("running")
                and any(mount.get("name") == PREFIX + "_" + history + "_database_data" for mount in matches[0]["mounts"]),
                "database-installation-identity-unverified")
        databases[history] = matches[0]["id"]
    return databases


def process_ancestors(processes, current_pid):
    by_id = {item["pid"]: item for item in processes}
    ancestors, pid = set(), current_pid
    while pid:
        require(pid in by_id and pid not in ancestors, "own-process-ancestry-unverified")
        ancestors.add(pid)
        pid = by_id[pid]["ppid"]
    return ancestors


def check_processes(processes, current_pid, current_uid):
    ancestors = process_ancestors(processes, current_pid)
    for item in processes:
        if item["pid"] in ancestors or item["uid"] != current_uid:
            continue
        paths = [item["cwd"]] + item["fds"]
        scoped = any(path == APP_DIR or path.startswith(APP_DIR + "/") for path in paths)
        require(not scoped and not POTENTIAL_OWNER.fullmatch(item["comm"]), "concurrent-host-owner-or-ambiguous-process")


@contextmanager
def defer_cancellation():
    """Defer catchable termination across the two syscalls, then deliver it with the new lock held.

    SIGKILL/host loss cannot be masked; directory identity must be reviewed again
    after either. No claim of transactional filesystem recovery is made.
    """
    require(hasattr(signal, "pthread_sigmask"), "signal-mask-unavailable")
    previous = signal.pthread_sigmask(signal.SIG_BLOCK, {signal.SIGINT, signal.SIGTERM, signal.SIGHUP})
    try:
        yield
    finally:
        signal.pthread_sigmask(signal.SIG_SETMASK, previous)


class Host:
    def __init__(self, run=command):
        self.run = run

    def lock_identity(self):
        app, lock = Path(APP_DIR), Path(APP_DIR) / ".fireguard-operation.lock"
        require(app.resolve(strict=True) == app and not any(path.is_symlink() for path in [app, *app.parents]),
                "installation-path-not-canonical")
        value = lock.lstat()
        process_uid = os.getuid()
        diagnostic: LockIdentityDiagnostic = {
            "appDir": APP_DIR, "isDirectory": stat.S_ISDIR(value.st_mode), "isSymlink": stat.S_ISLNK(value.st_mode),
            "lockUid": value.st_uid, "processUid": process_uid,
            "effectiveUid": os.geteuid() if hasattr(os, "geteuid") else None,
            "lockGid": value.st_gid, "processGid": os.getgid() if hasattr(os, "getgid") else None,
            "effectiveGid": os.getegid() if hasattr(os, "getegid") else None,
            "mode": stat.S_IMODE(value.st_mode), "modeOctal": format(stat.S_IMODE(value.st_mode), "04o"),
            "groupWritable": bool(value.st_mode & 0o020), "worldWritable": bool(value.st_mode & 0o002),
            "device": value.st_dev, "inode": value.st_ino, "mtimeNs": value.st_mtime_ns, "ctimeNs": value.st_ctime_ns,
            "empty": None,
        }
        if not (stat.S_ISDIR(value.st_mode) and not stat.S_ISLNK(value.st_mode) and value.st_uid == process_uid
                and not value.st_mode & 0o022):
            raise LockInspectionBlocked("lock-identity-unverified", diagnostic)
        if any(lock.iterdir()):
            diagnostic["empty"] = False
            raise LockInspectionBlocked("historical-lock-not-empty", diagnostic)
        return {"device": value.st_dev, "inode": value.st_ino, "uid": value.st_uid,
                "mtimeNs": value.st_mtime_ns, "ctimeNs": value.st_ctime_ns}

    def processes(self):
        records = []
        for path in Path("/proc").iterdir():
            if not path.name.isdecimal():
                continue
            try:
                fields = dict(line.split(":", 1) for line in (path / "status").read_text().splitlines() if ":" in line)
                uid = int(fields["Uid"].split()[0])
                record = {"pid": int(path.name), "ppid": int(fields["PPid"]), "uid": uid,
                          "comm": fields["Name"].strip(), "cwd": "", "fds": []}
                record["kernelThread"] = fields.get("Kthread", "").strip() == "1"
                records.append(record)
            except FileNotFoundError:
                require(not path.exists(), "process-metadata-raced")
            except (OSError, KeyError, ValueError):
                raise RecoveryBlocked("process-metadata-permission-or-format-unavailable") from None
        ancestors = process_ancestors(records, os.getpid())
        for record in records:
            if record["uid"] != os.getuid() or record["pid"] in ancestors or record["kernelThread"]:
                continue
            path = Path("/proc") / str(record["pid"])
            try:
                record["cwd"] = os.readlink(path / "cwd")
                record["fds"] = [os.readlink(fd) for fd in (path / "fd").iterdir()]
            except FileNotFoundError:
                require(not path.exists(), "process-metadata-raced")
            except OSError:
                raise RecoveryBlocked("process-metadata-permission-unavailable") from None
        return records

    def containers(self):
        ids = self.run(["docker", "ps", "--all", "--quiet", "--no-trunc"]).splitlines()
        result = []
        for identifier in ids:
            require(re.fullmatch(r"[a-f0-9]{64}", identifier), "docker-container-id-invalid")
            result.append(json.loads(self.run(["docker", "inspect", "--format", CONTAINER_FORMAT, identifier])))
        return result

    def image_manifest(self, proof):
        # Uses Docker's existing private registry login, never reads its credential file.
        # Image cache and a network-isolated, read-only inspection container only.
        self.run(["docker", "pull", proof["image"]])
        identity = json.loads(self.run(["docker", "image", "inspect", "--format", IMAGE_FORMAT, proof["image"]]))
        require(proof["image"] in (identity.get("digests") or [])
                and identity.get("revision") == proof["sourceSha"]
                and str(identity.get("source", "")).lower() == "https://github.com/" + REPOSITORY.lower(),
                "immutable-image-provenance-mismatch")
        return json.loads(self.run(["docker", "run", "--rm", "--network", "none", "--read-only", "--entrypoint", "php",
                                    proof["image"], "-r", IMAGE_MANIFEST_PHP]))

    def history(self, container, history):
        require(history in SCHEMA_PREFIX and re.fullmatch(r"[a-f0-9]{64}", container), "database-query-identity-invalid")
        sql = ("BEGIN READ ONLY; SELECT version FROM doctrine_migration_versions_" + history + " ORDER BY version; COMMIT;")
        # Credentials stay inside the existing DB container; neither environment nor rows are exported.
        shell = 'exec psql -v ON_ERROR_STOP=1 -qAt -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "$1"'
        output = self.run(["docker", "exec", "-i", container, "sh", "-c", shell, "--", sql])
        versions = output.splitlines()
        require(bool(versions) and len(versions) == len(set(versions)), "database-history-empty-or-duplicate")
        return versions

    def replace_empty_lock(self):
        lock = Path(APP_DIR) / ".fireguard-operation.lock"
        with defer_cancellation():
            os.rmdir(lock)
            os.mkdir(lock)  # The ordinary atomic acquire, once. Never delete or retry a competing owner's new directory.


def recover(proof, host, *, app_dir, project, prefix, current_pid, current_uid):
    validate_proof(proof, app_dir=app_dir, project=project, prefix=prefix)
    identity = host.lock_identity()
    started, completed = proof["lockWindow"]
    if not all(started <= identity[field] / 1e9 <= completed for field in ["mtimeNs", "ctimeNs"]):
        diagnostic: LockWindowDiagnostic = {"appDir": APP_DIR, "device": identity["device"], "inode": identity["inode"],
                                            "uid": identity["uid"], "mtimeNs": identity["mtimeNs"], "ctimeNs": identity["ctimeNs"],
                                            "expectedStartSeconds": started, "expectedEndSeconds": completed}
        raise LockInspectionBlocked("lock-not-from-reviewed-failure-window", diagnostic)
    check_processes(host.processes(), current_pid, current_uid)
    databases = check_containers(host.containers())
    require(host.image_manifest(proof) == proof["migrations"], "image-migration-manifest-mismatch")
    for history, container in databases.items():
        executed = host.history(container, history)
        require(set(executed).issubset(proof["migrations"][history]), "executed-migration-unavailable-in-forward-image")
    check_processes(host.processes(), current_pid, current_uid)
    require(check_containers(host.containers()) == databases, "database-identity-changed-during-review")
    require(host.lock_identity() == identity, "lock-identity-changed-during-review")
    host.replace_empty_lock()
    return {"candidateRunId": CANDIDATE, "currentRunId": proof["currentRunId"], "sourceSha": proof["sourceSha"],
            "result": "reviewed-lock-reacquired-awaiting-rollout"}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="mode", required=True)
    controller = commands.add_parser("provenance")
    controller.add_argument("--output", required=True, type=Path)
    controller.add_argument("--checkout", type=Path, default=Path.cwd())
    remote = commands.add_parser("acquire-reviewed-lock")
    remote.add_argument("--proof", required=True, type=Path)
    remote.add_argument("--app-dir", required=True)
    remote.add_argument("--project", required=True)
    remote.add_argument("--volume-prefix", required=True)
    args = parser.parse_args()
    if args.mode == "provenance":
        proof = provenance(os.environ, GitHub(os.environ.get("GH_TOKEN")), args.checkout)
        args.output.parent.mkdir(mode=0o700, parents=True, exist_ok=True)
        with args.output.open("x", encoding="utf-8") as output:
            os.chmod(args.output, 0o600)
            json.dump(proof, output, sort_keys=True)
        print("Reviewed development recovery provenance verified; no VPS operation executed.")
    else:
        proof = json.loads(args.proof.read_text())
        result = recover(proof, Host(), app_dir=args.app_dir, project=args.project, prefix=args.volume_prefix,
                         current_pid=os.getpid(), current_uid=os.getuid())
        print(json.dumps(result, sort_keys=True))


if __name__ == "__main__":
    try:
        main()
    except RecoveryBlocked as error:
        print("Reviewed recovery blocked: " + str(error), file=sys.stderr)
        raise SystemExit(78)
    except (OSError, ValueError, TypeError, KeyError):
        print("Reviewed recovery blocked: metadata-unavailable-or-ambiguous", file=sys.stderr)
        raise SystemExit(78)
