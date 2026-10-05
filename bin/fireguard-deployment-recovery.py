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
from enum import Enum
import errno
import hashlib
import importlib.util
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
import uuid


REPOSITORY = "DevSkyLex/fireguard-api"
CANDIDATE = "37080669400"
FAILED_SHA = "009d93d6c61ed6665d85722efb2a15d8c0dc2666"
APP_DIR = "/srv/apps/fireguard/development/back"
PROJECT = "fireguard-dev-back"
PREFIX = "fireguard-dev-back"
WRITERS = {"app", "async_worker", "webhook_worker", "assistant_worker", "scheduler_worker"}
REVIEWED_ABSENT_WRITERS = {"async_worker", "scheduler_worker", "webhook_worker"}
REVIEWED_RETAINED_STORAGE = {
    "app": (("app_var", "/var/www/html/var", True), ("jwt_keys", "/var/www/html/config/jwt", False),
            ("geoip_data", "/var/lib/fireguard/geoip", False)),
    "assistant_worker": (("app_var", "/var/www/html/var", True), ("jwt_keys", "/var/www/html/config/jwt", False)),
    "auth_database": (("auth_database_data", "/var/lib/postgresql/data", True),),
    "main_database": (("main_database_data", "/var/lib/postgresql/data", True),),
    "redis": (("redis_data", "/data", True),),
}
DEPENDENCIES = {"auth_database", "main_database", "redis", "mercure", "mailpit"}
SHA = re.compile(r"[a-f0-9]{40}\Z")
IMAGE = re.compile(r"ghcr\.io/devskylex/fireguard-api@sha256:[a-f0-9]{64}\Z")
VERSION_FILE = re.compile(r"Version[0-9]{14}\.php\Z")
SCHEMA_PREFIX = {"auth": "DoctrineMigrations\\Auth\\", "main": "DoctrineMigrations\\Main\\"}
ACTIVE = {"queued", "in_progress", "waiting", "pending", "requested"}
POTENTIAL_OWNER = re.compile(r"(?:python.*|ansible.*|sh|bash|dash|zsh|fish|ssh|sshd|scp|sftp.*|rsync|restic|pg_dump|pg_restore|docker|podman|tar)\Z")
REVIEWED_LEGACY_LOCK = {"device": 2049, "inode": 3149773, "uid": 1001, "gid": 1001, "mode": 0o775,
                        "mtimeNs": 1790986150159681219, "ctimeNs": 1790986150159681219}
REVIEWED_NAMESPACE_VALUES = (
    (1001, 1001, 3203515, 0o755, 1790986201399539338),
    (0, 0, 3203513, 0o755, 1788966352298447425),
    (1000, 1000, 3161822, 0o775, 1788966352298447425),
    (1000, 1000, 558852, 0o755, 1790595925944593762),
    (1000, 1000, 1747, 0o755, 1779019167958849589),
    (0, 0, 2, 0o755, 1788965698847000000),
)
REVIEWED_NAMESPACE = [{"depth": depth, "status": "available", "uid": uid, "gid": gid,
                       "mode": mode, "device": 2049, "inode": inode, "mtimeNs": stamp,
                       "ctimeNs": stamp, "isDirectory": True, "isSymlink": False}
                      for depth, (uid, gid, inode, mode, stamp) in enumerate(REVIEWED_NAMESPACE_VALUES)]
ISOLATION_API_VERSION = "1.45"
STORAGE_ROOT = "/var/lib/docker"
VOLUME_NAME = re.compile(r"[a-zA-Z0-9][a-zA-Z0-9_.-]*\Z")
DOCKER_ID = re.compile(r"[a-f0-9]{64}\Z")
DOCKER_IMAGE_ID = re.compile(r"sha256:[a-f0-9]{64}\Z")
REVIEWED_VOLUME_MOUNTS = [
    {"Name": "back_app_var", "Destination": "/var/www/html/var", "RW": True},
    {"Name": "back_jwt_keys", "Destination": "/var/www/html/config/jwt", "RW": False},
    {"Name": "back_geoip_data", "Destination": "/var/lib/fireguard/geoip", "RW": False},
]


class RecoveryBlocked(RuntimeError):
    """A public, fixed diagnostic; never include child stderr, argv or secret values."""


WriterStatus = Literal["created", "running", "paused", "restarting", "removing", "exited", "dead", "unknown"]
WRITER_STATUSES: tuple[WriterStatus, ...] = ("created", "running", "paused", "restarting", "removing", "exited", "dead", "unknown")


class WriterStateDiagnostic(TypedDict):
    service: Literal["app", "async_worker", "webhook_worker", "assistant_worker", "scheduler_worker"]
    matchCount: int
    statusCounts: dict[WriterStatus, int]
    countsTruncated: bool


class RetainedStorageDiagnostic(TypedDict):
    service: Literal["app", "assistant_worker", "auth_database", "main_database", "redis"]
    expectedMountCount: int
    actualMountCount: int
    countsTruncated: bool
    mismatchFields: list[Literal["shape", "type", "name", "source", "destination", "rw", "missing", "extra"]]


class NamespaceNodeFields(TypedDict, total=False):
    uid: int
    gid: int
    mode: int
    device: int
    inode: int
    mtimeNs: int
    ctimeNs: int
    isDirectory: bool
    isSymlink: bool


class NamespaceNodeDiagnostic(NamespaceNodeFields):
    depth: int
    status: Literal["available", "unavailable"]


class AuthorityPeerCount(TypedDict):
    identity: int
    processCount: int


class NamespacePeerFields(TypedDict, total=False):
    peerCount: int
    uidCounts: list[AuthorityPeerCount]
    writableGidCounts: list[AuthorityPeerCount]


class NamespacePeerDiagnostic(NamespacePeerFields):
    status: Literal["available", "unavailable"]
    reason: Literal["none", "namespace_metadata", "peer_metadata", "bounded_limit"]


class NamespaceRefusalDiagnostic(TypedDict, total=False):
    namespaceRefusalDepth: int
    namespaceUid: int
    namespaceGid: int
    namespaceMode: int
    namespaceDevice: int
    namespaceInode: int
    namespaceChain: list[NamespaceNodeDiagnostic]
    namespacePeerCounts: NamespacePeerDiagnostic


class LockIdentityDiagnostic(NamespaceRefusalDiagnostic):
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


CONTAINER_FORMAT = ('{"id":{{json .ID}},"running":{{json .State.Running}},'
                    '"restarting":{{json .State.Restarting}},"status":{{json .State.Status}},'
                    '"project":{{json (index .Config.Labels "com.docker.compose.project")}},'
                    '"service":{{json (index .Config.Labels "com.docker.compose.service")}},'
                    '"oneoff":{{json (index .Config.Labels "com.docker.compose.oneoff")}},'
                    '"workingDir":{{json (index .Config.Labels "com.docker.compose.project.working_dir")}},'
                    '"mounts":[{{range $i,$m := .Mounts}}{{if $i}},{{end}}'
                    '{"name":{{json $m.Name}},"source":{{json $m.Source}},"type":{{json $m.Type}},'
                    '"destination":{{json $m.Destination}},"rw":{{json $m.RW}}}{{end}}]}')
IMAGE_FORMAT = ('{"id":{{json .ID}},"volumesEmpty":{{eq (len .Config.Volumes) 0}},"digests":{{json .RepoDigests}},'
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


def writer_state_diagnostics(own) -> list[WriterStateDiagnostic]:
    result = []
    for service in sorted(WRITERS):
        matches = [item for item in own if item.get("service") == service and item.get("oneoff") == "False"]
        counts: dict[WriterStatus, int] = {status: 0 for status in WRITER_STATUSES}
        for item in matches:
            status = item.get("status")
            counts[status if type(status) is str and status in counts else "unknown"] += 1
        result.append({"service": service, "matchCount": min(len(matches), 32768),
                       "statusCounts": {status: min(count, 32768) for status, count in counts.items()},
                       "countsTruncated": len(matches) > 32768})
    return result


def retained_storage_diagnostic(service, mounts, expected) -> RetainedStorageDiagnostic:
    keys = ("name", "source", "type", "destination", "rw")
    wanted = {mount[0]: mount for mount in expected}
    counts = dict.fromkeys(wanted, 0)
    mismatch = set()
    for mount in mounts:
        for key in keys:
            if type(mount.get(key)) is not (bool if key == "rw" else str):
                mismatch.update(("shape", key))
        name = mount.get("name")
        if type(name) is not str or name not in wanted:
            mismatch.update(("name", "extra"))
            continue
        counts[name] += 1
        for key, value in zip(keys, wanted[name]):
            if mount.get(key) != value:
                mismatch.add(key)
    if any(count == 0 for count in counts.values()):
        mismatch.update(("name", "missing"))
    if any(count > 1 for count in counts.values()):
        mismatch.update(("name", "extra"))
    return {"service": service, "expectedMountCount": min(len(expected), 32768), "actualMountCount": min(len(mounts), 32768),
            "countsTruncated": len(expected) > 32768 or len(mounts) > 32768,
            "mismatchFields": [field for field in ("shape", "type", "name", "source", "destination", "rw", "missing", "extra")
                               if field in mismatch]}


def require_retained_storage(condition, service, mounts, expected):
    if not condition:
        raise RecoveryBlocked("retained-storage-contract-unverified "
                              + json.dumps(retained_storage_diagnostic(service, mounts, expected), sort_keys=True))


def retained_storage_identity(own):
    """The reviewed Compose contract, never an allowlist inferred from runtime mounts."""
    result = {}
    for service, contract in REVIEWED_RETAINED_STORAGE.items():
        matches = [item for item in own if item.get("service") == service and item.get("oneoff") == "False"]
        require(len(matches) == 1, "retained-storage-service-unverified")
        item = matches[0]
        require(type(item.get("id")) is str and DOCKER_ID.fullmatch(item["id"])
                and type(item.get("status")) is str and item["status"] in WRITER_STATUSES[:-1], "retained-storage-identity-unverified")
        expected = tuple(sorted((PREFIX + "_" + suffix, storage_volume_path(PREFIX + "_" + suffix), "volume", destination, rw)
                                for suffix, destination, rw in contract))
        require_retained_storage(all(type(mount.get(key)) is str for mount in item["mounts"] for key in ("name", "source", "type", "destination"))
                                 and all(type(mount.get("rw")) is bool for mount in item["mounts"]), service, item["mounts"], expected)
        mounted = tuple(sorted((mount["name"], mount["source"], mount["type"], mount["destination"], mount["rw"])
                               for mount in item["mounts"]))
        require_retained_storage(mounted == expected, service, item["mounts"], expected)
        result[service] = (item["id"], item.get("status"), item["running"], item["restarting"], mounted)
    return result


def reviewed_writer_identity(containers):
    result = {}
    for service in sorted(WRITERS):
        matches = [item for item in containers if item.get("project") == PROJECT
                   and item.get("service") == service and item.get("oneoff") == "False"]
        if not matches:
            result[service] = None
            continue
        require(len(matches) == 1 and type(matches[0].get("id")) is str and DOCKER_ID.fullmatch(matches[0]["id"]),
                "stopped-writer-identity-unverified")
        result[service] = (matches[0]["id"], matches[0].get("status"))
    return result


def check_containers(containers, *, allow_reviewed_absent=False):
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
    absent = False
    for service in WRITERS:
        matches = [item for item in own if item.get("service") == service and item.get("oneoff") == "False"]
        if allow_reviewed_absent and service in REVIEWED_ABSENT_WRITERS and not any(item.get("service") == service for item in own):
            absent = True
            continue
        if not (len(matches) == 1 and matches[0].get("status") == "exited"):
            raise RecoveryBlocked("stopped-writer-state-unverified "
                                  + json.dumps({"writers": writer_state_diagnostics(own)}, sort_keys=True))
    if absent:
        retained_storage_identity(own)
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


def check_peer_credentials(item, current_uid, group_gid):
    fields = [item.get("uids"), item.get("gids"), item.get("groups")]
    require(all(isinstance(values, list) and all(type(value) is int and value >= 0 for value in values) for values in fields)
            and len(fields[0]) == len(fields[1]) == 4 and item["uid"] == fields[0][0], "peer-group-metadata-unverified")
    if fields[0] != [current_uid] * 4:
        require(current_uid not in fields[0] and group_gid not in fields[1] + fields[2], "peer-group-writer-or-ambiguous-process")


def check_processes(processes, current_pid, current_uid, *, group_gid=None, isolated_peers=None, peer_reader=None):
    ancestors = process_ancestors(processes, current_pid)
    for item in processes:
        if item["pid"] in ancestors:
            continue
        if group_gid is not None:
            selected = (isolated_peers or {}).get(item["pid"])
            if isolated_peers is not None:
                authority = any(1000 in item[key] for key in ("uids", "gids", "groups"))
                require(not authority or selected is not None, "unproved-namespace-authority-peer-before-owner-guard")
            if selected is None:
                check_peer_credentials(item, current_uid, group_gid)
            else:
                require(selected["uids"] == [1000] * 4 and all(item[key] == selected[key] for key in ("uids", "gids", "groups"))
                        and item["ppid"] == selected["ppid"] and peer_reader(item["pid"]) == selected,
                        "isolated-peer-identity-changed-before-owner-guard")
        if item["uid"] != current_uid:
            continue
        paths = [item["cwd"]] + item["fds"]
        scoped = any(path == APP_DIR or path.startswith(APP_DIR + "/") for path in paths)
        require(not scoped and not POTENTIAL_OWNER.fullmatch(item["comm"]), "concurrent-host-owner-or-ambiguous-process")


def process_status_records(*, include_names=True, bounded=False):
    records, inspected = [], 0
    for path in Path("/proc").iterdir():
        if not path.name.isdecimal():
            continue
        inspected += 1
        if bounded:
            require(inspected <= 32768, "diagnostic-peer-limit")
        try:
            if bounded:
                with (path / "status").open("r", encoding="utf-8") as source:
                    status = source.read(512 * 1024 + 1)
                require(len(status) <= 512 * 1024, "diagnostic-peer-limit")
            else:
                status = (path / "status").read_text()
            lines = status.splitlines()
            require(all(sum(line.startswith(key + ":") for line in lines) == 1 for key in ["Uid", "Gid", "Groups"] + (["PPid"] if bounded else [])),
                    "process-credentials-unverified")
            selected = {"Uid", "Gid", "Groups", "PPid", "Kthread"} | ({"Name"} if include_names else set())
            fields = {key: value for line in lines if ":" in line for key, value in [line.split(":", 1)] if key in selected}
            credentials = {key: [int(value) for value in fields[source].split()] for key, source in
                           [("uids", "Uid"), ("gids", "Gid"), ("groups", "Groups")]}
            require(len(credentials["uids"]) == len(credentials["gids"]) == 4
                    and all(value >= 0 for values in credentials.values() for value in values), "process-credentials-unverified")
            record = {"pid": int(path.name), "ppid": int(fields["PPid"]), "uid": credentials["uids"][0], **credentials,
                      "kernelThread": fields.get("Kthread", "").strip() == "1"}
            if include_names:
                record.update(comm=fields["Name"].strip(), cwd="", fds=[])
            records.append(record)
        except FileNotFoundError:
            require(not path.exists(), "process-metadata-raced")
        except (OSError, KeyError, ValueError):
            raise RecoveryBlocked("process-metadata-permission-or-format-unavailable") from None
    return records


def privileged_process_records():
    """Load only the installed protected client; staged source supplies its digest."""
    helper = Path(__file__).resolve()
    expected = helper.parent / "fireguard-deployment-process-reader.py"
    installed = Path("/usr/local/libexec/fireguard-deployment-process-reader.py")

    def identity(node):
        return (node.st_dev, node.st_ino, node.st_uid, node.st_gid, node.st_mode,
                node.st_size, node.st_mtime_ns, node.st_ctime_ns)

    def source_bytes(path, owner):
        before = path.lstat()
        require(stat.S_ISREG(before.st_mode) and before.st_uid == owner and not before.st_mode & 0o022,
                "process-reader-client-source-unverified")
        descriptor = os.open(path, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC)
        with os.fdopen(descriptor, "rb") as stream:
            require(identity(os.fstat(stream.fileno())) == identity(before), "process-reader-client-source-changed")
            contents = stream.read(1024 * 1024 + 1)
            require(0 < len(contents) <= 1024 * 1024, "process-reader-client-source-unverified")
            require(identity(os.fstat(stream.fileno())) == identity(path.lstat()) == identity(before),
                    "process-reader-client-source-changed")
        return contents

    try:
        for parent in installed.parents:
            node = parent.lstat()
            require(stat.S_ISDIR(node.st_mode) and node.st_uid == 0 and not node.st_mode & 0o022,
                    "process-reader-installed-path-unverified")
        contents = source_bytes(installed, 0)
        require(hashlib.sha256(contents).digest() == hashlib.sha256(source_bytes(expected, helper.stat().st_uid)).digest(),
                "process-reader-installed-version-mismatch")
        spec = importlib.util.spec_from_file_location("_fireguard_deployment_process_reader", installed)
        require(spec is not None, "process-reader-client-unavailable")
        module = importlib.util.module_from_spec(spec)
        # This code is root-owned and version-verified. Staged bytes are never executed.
        exec(compile(contents, str(installed), "exec"), module.__dict__)
        return module.read_snapshot(os.getpid(), os.getuid())
    except RecoveryBlocked:
        raise
    except Exception:
        # No private records, process identities or exception text reach public job logs.
        raise RecoveryBlocked("process-reader-snapshot-unavailable") from None


def namespace_node_metadata(path, depth, node=None) -> NamespaceNodeDiagnostic:
    try:
        node = path.lstat() if node is None else node
        return {"depth": depth, "status": "available", "uid": node.st_uid, "gid": node.st_gid,
                "mode": stat.S_IMODE(node.st_mode), "device": node.st_dev, "inode": node.st_ino,
                "mtimeNs": node.st_mtime_ns, "ctimeNs": node.st_ctime_ns,
                "isDirectory": stat.S_ISDIR(node.st_mode), "isSymlink": stat.S_ISLNK(node.st_mode)}
    except (OSError, AttributeError, TypeError, ValueError):
        return {"depth": depth, "status": "unavailable"}


def namespace_peer_counts(chain: list[NamespaceNodeDiagnostic]) -> NamespacePeerDiagnostic:
    if any(node["status"] != "available" for node in chain):
        return {"status": "unavailable", "reason": "namespace_metadata"}
    try:
        records = process_status_records(include_names=False, bounded=True)
        own_ancestors = process_ancestors(records, os.getpid())
        peers = [item for item in records if item["pid"] not in own_ancestors]
        owners = sorted({node["uid"] for node in chain})
        groups = sorted({node["gid"] for node in chain if node["mode"] & 0o020})
        return {"status": "available", "reason": "none", "peerCount": len(peers),
                "uidCounts": [{"identity": uid, "processCount": sum(uid in item["uids"] for item in peers)} for uid in owners],
                "writableGidCounts": [{"identity": gid, "processCount": sum(gid in item["gids"] + item["groups"] for item in peers)}
                                      for gid in groups]}
    except RecoveryBlocked as error:
        return {"status": "unavailable", "reason": "bounded_limit" if str(error) == "diagnostic-peer-limit" else "peer_metadata"}
    except (OSError, KeyError, ValueError, TypeError, AttributeError):
        return {"status": "unavailable", "reason": "peer_metadata"}


def isolation_validator():
    """Load only the source-verified sibling staged with this helper; never search PYTHONPATH."""
    helper = Path(__file__).resolve()
    source = helper.parent / "fireguard-deployment-isolation.py"
    try:
        node = source.lstat()
        require(stat.S_ISREG(node.st_mode) and not source.is_symlink()
                and node.st_uid == helper.stat().st_uid and not node.st_mode & 0o022,
                "isolation-module-unverified")
        spec = importlib.util.spec_from_file_location("_fireguard_deployment_isolation", source)
        require(spec is not None and spec.loader is not None, "isolation-module-unavailable")
        module = importlib.util.module_from_spec(spec)
        sys.modules[spec.name] = module
        spec.loader.exec_module(module)
        return module.validate_isolation
    except (OSError, AttributeError, ImportError, SyntaxError):
        raise RecoveryBlocked("isolation-module-unavailable") from None


def metadata_text(path, limit):
    try:
        with path.open("r", encoding="utf-8") as source:
            value = source.read(limit + 1)
        require(len(value) <= limit, "isolation-metadata-limit")
        return value
    except (OSError, UnicodeError):
        raise RecoveryBlocked("isolation-metadata-unavailable") from None


def numeric_proc_entries(path, limit):
    try:
        entries = sorted(int(item.name) for item in path.iterdir() if item.name.isdecimal())
    except OSError:
        raise RecoveryBlocked("isolation-inventory-unavailable") from None
    require(bool(entries) and len(entries) <= limit and len(entries) == len(set(entries)), "isolation-inventory-unverified")
    return entries


def process_stat(path, identifier):
    value = metadata_text(path, 16384)
    boundary = value.rfind(")")
    require(boundary > 0 and value.split(" ", 1)[0] == str(identifier), "isolation-process-identity-unverified")
    fields = value[boundary + 1:].split()
    require(len(fields) >= 20 and fields[1].isdecimal() and fields[19].isdecimal(), "isolation-process-stat-unverified")
    return int(fields[1]), int(fields[19])


def task_credentials(value):
    selected = {"Pid", "Tgid", "PPid", "Uid", "Gid", "Groups", "NSpid", "NStgid", "NoNewPrivs",
                "CapEff", "CapPrm", "CapInh", "CapAmb", "CapBnd"}
    fields = {}
    for line in value.splitlines():
        key, separator, item = line.partition(":")
        if separator and key in selected:
            require(key not in fields, "isolation-process-credentials-unverified")
            fields[key] = item.strip()
    require(set(fields) == selected, "isolation-process-credentials-unverified")
    record = {}
    for source, target in (("Pid", "tid"), ("Tgid", "tgid"), ("PPid", "ppid"), ("NoNewPrivs", "noNewPrivs")):
        require(fields[source].isdecimal(), "isolation-process-credentials-unverified")
        record[target] = int(fields[source])
    for source, target in (("Uid", "uids"), ("Gid", "gids"), ("Groups", "groups"), ("NSpid", "nsPid"), ("NStgid", "nsTgid")):
        values = fields[source].split()
        require(all(item.isdecimal() for item in values), "isolation-process-credentials-unverified")
        record[target] = [int(item) for item in values]
    require(len(record["uids"]) == len(record["gids"]) == 4 and record["noNewPrivs"] in (0, 1),
            "isolation-process-credentials-unverified")
    for key in ("CapEff", "CapPrm", "CapInh", "CapAmb", "CapBnd"):
        require(re.fullmatch(r"[a-fA-F0-9]{1,16}", fields[key]), "isolation-process-credentials-unverified")
        record[key[0].lower() + key[1:]] = int(fields[key], 16)
    return record


def task_cgroup(value, driver):
    paths = []
    for line in value.splitlines():
        parts = line.split(":", 2)
        require(len(parts) == 3 and parts[0].isdecimal() and parts[2].startswith("/")
                and posixpath.normpath(parts[2]) == parts[2] and "\x00" not in parts[2], "isolation-cgroup-unverified")
        paths.append(parts[2])
    require(bool(paths) and len(set(paths)) == 1, "isolation-cgroup-unverified")
    expression = r"/system.slice/docker-([a-f0-9]{64})\.scope" if driver == "systemd" else r"/docker/([a-f0-9]{64})"
    match = re.fullmatch(expression, paths[0])
    return {"cgroupPath": paths[0], "cgroupsCoherent": True, "containerId": match[1] if match else None}


def isolation_task(directory, pid, tid, driver):
    parent, start = process_stat(directory / "stat", tid)
    task = task_credentials(metadata_text(directory / "status", 512 * 1024))
    require(task["tid"] == tid and task["tgid"] == pid and task["ppid"] == parent,
            "isolation-process-identity-unverified")
    task.update(startTicks=start, **task_cgroup(metadata_text(directory / "cgroup", 16384), driver))
    require(process_stat(directory / "stat", tid) == (parent, start), "isolation-process-identity-changed")
    return task


def isolation_process_inventory(driver):
    """Metadata only, including every thread; an incomplete scan is never a successful proof."""
    root = Path("/proc")
    identifiers = numeric_proc_entries(root, 32768)
    records, task_count, credential_count = [], 0, 0
    for pid in identifiers:
        directory = root / str(pid)
        parent, start = process_stat(directory / "stat", pid)
        tids = numeric_proc_entries(directory / "task", 65536)
        task_count += len(tids)
        require(task_count <= 65536, "isolation-inventory-limit")
        tasks = []
        for tid in tids:
            task_path = directory / "task" / str(tid)
            task = isolation_task(task_path, pid, tid, driver)
            require(task["ppid"] == parent, "isolation-process-identity-unverified")
            credential_count += sum(len(task[key]) for key in ("uids", "gids", "groups", "nsPid", "nsTgid"))
            require(credential_count <= 1_048_576, "isolation-inventory-limit")
            tasks.append(task)
        require(numeric_proc_entries(directory / "task", 65536) == tids
                and process_stat(directory / "stat", pid) == (parent, start), "isolation-process-identity-changed")
        records.append({"pid": pid, "ppid": parent, "startTicks": start, "tasksComplete": True, "tasks": tasks})
    require(numeric_proc_entries(root, 32768) == identifiers, "isolation-inventory-changed")
    return records


ISOLATION_VERSION_FORMAT = '{"client":{{json .Client.APIVersion}},"server":{{json .Server.APIVersion}},"minimum":{{json .Server.MinAPIVersion}}}'
ISOLATION_INFO_FORMAT = ('{{$rootful := true}}{{range .SecurityOptions}}{{if eq . "name=rootless"}}{{$rootful = false}}{{end}}{{end}}'
                         '{"Rootful":{{$rootful}},"CgroupDriver":{{json .CgroupDriver}},"DockerRootDir":{{json .DockerRootDir}}}')
ISOLATION_IMAGE_FORMAT = '{"Id":{{json .ID}},"RootFS":{"Type":{{json .RootFS.Type}}}}'
STORAGE_ACTOR_FORMAT = ('{"Id":{{json .ID}},"Image":{{json .Image}},"Name":{{json .Name}},'
                        '"State":{"Status":{{json .State.Status}},"Running":{{json .State.Running}},'
                        '"Restarting":{{json .State.Restarting}},"Paused":{{json .State.Paused}}},'
                        '"Owner":{{json (index .Config.Labels "fireguard.reviewed-storage-owner")}},'
                        '"Mounts":[{{range $i,$m := .Mounts}}{{if $i}},{{end}}'
                        '{"Type":{{json $m.Type}},"Name":{{json $m.Name}},"Source":{{json $m.Source}},'
                        '"Destination":{{json $m.Destination}},"RW":{{json $m.RW}}}{{end}}],'
                        '"Declarations":[{{range $i,$m := .HostConfig.Mounts}}{{if $i}},{{end}}'
                        '{"Type":{{json $m.Type}},"Source":{{json $m.Source}},"Target":{{json $m.Target}},"ReadOnly":{{$m.ReadOnly}},'
                        '"NoCopy":{{if and $m.VolumeOptions $m.VolumeOptions.NoCopy}}true{{else}}false{{end}},'
                        '"NonRecursive":{{if and $m.BindOptions $m.BindOptions.NonRecursive}}true{{else}}false{{end}},'
                        '"PropagationEmpty":{{or (not $m.BindOptions) (eq $m.BindOptions.Propagation "")}}}{{end}}]}')
STORAGE_REFERENCE_FORMAT = ('{"Id":{{json .ID}},"Mounts":[{{range $i,$m := .Mounts}}{{if $i}},{{end}}'
                            '{"Type":{{json $m.Type}},"Name":{{json $m.Name}},"Source":{{json $m.Source}}}{{end}}]}')
ISOLATION_VOLUME_FORMAT = ('{"Name":{{json .Name}},"Driver":{{json .Driver}},"Scope":{{json .Scope}},'
                           '"Mountpoint":{{json .Mountpoint}},"OptionsEmpty":{{eq (len .Options) 0}}}')
ISOLATION_CONTAINER_FORMAT = (
    '{{$nnp := true}}{{if eq (len .HostConfig.SecurityOpt) 0}}{{$nnp = false}}{{end}}'
    '{{$hasNnp := false}}{{$extras := ""}}{{$declared := len .HostConfig.SecurityOpt}}{{if gt $declared 256}}{{$declared = 256}}{{end}}'
    '{{range .HostConfig.SecurityOpt}}{{if and (ne . "no-new-privileges") (ne . "no-new-privileges:true") (ne . "no-new-privileges=true")}}'
    '{{$nnp = false}}{{if lt (len $extras) 256}}{{$extras = printf "%s." $extras}}{{end}}{{else}}{{$hasNnp = true}}{{end}}{{end}}'
    '{{$project := index .Config.Labels "com.docker.compose.project"}}{{$projectClass := "other"}}'
    '{{if not $project}}{{$projectClass = "unknown"}}{{else if eq $project "fireguard-production-back"}}{{$projectClass = "api-production"}}'
    '{{else if eq $project "back"}}{{$projectClass = "api-production-legacy"}}'
    '{{else if eq $project "fireguard-dev-back"}}{{$projectClass = "api-development"}}'
    '{{else if eq $project "fireguard-production-front"}}{{$projectClass = "web-production"}}'
    '{{else if eq $project "fireguard-dev-front"}}{{$projectClass = "web-development"}}{{end}}'
    '{{$service := index .Config.Labels "com.docker.compose.service"}}{{$serviceClass := "other"}}'
    '{{if not $service}}{{$serviceClass = "unknown"}}{{else if eq $service "app"}}{{$serviceClass = "app"}}'
    '{{else if eq $service "assistant_worker"}}{{$serviceClass = "assistant_worker"}}'
    '{{else if eq $service "async_worker"}}{{$serviceClass = "async_worker"}}'
    '{{else if eq $service "scheduler_worker"}}{{$serviceClass = "scheduler_worker"}}'
    '{{else if eq $service "webhook_worker"}}{{$serviceClass = "webhook_worker"}}'
    '{{else if eq $service "backup_files"}}{{$serviceClass = "backup_files"}}'
    '{{else if eq $service "fireguard-web"}}{{$serviceClass = "fireguard-web"}}{{end}}'
    '{"Id":{{json .ID}},"Image":{{json .Image}},'
    '"SecurityProfile":{"hasRecognizedNnp":{{$hasNnp}},"optionsEmpty":{{eq (len .HostConfig.SecurityOpt) 0}},'
    '"extraOptionsCount":{{len $extras}},"declaredOptionsCount":{{$declared}},'
    '"projectClass":{{json $projectClass}},"serviceClass":{{json $serviceClass}}},'
    '"State":{"Pid":{{json .State.Pid}},"StartedAt":{{json .State.StartedAt}},"Running":{{json .State.Running}},'
    '"Restarting":{{json .State.Restarting}},"Paused":{{json .State.Paused}}},'
    '"HostConfig":{"Runtime":{{json .HostConfig.Runtime}},"PidMode":{{json .HostConfig.PidMode}},'
    '"UsernsMode":{{json .HostConfig.UsernsMode}},"CgroupParent":{{json .HostConfig.CgroupParent}},'
    '"Privileged":{{json .HostConfig.Privileged}},"NetworkMode":{{json .HostConfig.NetworkMode}},'
    '"IpcMode":{{json .HostConfig.IpcMode}},"UTSMode":{{json .HostConfig.UTSMode}},'
    '"CapAddEmpty":{{eq (len .HostConfig.CapAdd) 0}},"DevicesEmpty":{{eq (len .HostConfig.Devices) 0}},'
    '"DeviceRequestsEmpty":{{eq (len .HostConfig.DeviceRequests) 0}},"DeviceCgroupRulesEmpty":{{eq (len .HostConfig.DeviceCgroupRules) 0}},'
    '"TmpfsEmpty":{{eq (len .HostConfig.Tmpfs) 0}},"VolumesFromEmpty":{{eq (len .HostConfig.VolumesFrom) 0}},'
    '"SecurityOpt":{{if $nnp}}["no-new-privileges:true"]{{else}}[]{{end}},'
    '"Mounts":['
    '{{range $i,$m := .HostConfig.Mounts}}{{if $i}},{{end}}'
    '{"Type":{{json $m.Type}},"Name":{{json $m.Source}},"Destination":{{json $m.Target}},"RW":{{not $m.ReadOnly}},'
    '"OptionsDefault":{{and (eq $m.Consistency "") (not $m.BindOptions) (not $m.TmpfsOptions) (not $m.ClusterOptions) (not $m.ImageOptions) '
    '(or (not $m.VolumeOptions) (and (not $m.VolumeOptions.NoCopy) (eq $m.VolumeOptions.Subpath "") '
    '(eq (len $m.VolumeOptions.Labels) 0) (not $m.VolumeOptions.DriverConfig)))}}}{{end}}],'
    '"Binds":['
    '{{range $i,$b := .HostConfig.Binds}}{{if $i}},{{end}}{{$p := split $b ":"}}'
    '{"Type":"volume","Name":{{if ge (len $p) 2}}{{json (index $p 0)}}{{else}}""{{end}},'
    '"Destination":{{if ge (len $p) 2}}{{json (index $p 1)}}{{else}}""{{end}},'
    '"RW":{{or (eq (len $p) 2) (and (eq (len $p) 3) (eq (index $p 2) "rw"))}},'
    '"OptionsDefault":{{or (eq (len $p) 2) (and (eq (len $p) 3) (or (eq (index $p 2) "ro") (eq (index $p 2) "rw")))}}}{{end}}]},'
    '"Mounts":[{{range $i,$m := .Mounts}}{{if $i}},{{end}}'
    '{"Type":{{json $m.Type}},"Name":{{json $m.Name}},"Driver":{{json $m.Driver}},'
    '"Source":{{json $m.Source}},"Destination":{{json $m.Destination}},"RW":{{json $m.RW}}}{{end}}]}'
)


def docker_api_version(value):
    require(type(value) is str and re.fullmatch(r"1\.[0-9]{2}", value), "isolation-docker-api-unverified")
    return int(value.split(".")[1])


def canonical_storage(value):
    require(type(value) is str and value.startswith("/") and posixpath.normpath(value) == value,
            "isolation-storage-unverified")
    try:
        require(str(Path(value).resolve(strict=True)) == value, "isolation-storage-unverified")
    except OSError:
        raise RecoveryBlocked("isolation-storage-unavailable") from None
    return value


STORAGE_INSPECT_PHP = r'''
function storage_fail($code) {
  echo json_encode(['ok' => false, 'code' => $code], JSON_THROW_ON_ERROR);
  exit(0);
}
function storage_stat($path) {
  clearstatcache(true, $path);
  $value = @lstat($path);
  if ($value === false) storage_fail('stat-unavailable');
  $kind = $value['mode'] & 0170000;
  if ($kind !== 0040000) storage_fail($kind === 0120000 ? 'symlink' : 'not-directory');
  return ['device' => $value['dev'], 'inode' => $value['ino'], 'uid' => $value['uid'],
          'gid' => $value['gid'], 'mode' => $value['mode'] & 07777,
          'isDirectory' => true, 'isSymlink' => false];
}
function storage_named_identity($target, $expected) {
  if (storage_stat($target) !== $expected) storage_fail('named-identity');
}
function storage_related($left, $right) {
  return $left === $right || str_starts_with($left, $right . '/');
}
function storage_process($text) {
  if (!is_string($text) || strlen($text) > 16384) storage_fail('process-proof');
  $keys = ['Uid', 'Gid', 'CapEff', 'CapPrm', 'CapInh', 'CapAmb', 'CapBnd', 'NoNewPrivs', 'Pid', 'Tgid', 'NSpid', 'NStgid'];
  $values = [];
  foreach (explode("\n", $text) as $line) {
    $parts = explode(':', $line, 2);
    if (count($parts) !== 2 || !in_array($parts[0], $keys, true)) continue;
    if (isset($values[$parts[0]])) storage_fail('process-proof');
    $values[$parts[0]] = trim($parts[1]);
  }
  if (count($values) !== count($keys)) storage_fail('process-proof');
  foreach (['Uid', 'Gid'] as $key) {
    if (!preg_match('/^0\s+0\s+0\s+0$/D', $values[$key])) storage_fail('process-proof');
  }
  foreach (['CapEff', 'CapPrm', 'CapInh', 'CapAmb', 'CapBnd'] as $key) {
    if (!preg_match('/^0{1,16}$/D', $values[$key])) storage_fail('process-proof');
  }
  foreach (['NoNewPrivs', 'Pid', 'Tgid', 'NSpid', 'NStgid'] as $key) {
    if ($values[$key] !== '1') storage_fail('process-proof');
  }
}
function storage_propagation($options, $category) {
  $seen = [];
  foreach ($options as $option) {
    if ($option === 'unbindable') storage_fail($category . '-unbindable');
    if (str_starts_with($option, 'shared:')) storage_fail($category . '-shared');
    if (!preg_match('/^(master|propagate_from):([1-9][0-9]{0,9})$/D', $option, $match)
        || (int)$match[2] > 4294967295 || isset($seen[$match[1]])) storage_fail('mount-metadata');
    $seen[$match[1]] = true;
  }
  if (isset($seen['propagate_from']) && !isset($seen['master'])) storage_fail('mount-metadata');
}
function storage_mounts($text, $root, $targets, $volumeRoots) {
  if (strlen($text) > 2097152) storage_fail('mount-metadata');
  $lines = explode("\n", trim($text));
  if (count($lines) > 65536) storage_fail('mount-metadata');
  $rootCount = 0;
  $targetCounts = array_fill(0, count($targets), 0);
  $selected = [];
  foreach ($lines as $line) {
    $fields = explode(' ', $line);
    $separator = array_search('-', $fields, true);
    if (count($fields) < 10 || $separator === false || $separator < 6
        || count($fields) !== $separator + 4 || !preg_match('/^[0-9]+$/D', $fields[0]) || !preg_match('/^[0-9]+$/D', $fields[1])) {
      storage_fail('mount-metadata');
    }
    $point = strtr($fields[4], ['\\040' => ' ', '\\011' => "\t", '\\012' => "\n", '\\134' => '\\']);
    $targetIndex = array_search($point, $targets, true);
    if ($point === $root || $targetIndex !== false) {
      $selected[$point] = $line;
      if ($targetIndex !== false) ++$targetCounts[$targetIndex];
      else ++$rootCount;
      if (!in_array('ro', explode(',', $fields[5]), true)) storage_fail('not-readonly');
      storage_propagation(array_slice($fields, 6, $separator - 6), $targetIndex === false ? 'root' : 'named');
    } elseif ($point === $root . '/volumes') {
      storage_fail('submount');
    }
    foreach ($volumeRoots as $volumeRoot) {
      if (storage_related($point, $volumeRoot)) storage_fail('submount');
    }
    foreach ($targets as $target) {
      if ($point !== $target && storage_related($point, $target)) storage_fail('submount');
    }
  }
  if ($rootCount !== 1) storage_fail('root-mount');
  foreach ($targetCounts as $count) if ($count !== 1) storage_fail('named-mount');
  ksort($selected);
  return $selected; // Private in-memory signatures; never publish sources or options.
}
try {
  storage_process(@file_get_contents('/proc/self/status'));
  $input = json_decode($argv[1] ?? '', true, 16, JSON_THROW_ON_ERROR);
  if (!is_array($input) || ($input['root'] ?? null) !== '/var/lib/docker'
      || !isset($input['names']) || !is_array($input['names']) || count($input['names']) > 4096
      || !array_is_list($input['names']) || count(array_unique($input['names'])) !== count($input['names'])) {
    storage_fail('input');
  }
  $root = $input['root'];
  $volumeRoots = [];
  $targets = [];
  foreach ($input['names'] as $name) {
    if (!is_string($name) || strlen($name) > 255 || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/D', $name)) storage_fail('input');
    $volumeRoots[] = $root . '/volumes/' . $name;
    $targets[] = '/__fireguard_volume_proof/' . (count($targets));
  }
  $mountinfo = @file_get_contents('/proc/self/mountinfo');
  if ($mountinfo === false) storage_fail('mount-metadata');
  $mountProof = storage_mounts($mountinfo, $root, $targets, $volumeRoots);
  $rootStat = storage_stat($root);
  $volumesStat = storage_stat($root . '/volumes');
  $nodes = [];
  foreach ($volumeRoots as $index => $volumeRoot) {
    $nodes[] = ['index' => $index, 'directory' => storage_stat($volumeRoot), 'data' => storage_stat($volumeRoot . '/_data')];
    storage_named_identity($targets[$index], $nodes[$index]['data']);
  }
  $finalMountinfo = @file_get_contents('/proc/self/mountinfo');
  if ($finalMountinfo === false) storage_fail('mount-metadata');
  if (storage_mounts($finalMountinfo, $root, $targets, $volumeRoots) !== $mountProof) storage_fail('changed');
  if (storage_stat($root) !== $rootStat || storage_stat($root . '/volumes') !== $volumesStat) storage_fail('changed');
  foreach ($volumeRoots as $index => $volumeRoot) {
    if (storage_stat($volumeRoot) !== $nodes[$index]['directory']
        || storage_stat($volumeRoot . '/_data') !== $nodes[$index]['data']) storage_fail('changed');
    storage_named_identity($targets[$index], $nodes[$index]['data']);
  }
  echo json_encode(['ok' => true, 'actorVerified' => true, 'root' => $rootStat, 'volumes' => $volumesStat, 'nodes' => $nodes], JSON_THROW_ON_ERROR);
} catch (Throwable $error) { storage_fail('metadata'); }
'''


def storage_node(value):
    fields = {"device", "inode", "uid", "gid", "mode", "isDirectory", "isSymlink"}
    require(type(value) is dict and set(value) == fields, "storage-inspector-metadata-unverified")
    require(all(type(value[key]) is int and value[key] >= 0 for key in ("device", "inode", "uid", "gid", "mode"))
            and 0 < value["inode"] <= 0xffffffffffffffff and value["device"] <= 0xffffffffffffffff
            and value["uid"] <= 0xffffffff and value["gid"] <= 0xffffffff
            and value["mode"] <= 0o7777 and value["isDirectory"] is True and value["isSymlink"] is False,
            "storage-inspector-metadata-unverified")
    return value


def storage_root_chain():
    """The sole allowed bind cannot obscure the image's runtime or /proc."""
    paths = [Path(STORAGE_ROOT), *Path(STORAGE_ROOT).parents]
    result = []
    try:
        for path in paths:
            value = path.lstat()
            node = storage_node({"device": value.st_dev, "inode": value.st_ino, "uid": value.st_uid, "gid": value.st_gid,
                                 "mode": stat.S_IMODE(value.st_mode), "isDirectory": stat.S_ISDIR(value.st_mode),
                                 "isSymlink": stat.S_ISLNK(value.st_mode)})
            require(node["uid"] == 0 and not node["mode"] & 0o022, "storage-root-authority-unverified")
            result.append(node)
    except OSError:
        raise RecoveryBlocked("storage-root-metadata-unavailable") from None
    return result


def storage_kernel():
    try:
        match = re.match(r"([0-9]+)\.([0-9]+)(?:\.|-)", os.uname().release)
        require(match is not None and tuple(map(int, match.groups())) >= (5, 12), "storage-recursive-readonly-unsupported")
    except (AttributeError, OSError):
        raise RecoveryBlocked("storage-kernel-unverified") from None


def storage_volume_path(name):
    require(type(name) is str and len(name) <= 255 and VOLUME_NAME.fullmatch(name), "storage-volume-name-unverified")
    return STORAGE_ROOT + "/volumes/" + name + "/_data"


def storage_host_mounts(value, names):
    """Pin only the source mount and reject relevant aliases; unrelated overlays may churn."""
    require(type(value) is str and len(value) <= 2_097_152, "storage-host-mount-metadata-unverified")
    lines = value.splitlines()
    require(0 < len(lines) <= 65536, "storage-host-mount-metadata-unverified")
    roots = [storage_volume_path(name).removesuffix("/_data") for name in names]
    ancestors = []
    for line in lines:
        fields = line.split(" ")
        require("-" in fields, "storage-host-mount-metadata-unverified")
        separator = fields.index("-")
        require(separator >= 6 and len(fields) == separator + 4 and fields[0].isdecimal()
                and fields[1].isdecimal() and re.fullmatch(r"[0-9]+:[0-9]+", fields[2]),
                "storage-host-mount-metadata-unverified")
        point = fields[4]
        for encoded, decoded in (("\\040", " "), ("\\011", "\t"), ("\\012", "\n"), ("\\134", "\\")):
            point = point.replace(encoded, decoded)
        require(point.startswith("/") and posixpath.normpath(point) == point and "\x00" not in point,
                "storage-host-mount-metadata-unverified")
        require(point != STORAGE_ROOT + "/volumes" and not any(
            point == root or point.startswith(root + "/") for root in roots), "storage-host-volume-submount")
        if point == "/" or point == STORAGE_ROOT or STORAGE_ROOT.startswith(point + "/"):
            ancestors.append((point, fields, separator))
    require(bool(ancestors), "storage-host-source-mount-unverified")
    longest = max(len(item[0]) for item in ancestors)
    selected = [item for item in ancestors if len(item[0]) == longest]
    require(len(selected) == 1, "storage-host-source-mount-unverified")
    point, fields, separator = selected[0]
    propagation = {"shared": None, "master": None, "propagate_from": None}
    for option in fields[6:separator]:
        require(option != "unbindable", "storage-host-source-unbindable")
        key, marker, value = option.partition(":")
        require(marker and key in propagation and propagation[key] is None and value.isdecimal()
                and 0 < int(value) <= 0xffffffffffffffff, "storage-host-source-mount-unverified")
        propagation[key] = int(value)
    numbers = [int(fields[0]), int(fields[1]), *map(int, fields[2].split(":"))]
    require(all(0 <= item <= 0xffffffffffffffff for item in numbers), "storage-host-mount-metadata-unverified")
    return {"mountId": numbers[0], "parentId": numbers[1], "deviceMajor": numbers[2], "deviceMinor": numbers[3],
            "sourceDepth": len(Path(STORAGE_ROOT).parts) - len(Path(point).parts),
            "rootHash": hashlib.sha256(fields[3].encode("utf-8")).hexdigest(),
            "readonly": "ro" in fields[5].split(","), "propagation": propagation,
            "private": not any(value is not None for value in propagation.values())}


def storage_host_mount_proof(names):
    return storage_host_mounts(metadata_text(Path("/proc/self/mountinfo"), 2_097_152), names)


def storage_expected_mounts(names):
    mounts = [{"Type": "bind", "Name": "", "Source": STORAGE_ROOT, "Destination": STORAGE_ROOT, "RW": False}]
    declarations = [{"Type": "bind", "Source": STORAGE_ROOT, "Target": STORAGE_ROOT, "ReadOnly": True,
                     "NoCopy": False, "NonRecursive": True, "PropagationEmpty": True}]
    for index, name in enumerate(names):
        target = "/__fireguard_volume_proof/" + str(index)
        mounts.append({"Type": "volume", "Name": name, "Source": storage_volume_path(name), "Destination": target, "RW": False})
        declarations.append({"Type": "volume", "Source": name, "Target": target, "ReadOnly": True,
                             "NoCopy": True, "NonRecursive": False, "PropagationEmpty": True})
    return mounts, declarations


def storage_mounts_match(actual, expected, target):
    if type(actual) is not list or len(actual) != len(expected):
        return False
    for item in actual:
        if type(item) is not dict or set(item) != set(expected[0]):
            return False
        if any(type(item[key]) is not type(expected[0][key]) for key in item):
            return False
    return sorted(actual, key=lambda item: item[target]) == sorted(expected, key=lambda item: item[target])


def isolation_policy(containers, current_pid, current_uid, storage_path=None):
    storage_path = storage_path or canonical_storage
    storage = sorted({storage_path(mount["source"]) for item in containers if item.get("project") == PROJECT
                      for mount in item["mounts"]})
    require(bool(storage), "isolation-development-storage-unavailable")
    return {"authorityUid": 1000, "authorityGids": [1000], "currentPid": current_pid, "currentUid": current_uid,
            "protectedPaths": ["/srv"], "developmentStoragePaths": storage,
            "allowedVolumeMounts": [dict(item) for item in REVIEWED_VOLUME_MOUNTS]}


def isolation_denial_counts(snapshot, policy):
    """Informational aggregates only; they never grant an exception or expose peers."""
    counts = dict.fromkeys(("unknownHostPeer", "runtime", "privatePid", "privileged", "noNewPrivs", "capabilities",
                            "mixedCredentials", "nonAllowlistedMount", "volumeOptions", "metadata"), 0)
    try:
        own = process_ancestors(snapshot["processes"], policy["currentPid"])
        containers = {item["Id"]: item for item in snapshot["containers"]}
        relevant = set()
        for process in snapshot["processes"]:
            if process["pid"] in own:
                continue
            tasks = [task for task in process["tasks"] if 1000 in task["uids"] + task["gids"] + task["groups"]]
            for task in tasks:
                counts["noNewPrivs"] += int(task["noNewPrivs"] != 1)
                counts["capabilities"] += int(any(task[key] != 0 for key in ("capEff", "capPrm", "capInh", "capAmb")))
                counts["mixedCredentials"] += int(task["uids"] != [1000] * 4 or len(set(task["gids"])) != 1)
            owners = {task["containerId"] for task in tasks}
            counts["unknownHostPeer"] += int(bool(tasks) and (None in owners or not owners.issubset(containers)))
            relevant.update(owner for owner in owners if owner in containers)
        for owner in relevant:
            container = containers[owner]
            config = container["HostConfig"]
            counts["runtime"] += int(config["Runtime"] != "runc")
            counts["privatePid"] += int(config["PidMode"] != "")
            counts["privileged"] += int(config["Privileged"] is not False)
            counts["noNewPrivs"] += int(config["SecurityOpt"] not in (["no-new-privileges"], ["no-new-privileges:true"]))
            for mount in container["Mounts"]:
                counts["nonAllowlistedMount"] += int(mount["Type"] != "volume" or not any(
                    all(mount[key] == expected[key] for key in ("Name", "Destination", "RW")) for expected in policy["allowedVolumeMounts"]))
        used = {mount["Name"] for owner in relevant for mount in containers[owner]["Mounts"] if mount["Type"] == "volume"}
        counts["volumeOptions"] = sum(volume["OptionsEmpty"] is not True for volume in snapshot["volumes"] if volume["Name"] in used)
    except (KeyError, TypeError, ValueError, RecoveryBlocked):
        counts["metadata"] = 1
    return counts


def require_isolation(result, snapshot, policy):
    if not result.allowed:
        code = "container-isolation-" + result.code.value
        counts = isolation_denial_counts(snapshot, policy)
        if result.code.value in {"invalid-snapshot", "incomplete-inventory"}:
            counts["metadata"] = 1
        metadata = {"counts": counts}
        # This method belongs to the adjacent source-verified pure module. Its
        # projection admits exact closed enum members and a bool/null only.
        if callable(getattr(result, "diagnostic", None)):
            metadata["diagnostic"] = result.diagnostic()
        raise RecoveryBlocked(code + " " + json.dumps(metadata, sort_keys=True))


def isolated_peer_tasks(snapshot):
    """Called only after the pure validator accepts the full inventory."""
    return {process["pid"]: next(task for task in process["tasks"] if task["tid"] == process["pid"])
            for process in snapshot["processes"] if all(task["uids"] == [1000] * 4 for task in process["tasks"])}


def relevant_storage_proof(snapshot, development_names):
    proof = snapshot.get("storageProof")
    require(type(proof) is dict, "storage-physical-proof-unavailable")
    peers = isolated_peer_tasks(snapshot)
    owners = {task["containerId"] for process in snapshot["processes"] if process["pid"] in peers for task in process["tasks"]}
    names = set(development_names)
    names.update(mount["Name"] for item in snapshot["containers"] if item["Id"] in owners
                 for mount in item["Mounts"] if mount["Type"] == "volume")
    nodes = {name: proof["nodes"][index] for index, name in enumerate(proof["volumeNames"])}
    require(names.issubset(nodes), "storage-physical-proof-unavailable")
    return {"rootChain": proof["rootChain"], "hostMount": proof["hostMount"], "volumes": proof["volumes"],
            "nodes": {name: {key: value for key, value in nodes[name].items() if key != "index"} for name in sorted(names)}}


def potential_authority_volume_names(processes, containers, current_pid):
    own = process_ancestors(processes, current_pid)
    owners = {task["containerId"] for process in processes if process["pid"] not in own for task in process["tasks"]
              if 1000 in task["uids"] + task["gids"] + task["groups"]}
    return {mount["Name"] for item in containers if item["Id"] in owners for mount in item["Mounts"] if mount["Type"] == "volume"}


class DockerMetadataOperation(str, Enum):
    """Fixed public phases; never interpolate an identifier, path or child error."""

    LEGACY_LIST = "docker-legacy-container-list"
    LEGACY_INSPECT = "docker-legacy-container-inspect"
    ISOLATION_VERSION = "docker-isolation-version"
    ISOLATION_INFO = "docker-isolation-info"
    ISOLATION_LIST = "docker-isolation-container-list"
    ISOLATION_CONTAINER = "docker-isolation-container-inspect"
    ISOLATION_IMAGE = "docker-isolation-image-inspect"
    ISOLATION_VOLUME = "docker-isolation-volume-inspect"
    STORAGE_CREATE = "docker-storage-inspector-create"
    STORAGE_START = "docker-storage-inspector-start"
    STORAGE_REMOVE = "docker-storage-inspector-remove"
    STORAGE_LIST = "docker-storage-inspector-owned-list"
    STORAGE_INSPECT = "docker-storage-inspector-owned-inspect"


def metadata_operation(run, argv, operation):
    require(isinstance(operation, DockerMetadataOperation), "docker-metadata-operation-unverified")
    try:
        return run(argv)
    except (RecoveryBlocked, OSError, subprocess.TimeoutExpired):
        raise RecoveryBlocked(operation.value + "-command-failed") from None


def isolation_operation(arguments):
    phases = {("version",): DockerMetadataOperation.ISOLATION_VERSION,
              ("info",): DockerMetadataOperation.ISOLATION_INFO,
              ("ps",): DockerMetadataOperation.ISOLATION_LIST,
              ("container", "inspect"): DockerMetadataOperation.ISOLATION_CONTAINER,
              ("image", "inspect"): DockerMetadataOperation.ISOLATION_IMAGE,
              ("volume", "inspect"): DockerMetadataOperation.ISOLATION_VOLUME}
    key = tuple(arguments[:2]) if arguments and arguments[0] in {"container", "image", "volume"} else tuple(arguments[:1])
    require(key in phases, "docker-isolation-operation-unverified")
    return phases[key]


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
        self._verified_storage_image = None
        self._storage_development = None
        self._storage_development_references = None
        self._storage_proof = None
        self._storage_authority_verified = False

    def isolation_docker(self, arguments):
        # Pin the API used by every request, not only the daemon's advertised maximum.
        return metadata_operation(self.run, ["/usr/bin/env", "DOCKER_API_VERSION=" + ISOLATION_API_VERSION,
                                            "docker", "--host", "unix:///var/run/docker.sock", *arguments], isolation_operation(arguments))

    def isolation_json(self, arguments):
        try:
            result = json.loads(self.isolation_docker(arguments))
            require(type(result) is dict, isolation_operation(arguments).value + "-metadata-unverified")
            return result
        except (ValueError, TypeError):
            raise RecoveryBlocked(isolation_operation(arguments).value + "-metadata-unverified") from None

    def isolation_api(self):
        override = os.environ.get("DOCKER_API_VERSION")
        if override is not None:
            require(docker_api_version(override) >= 45, "isolation-docker-api-override-unsupported")
        version = self.isolation_json(["version", "--format", ISOLATION_VERSION_FORMAT])
        require(docker_api_version(version["client"]) >= 45 and docker_api_version(version["server"]) >= 45
                and docker_api_version(version["minimum"]) <= 45, "isolation-docker-api-unsupported")
        info = self.isolation_json(["info", "--format", ISOLATION_INFO_FORMAT])
        require(info["Rootful"] is True and info["CgroupDriver"] in {"systemd", "cgroupfs"}, "isolation-docker-authority-unverified")
        if self._storage_development is not None:
            require(info.get("DockerRootDir") == STORAGE_ROOT, "storage-docker-root-unverified")
            self._storage_authority_verified = True
        return info["CgroupDriver"]

    def prepare_storage_inspector(self, containers):
        require(self._verified_storage_image is not None, "storage-source-image-unverified")
        development = {}
        references = {}
        for record in containers:
            if record.get("project") != PROJECT:
                continue
            for mount in record["mounts"]:
                name, source = mount["name"], mount["source"]
                require(source == storage_volume_path(name), "storage-development-mount-unverified")
                require(type(record.get("id")) is str and DOCKER_ID.fullmatch(record["id"]), "storage-volume-reference-unverified")
                development[name] = source
                references.setdefault(name, set()).add(record["id"])
        require(bool(development), "isolation-development-storage-unavailable")
        self._storage_development = development
        self._storage_development_references = references
        self._storage_authority_verified = False

    def reviewed_storage_path(self, value):
        require(self._storage_development is not None and value in self._storage_development.values(),
                "storage-development-mount-unverified")
        return value

    def storage_docker(self, arguments, operation):
        return metadata_operation(self.run, ["/usr/bin/env", "DOCKER_API_VERSION=" + ISOLATION_API_VERSION,
                                            "docker", "--host", "unix:///var/run/docker.sock", *arguments], operation)

    def cleanup_storage_actor(self, nonce):
        identifiers = self.storage_docker(["ps", "--all", "--quiet", "--no-trunc", "--filter",
                                          "label=fireguard.reviewed-storage-owner=" + nonce], DockerMetadataOperation.STORAGE_LIST).splitlines()
        require(len(identifiers) <= 1 and all(DOCKER_ID.fullmatch(value) for value in identifiers),
                "storage-inspector-cleanup-identity-unverified")
        for identifier in identifiers:
            self.storage_actor_identity(identifier, nonce)
            self.storage_docker(["rm", "--force", identifier], DockerMetadataOperation.STORAGE_REMOVE)

    def storage_actor_identity(self, identifier, nonce, *, created=False, names=None):
        try:
            identity = json.loads(self.storage_docker(["container", "inspect", "--format", STORAGE_ACTOR_FORMAT, identifier],
                                                     DockerMetadataOperation.STORAGE_INSPECT))
        except (TypeError, ValueError):
            raise RecoveryBlocked("storage-inspector-cleanup-identity-unverified") from None
        require(type(identity) is dict and identity.get("Id") == identifier and identity.get("Image") == self._verified_storage_image
                and identity.get("Owner") == nonce and identity.get("Name") == "/fireguard-reviewed-storage-" + nonce,
                "storage-inspector-cleanup-identity-unverified")
        state = identity.get("State")
        require(type(state) is dict and type(state.get("Status")) is str and state["Status"] in {"created", "running", "exited"}
                and state.get("Running") is (state["Status"] == "running")
                and state.get("Restarting") is False and state.get("Paused") is False
                and (not created or state["Status"] == "created"), "storage-inspector-cleanup-state-unverified")
        if names is not None:
            mounts, declarations = storage_expected_mounts(names)
            require(storage_mounts_match(identity.get("Mounts"), mounts, "Destination")
                    and storage_mounts_match(identity.get("Declarations"), declarations, "Target"),
                    "storage-inspector-mount-declaration-unverified")

    def storage_reference_proof(self, names, references, volumes):
        require(type(references) is dict and set(references) == set(names)
                and all(type(ids) is set and bool(ids) and all(type(item) is str and DOCKER_ID.fullmatch(item) for item in ids)
                        for ids in references.values()), "storage-volume-reference-unverified")
        identifiers = set().union(*references.values())
        require(len(identifiers) <= 4096, "storage-volume-reference-unverified")
        for identifier in sorted(identifiers):
            reference = self.isolation_json(["container", "inspect", "--format", STORAGE_REFERENCE_FORMAT, identifier])
            require(type(reference) is dict and reference.get("Id") == identifier and type(reference.get("Mounts")) is list,
                    "storage-volume-reference-unverified")
            for name in names:
                if identifier not in references[name]:
                    continue
                matches = [mount for mount in reference["Mounts"] if type(mount) is dict and mount.get("Type") == "volume"
                           and mount.get("Name") == name]
                require(bool(matches) and all(mount.get("Source") == storage_volume_path(name) for mount in matches),
                        "storage-volume-reference-unverified")
        for name in names:
            current = self.isolation_json(["volume", "inspect", "--format", ISOLATION_VOLUME_FORMAT, name])
            expected = {"Name": name, "Driver": "local", "Scope": "local", "OptionsEmpty": True,
                        "Mountpoint": storage_volume_path(name)}
            require(current == expected and type(current.get("OptionsEmpty")) is bool and volumes.get(name) == expected
                    and type(volumes[name].get("OptionsEmpty")) is bool, "storage-volume-reference-unverified")

    def inspect_storage(self, names, references, volumes):
        require(self._verified_storage_image is not None and self._storage_development is not None,
                "storage-source-image-unverified")
        require(self._storage_authority_verified, "storage-docker-root-unverified")
        require(type(names) is list and 0 < len(names) <= 4096 and all(type(name) is str and len(name) <= 255
                and VOLUME_NAME.fullmatch(name) for name in names) and len(set(names)) == len(names), "storage-volume-name-unverified")
        storage_kernel()
        chain = storage_root_chain()
        host_mount = storage_host_mount_proof(names)
        self.storage_reference_proof(names, references, volumes)
        payload = json.dumps({"root": STORAGE_ROOT, "names": names}, separators=(",", ":"))
        nonce = uuid.uuid4().hex
        arguments = ["create", "--name", "fireguard-reviewed-storage-" + nonce, "--label", "fireguard.reviewed-storage-owner=" + nonce,
                     "--pull", "never", "--init=false",
                     "--user", "0:0", "--cap-drop", "ALL", "--security-opt", "no-new-privileges:true",
                     # Moby PidMode.Valid accepts only empty (new private namespace), host, or container:<id>.
                     "--network", "none", "--pid", "", "--runtime", "runc", "--ipc", "private", "--read-only", "--workdir", "/",
                     "--no-healthcheck", "--env", "LD_PRELOAD=", "--env", "LD_AUDIT=", "--env", "LD_LIBRARY_PATH=",
                     "--mount", "type=bind,src=" + STORAGE_ROOT + ",dst=" + STORAGE_ROOT
                     + ",readonly,bind-recursive=disabled"]
        for index, name in enumerate(names):
            arguments.extend(["--mount", "type=volume,src=" + name + ",dst=/__fireguard_volume_proof/" + str(index)
                              + ",readonly,volume-nocopy"])
        arguments.extend(["--entrypoint", "/usr/bin/env", self._verified_storage_image,
                          "-i", "/usr/local/bin/php", "-n", "-r", STORAGE_INSPECT_PHP, payload])
        try:
            identifier = self.storage_docker(arguments, DockerMetadataOperation.STORAGE_CREATE).strip()
            require(DOCKER_ID.fullmatch(identifier), "storage-inspector-identity-unverified")
            self.storage_actor_identity(identifier, nonce, created=True, names=names)
            self.storage_reference_proof(names, references, volumes)
            output = self.storage_docker(["start", "--attach", identifier], DockerMetadataOperation.STORAGE_START)
        finally:
            # Also covers cancellation/timeout after Docker creates the actor but before returning its ID.
            # Only this nonce + image + name attested object; never purge unrelated runtime containers.
            self.cleanup_storage_actor(nonce)
        require(storage_root_chain() == chain, "storage-root-identity-changed")
        require(storage_host_mount_proof(names) == host_mount, "storage-host-source-mount-changed")
        self.storage_reference_proof(names, references, volumes)
        try:
            body = json.loads(output)
        except (TypeError, ValueError):
            raise RecoveryBlocked("storage-inspector-metadata-unverified") from None
        if type(body) is dict and body.get("ok") is False:
            codes = {"stat-unavailable", "symlink", "not-directory", "mount-metadata", "not-readonly", "not-private",
                     "root-shared", "root-unbindable", "named-shared", "named-slave", "named-unbindable",
                     "submount", "root-mount", "input", "changed", "metadata", "process-proof", "named-mount", "named-identity"}
            require(set(body) == {"ok", "code"} and type(body.get("code")) is str and body["code"] in codes,
                    "storage-inspector-metadata-unverified")
            raise RecoveryBlocked("storage-inspector-" + body["code"])
        require(type(body) is dict and set(body) == {"ok", "actorVerified", "root", "volumes", "nodes"}
                and body["ok"] is True and body["actorVerified"] is True,
                "storage-inspector-metadata-unverified")
        require(storage_node(body["root"]) == chain[0], "storage-inspector-root-identity-mismatch")
        parent = storage_node(body["volumes"])
        require(parent["uid"] == 0 and not parent["mode"] & 0o022, "storage-volume-parent-unverified")
        nodes = body["nodes"]
        require(type(nodes) is list and len(nodes) == len(names), "storage-inspector-metadata-unverified")
        occupied = {(node["device"], node["inode"]) for node in chain + REVIEWED_NAMESPACE}
        require((parent["device"], parent["inode"]) not in occupied, "storage-physical-alias")
        occupied.add((parent["device"], parent["inode"]))
        for index, node in enumerate(nodes):
            require(type(node) is dict and set(node) == {"index", "directory", "data"}
                    and type(node["index"]) is int and node["index"] == index, "storage-inspector-metadata-unverified")
            directory = storage_node(node["directory"])
            require(directory["uid"] == 0 and not directory["mode"] & 0o022, "storage-volume-parent-unverified")
            for value in (directory, storage_node(node["data"])):
                physical = (value["device"], value["inode"])
                require(physical not in occupied, "storage-physical-alias")
                occupied.add(physical)
        self._storage_proof = {"rootChain": chain, "hostMount": host_mount, "volumeNames": names, "volumes": parent, "nodes": nodes}
        return self._storage_proof

    def isolation_container_ids(self):
        identifiers = self.isolation_docker(["ps", "--quiet", "--no-trunc"]).splitlines()
        require(len(identifiers) <= 4096 and len(set(identifiers)) == len(identifiers)
                and all(DOCKER_ID.fullmatch(item) for item in identifiers), "isolation-container-inventory-unverified")
        return sorted(identifiers)

    def isolation_containers(self, identifiers):
        records, images = [], {}
        for identifier in identifiers:
            record = self.isolation_json(["container", "inspect", "--format", ISOLATION_CONTAINER_FORMAT, identifier])
            require(record["Id"] == identifier and DOCKER_IMAGE_ID.fullmatch(record["Image"]), "isolation-container-identity-unverified")
            image_id = record["Image"]
            if image_id not in images:
                image = self.isolation_json(["image", "inspect", "--format", ISOLATION_IMAGE_FORMAT, image_id])
                require(image["Id"] == image_id, "isolation-image-identity-unverified")
                images[image_id] = image["RootFS"]
            record["RootFS"] = images[image_id]
            config = record["HostConfig"]
            mounts, binds = config.pop("Mounts"), config.pop("Binds")
            require(type(mounts) is list and type(binds) is list, "isolation-mount-declarations-unverified")
            config["MountDeclarations"] = mounts + binds
            config["MountDeclarationsComplete"] = True
            for mount in config["MountDeclarations"]:
                if not VOLUME_NAME.fullmatch(mount["Name"]):
                    mount["OptionsDefault"] = False
            records.append(record)
        return records

    def isolation_volumes(self, containers, *, required_names=None):
        names = {mount["Name"] for record in containers for mount in record["Mounts"] if mount["Type"] == "volume"}
        active_names = set(names)
        if self._storage_development is not None:
            require(type(required_names) is set and required_names.issubset(names), "storage-authority-cohort-unverified")
            names.update(self._storage_development)
            required_names = required_names | set(self._storage_development)
        require(len(names) <= 4096 and all(type(name) is str and VOLUME_NAME.fullmatch(name) for name in names),
                "isolation-volume-inventory-unverified")
        volumes = []
        for name in sorted(names):
            volume = self.isolation_json(["volume", "inspect", "--format", ISOLATION_VOLUME_FORMAT, name])
            require(volume["Name"] == name and type(volume["OptionsEmpty"]) is bool, "isolation-volume-metadata-unverified")
            if self._storage_development is None:
                canonical_storage(volume["Mountpoint"])
            elif name in required_names:
                require(volume["Driver"] == "local" and volume["Scope"] == "local" and volume["OptionsEmpty"] is True
                        and volume["Mountpoint"] == storage_volume_path(name), "storage-volume-authority-unverified")
            volumes.append(volume)
        for record in containers:
            for mount in record["Mounts"]:
                if mount["Type"] == "volume":
                    if self._storage_development is None:
                        canonical_storage(mount["Source"])
                    elif mount["Name"] in required_names:
                        require(mount["Source"] == storage_volume_path(mount["Name"]), "storage-volume-source-unverified")
        if self._storage_development is not None:
            references = {name: set(self._storage_development_references.get(name, set())) for name in required_names}
            for record in containers:
                for mount in record["Mounts"]:
                    if mount["Type"] == "volume" and mount["Name"] in references:
                        references[mount["Name"]].add(record["Id"])
            self.inspect_storage(sorted(required_names), references, {volume["Name"]: volume for volume in volumes})
        return [volume for volume in volumes if volume["Name"] in active_names]

    def isolation_snapshot(self):
        driver = self.isolation_api()
        identifiers = self.isolation_container_ids()
        containers = self.isolation_containers(identifiers)
        processes = isolation_process_inventory(driver)
        required = potential_authority_volume_names(processes, containers, os.getpid()) if self._storage_development is not None else None
        volumes = self.isolation_volumes(containers, required_names=required)
        require(self.isolation_container_ids() == identifiers, "isolation-container-inventory-changed")
        boot_id = metadata_text(Path("/proc/sys/kernel/random/boot_id"), 128).strip()
        boot_lines = [line.split() for line in metadata_text(Path("/proc/stat"), 2 * 1024 * 1024).splitlines() if line.startswith("btime ")]
        require(len(boot_lines) == 1 and len(boot_lines[0]) == 2 and boot_lines[0][1].isdecimal(), "isolation-boot-metadata-unverified")
        snapshot = {"complete": True, "proofMode": "trusted-docker-runc-private-pid", "dockerApiVersion": ISOLATION_API_VERSION,
                "cgroupDriver": driver, "bootId": boot_id, "bootTimeSeconds": int(boot_lines[0][1]),
                "clockTicks": os.sysconf("SC_CLK_TCK"), "processes": processes, "containers": containers, "volumes": volumes}
        if self._storage_development is not None:
            snapshot["storageProof"] = self._storage_proof
        return snapshot

    def isolation_peer_task(self, pid, driver):
        require(type(pid) is int and pid > 0 and driver in {"systemd", "cgroupfs"}, "isolated-peer-identity-unverified")
        return isolation_task(Path("/proc") / str(pid) / "task" / str(pid), pid, pid, driver)

    def reviewed_legacy_lock(self, app, value, diagnostic: LockIdentityDiagnostic):
        observed = {"device": value.st_dev, "inode": value.st_ino, "uid": value.st_uid, "gid": value.st_gid,
                    "mode": stat.S_IMODE(value.st_mode), "mtimeNs": value.st_mtime_ns, "ctimeNs": value.st_ctime_ns}
        if not (observed == REVIEWED_LEGACY_LOCK and diagnostic["processUid"] == diagnostic["effectiveUid"] == 1001
                and diagnostic["processGid"] == diagnostic["effectiveGid"] == 1001):
            raise LockInspectionBlocked("lock-identity-unverified", diagnostic)
        chain, metadata = [], []
        paths = [app, *app.parents]
        for depth, path in enumerate(paths):
            node = path.lstat()
            metadata.append(namespace_node_metadata(path, depth, node))
            if not (stat.S_ISDIR(node.st_mode) and not stat.S_ISLNK(node.st_mode) and not node.st_mode & 0o022
                    and node.st_uid in {0, 1001} and (depth > 0 or node.st_uid == 1001)):
                metadata.extend(namespace_node_metadata(remaining, index) for index, remaining in enumerate(paths) if index > depth)
                if metadata == REVIEWED_NAMESPACE:
                    fingerprint = hashlib.sha256(json.dumps(metadata, sort_keys=True).encode()).hexdigest()
                    return {"gid": 1001, "namespaceSha256": fingerprint, "namespace": metadata, "isolationRequired": True}
                diagnostic.update({"namespaceRefusalDepth": depth, "namespaceUid": node.st_uid, "namespaceGid": node.st_gid,
                                   "namespaceMode": stat.S_IMODE(node.st_mode), "namespaceDevice": node.st_dev, "namespaceInode": node.st_ino})
                diagnostic["namespaceChain"] = metadata
                diagnostic["namespacePeerCounts"] = namespace_peer_counts(metadata)
                raise LockInspectionBlocked("lock-identity-unverified", diagnostic)
            chain.append({"depth": depth, "device": node.st_dev, "inode": node.st_ino, "uid": node.st_uid,
                          "gid": node.st_gid, "mode": stat.S_IMODE(node.st_mode)})
        fingerprint = hashlib.sha256(json.dumps(chain, sort_keys=True).encode()).hexdigest()
        return {"gid": 1001, "namespaceSha256": fingerprint, "namespace": chain}

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
        if not (stat.S_ISDIR(value.st_mode) and not stat.S_ISLNK(value.st_mode) and value.st_uid == process_uid):
            raise LockInspectionBlocked("lock-identity-unverified", diagnostic)
        reviewed_legacy = None
        if value.st_mode & 0o022:
            reviewed_legacy = self.reviewed_legacy_lock(app, value, diagnostic)
        if any(lock.iterdir()):
            diagnostic["empty"] = False
            raise LockInspectionBlocked("historical-lock-not-empty", diagnostic)
        identity = {"device": value.st_dev, "inode": value.st_ino, "uid": value.st_uid,
                    "mtimeNs": value.st_mtime_ns, "ctimeNs": value.st_ctime_ns}
        if reviewed_legacy is not None:
            identity["reviewedLegacy"] = reviewed_legacy
        return identity

    def processes(self):
        records = process_status_records()
        ancestors = process_ancestors(records, os.getpid())
        for record in records:
            if record["uid"] != os.getuid() or record["pid"] in ancestors or record["kernelThread"]:
                continue
            path = Path("/proc") / str(record["pid"])
            stage = "cwd"
            try:
                record["cwd"] = os.readlink(path / "cwd")
                stage = "fd-list"
                descriptors = list((path / "fd").iterdir())
                stage = "fd-link"
                record["fds"] = [os.readlink(fd) for fd in descriptors]
            except FileNotFoundError:
                require(not path.exists(), "process-metadata-raced")
            except OSError as error:
                if error.errno in {errno.EACCES, errno.EPERM}:
                    # Discard this partial local snapshot. Every caller identity and
                    # concurrency decision stays unchanged; only collection differs.
                    return privileged_process_records()
                category = {errno.EACCES: "EACCES", errno.EPERM: "EPERM"}.get(error.errno, "OTHER")
                raise RecoveryBlocked("process-metadata-permission-unavailable "
                                      + json.dumps({"stage": stage, "errno": category}, sort_keys=True)) from None
        return records

    def containers(self):
        ids = metadata_operation(self.run, ["docker", "ps", "--all", "--quiet", "--no-trunc"], DockerMetadataOperation.LEGACY_LIST).splitlines()
        result = []
        for identifier in ids:
            require(re.fullmatch(r"[a-f0-9]{64}", identifier), "docker-container-id-invalid")
            value = metadata_operation(self.run, ["docker", "inspect", "--format", CONTAINER_FORMAT, identifier], DockerMetadataOperation.LEGACY_INSPECT)
            try:
                record = json.loads(value)
                require(type(record) is dict, "docker-legacy-container-inspect-metadata-unverified")
                result.append(record)
            except (ValueError, TypeError):
                raise RecoveryBlocked("docker-legacy-container-inspect-metadata-unverified") from None
        return result

    def image_manifest(self, proof):
        # Uses Docker's existing private registry login, never reads its credential file.
        # Image cache and a network-isolated, read-only inspection container only.
        self._verified_storage_image = None
        self.run(["docker", "pull", proof["image"]])
        identity = json.loads(self.run(["docker", "image", "inspect", "--format", IMAGE_FORMAT, proof["image"]]))
        require(proof["image"] in (identity.get("digests") or [])
                and identity.get("revision") == proof["sourceSha"]
                and str(identity.get("source", "")).lower() == "https://github.com/" + REPOSITORY.lower(),
                "immutable-image-provenance-mismatch")
        result = json.loads(self.run(["docker", "run", "--rm", "--network", "none", "--read-only", "--entrypoint", "php",
                                    proof["image"], "-r", IMAGE_MANIFEST_PHP]))
        if result == proof["migrations"]:
            # Only this source-verified immutable image may supply the metadata runtime.
            image_id = identity.get("id")
            if type(image_id) is str and DOCKER_IMAGE_ID.fullmatch(image_id) and identity.get("volumesEmpty") is True:
                self._verified_storage_image = image_id
        return result

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
    group_gid = identity.get("reviewedLegacy", {}).get("gid")
    # Host creates this context only for the exact original incident lock after
    # validating its credentials and either the protected or pinned namespace.
    reviewed_original_lock = identity.get("reviewedLegacy") is not None
    isolated_namespace = identity.get("reviewedLegacy", {}).get("isolationRequired") is True
    if not isolated_namespace:
        check_processes(host.processes(), current_pid, current_uid, group_gid=group_gid)
    container_records = host.containers()
    databases = check_containers(container_records, allow_reviewed_absent=reviewed_original_lock)
    writer_identity = reviewed_writer_identity(container_records) if reviewed_original_lock else None
    storage_identity = retained_storage_identity([item for item in container_records if item.get("project") == PROJECT]) \
        if writer_identity is not None and any(value is None for value in writer_identity.values()) else None
    if isolated_namespace:
        require(host.image_manifest(proof) == proof["migrations"], "image-migration-manifest-mismatch")
        host.prepare_storage_inspector(container_records)
        validator = isolation_validator()
        policy = isolation_policy(container_records, current_pid, current_uid, host.reviewed_storage_path)
        before = host.isolation_snapshot()
        result = validator(policy, before, before)
        require_isolation(result, before, policy)
        storage_before = relevant_storage_proof(before, host._storage_development)
        check_processes(host.processes(), current_pid, current_uid, group_gid=group_gid, isolated_peers=isolated_peer_tasks(before),
                        peer_reader=lambda pid: host.isolation_peer_task(pid, before["cgroupDriver"]))
    else:
        require(host.image_manifest(proof) == proof["migrations"], "image-migration-manifest-mismatch")
    for history, container in databases.items():
        executed = host.history(container, history)
        require(set(executed).issubset(proof["migrations"][history]), "executed-migration-unavailable-in-forward-image")
    if isolated_namespace:
        after = host.isolation_snapshot()
        result = validator(policy, before, after)
        require_isolation(result, after, policy)
        require(storage_before == relevant_storage_proof(after, host._storage_development), "storage-physical-identity-changed")
        check_processes(host.processes(), current_pid, current_uid, group_gid=group_gid, isolated_peers=isolated_peer_tasks(after),
                        peer_reader=lambda pid: host.isolation_peer_task(pid, after["cgroupDriver"]))
    else:
        check_processes(host.processes(), current_pid, current_uid, group_gid=group_gid)
    final_containers = host.containers()
    require(check_containers(final_containers, allow_reviewed_absent=reviewed_original_lock) == databases, "database-identity-changed-during-review")
    if writer_identity is not None:
        require(reviewed_writer_identity(final_containers) == writer_identity, "writer-identity-changed-during-review")
    if storage_identity is not None:
        require(retained_storage_identity([item for item in final_containers if item.get("project") == PROJECT]) == storage_identity,
                "retained-storage-identity-changed-during-review")
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
