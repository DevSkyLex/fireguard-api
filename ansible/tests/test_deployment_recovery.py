"""Hermetic tests: no SSH, network, secret files, databases or Docker daemon."""

import copy
import ast
from contextlib import contextmanager, nullcontext, redirect_stderr, redirect_stdout
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path
import re
import runpy
import shutil
import subprocess
import sys
import tarfile
import tempfile
from types import SimpleNamespace
import unittest
import uuid
from unittest.mock import MagicMock, patch

from jinja2 import Environment, StrictUndefined
import yaml


DRAFT = Path(__file__).resolve().parent
if DRAFT.name == "tests" and DRAFT.parent.name == "ansible":
    ROOT = DRAFT.parents[1]
    HELPER = ROOT / "bin/fireguard-deployment-recovery.py"
    WORKFLOW = ROOT / ".github/workflows/deploy-vps.yml"
    PLAYBOOK = ROOT / "ansible/deploy.yml"
else:
    ROOT = DRAFT.parents[2]
    HELPER = DRAFT / "fireguard-deployment-recovery.py"
    WORKFLOW = DRAFT / "deploy-vps.proposed.yml"
    PLAYBOOK = DRAFT / "deploy.proposed.yml"
SPEC = importlib.util.spec_from_file_location("deployment_recovery", HELPER)
RECOVERY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(RECOVERY)
SOURCE = "a" * 40
CURRENT_ID = "40000000001"
CI_ID = "40000000000"
IMAGE = "ghcr.io/devskylex/fireguard-api@sha256:" + "b" * 64
MEMBER = 1000
VERSION = "Version20261003120000"
DIGEST = hashlib.sha256(b"<?php\n// immutable migration\n").hexdigest()
MANIFEST = {history: {namespace + VERSION: DIGEST} for history, namespace in RECOVERY.SCHEMA_PREFIX.items()}


def run_metadata(workflow, source=SOURCE, status="completed", conclusion="success", event="workflow_dispatch"):
    return {"event": event, "head_branch": "develop", "head_sha": source,
            "repository": {"full_name": RECOVERY.REPOSITORY}, "head_repository": {"full_name": RECOVERY.REPOSITORY},
            "path": ".github/workflows/" + workflow, "status": status, "conclusion": conclusion, "run_attempt": 1}


class FakeAPI:
    def __init__(self):
        self.old = run_metadata("deploy-vps.yml", RECOVERY.FAILED_SHA, conclusion="failure")
        self.current = run_metadata("deploy-vps.yml", status="in_progress", conclusion=None)
        self.ci = run_metadata("ci.yml")
        self.jobs = [{"name": "Deploy to VPS", "status": "completed", "conclusion": "failure",
                      "started_at": "2026-10-03T00:08:28Z", "completed_at": "2026-10-03T00:14:05Z",
                      "steps": [{"name": "Run Ansible deployment", "status": "completed", "conclusion": "failure"}]}]
        self.gates = [{"name": "SonarQube Quality Gate (fireguard-api-develop)", "status": "completed", "conclusion": "success",
                       "steps": [{"name": name, "status": "completed", "conclusion": "success"}
                                 for name in ["Run SonarQube scan", "Enforce SonarQube quality gate"]]}]
        self.runs = [{"id": int(CURRENT_ID), "status": "in_progress"}]
        self.tip = SOURCE

    def get(self, path):
        if path.endswith("/git/ref/heads/develop"):
            return {"object": {"sha": self.tip}}
        return {RECOVERY.CANDIDATE: self.old, CURRENT_ID: self.current, CI_ID: self.ci}[path.rsplit("/", 1)[1]]

    def pages(self, path, key):
        if key == "workflow_runs":
            return self.runs
        if RECOVERY.CANDIDATE in path:
            return self.jobs
        return self.gates


class FakeGit:
    def __init__(self):
        self.changes = "A\tmigrations/main/" + VERSION + ".php\n"
        self.ancestor = True
        self.head = SOURCE
        self.calls = []

    def __call__(self, argv, *, cwd=None):
        self.calls.append(argv)
        if argv[1] == "rev-parse":
            return self.head + "\n"
        if argv[1] == "merge-base":
            RECOVERY.require(self.ancestor, "metadata-command-failed")
            return ""
        if argv[1] == "diff":
            return self.changes
        if argv[1] == "ls-tree":
            return argv[-1] + "/" + VERSION + ".php\n"
        if argv[1] == "show":
            return "<?php\n// immutable migration\n"
        raise AssertionError("Unexpected command: " + str(argv))


def environment():
    return {"GITHUB_EVENT_NAME": "workflow_dispatch", "SOURCE_BRANCH": "develop", "DEPLOY_ENVIRONMENT": "development",
            "REVIEWED_LOCK_RECOVERY_RUN_ID": RECOVERY.CANDIDATE, "GITHUB_REPOSITORY": RECOVERY.REPOSITORY,
            "IMAGE_INPUT": "", "RESET_DEVELOPMENT_FIXTURES": "false", "SOURCE_SHA": SOURCE,
            "IMAGE_REF": IMAGE, "GITHUB_RUN_ID": CURRENT_ID, "VERIFIED_CI_RUN_ID": CI_ID}


def proof():
    return RECOVERY.provenance(environment(), FakeAPI(), ROOT, FakeGit())


def container(service, identifier):
    database = service in {"auth_database", "main_database"}
    name = RECOVERY.PREFIX + "_" + service + "_data"
    mounts = [{"name": name, "source": RECOVERY.storage_volume_path(name)}] if database else []
    return {"id": identifier * 64, "project": RECOVERY.PROJECT, "service": service, "oneoff": "False",
            "workingDir": RECOVERY.APP_DIR, "running": database, "restarting": False,
            "status": "running" if database else "exited", "mounts": mounts}


class FakeHost:
    def __init__(self):
        self.identity = {"device": 1, "inode": 42, "uid": MEMBER,
                         "mtimeNs": int(RECOVERY.timestamp("2026-10-03T00:09:00Z") * 1e9),
                         "ctimeNs": int(RECOVERY.timestamp("2026-10-03T00:09:00Z") * 1e9)}
        self.process_records = [{"pid": 3, "ppid": 2, "uid": MEMBER, "comm": "python3", "cwd": "", "fds": []},
                                {"pid": 2, "ppid": 1, "uid": MEMBER, "comm": "sh", "cwd": "", "fds": []},
                                {"pid": 1, "ppid": 0, "uid": 0, "comm": "systemd", "cwd": "/", "fds": []}]
        self.container_records = [container(service, format(index, "x"))
                                  for index, service in enumerate(sorted(RECOVERY.WRITERS | {"auth_database", "main_database"}), start=1)]
        self.manifest = copy.deepcopy(MANIFEST)
        self.histories = {history: list(versions) for history, versions in MANIFEST.items()}
        self.released = False
        self.reacquired = False
        self.lock_reads = 0
        self.process_reads = 0
        self.container_reads = 0
        self.before_final_lock = None
        self.before_final_process = None
        self.before_final_containers = None
        self._verified_storage_image = None
        self._storage_development = None

    def lock_identity(self):
        self.lock_reads += 1
        if self.lock_reads == 2 and self.before_final_lock:
            self.before_final_lock(self)
        return copy.deepcopy(self.identity)

    def processes(self):
        self.process_reads += 1
        if self.process_reads == 2 and self.before_final_process:
            self.before_final_process(self)
        return self.process_records

    def containers(self):
        self.container_reads += 1
        if self.container_reads == 2 and self.before_final_containers:
            self.before_final_containers(self)
        return self.container_records

    def image_manifest(self, evidence):
        if self.manifest == evidence["migrations"]:
            self._verified_storage_image = "sha256:" + "b" * 64
        return self.manifest

    prepare_storage_inspector = RECOVERY.Host.prepare_storage_inspector
    reviewed_storage_path = RECOVERY.Host.reviewed_storage_path

    def history(self, identifier, history):
        return self.histories[history]

    def replace_empty_lock(self):
        self.released = True
        self.reacquired = True


def recover(host, evidence=None, **overrides):
    options = {"app_dir": RECOVERY.APP_DIR, "project": RECOVERY.PROJECT, "prefix": RECOVERY.PREFIX,
               "current_pid": 3, "current_uid": MEMBER} | overrides
    return RECOVERY.recover(evidence or proof(), host, **options)


@contextmanager
def legacy_fixture(**credentials):
    app, lock = MagicMock(), MagicMock()
    app.resolve.return_value = app
    app.__truediv__.return_value = lock
    chain = [app] + [MagicMock() for unused in range(5)]
    app.parents = chain[1:]
    for depth, path in enumerate(chain):
        path.is_symlink.return_value = False
        path.lstat.return_value = SimpleNamespace(st_mode=RECOVERY.stat.S_IFDIR | 0o755, st_uid=1001 if depth == 0 else 0,
                                                 st_gid=1001 if depth == 0 else 0, st_dev=2049, st_ino=100 + depth,
                                                 st_mtime_ns=200 + depth, st_ctime_ns=300 + depth)
    observed = RECOVERY.REVIEWED_LEGACY_LOCK
    lock.lstat.return_value = SimpleNamespace(st_mode=RECOVERY.stat.S_IFDIR | observed["mode"], st_uid=observed["uid"],
                                             st_gid=observed["gid"], st_dev=observed["device"], st_ino=observed["inode"],
                                             st_mtime_ns=observed["mtimeNs"], st_ctime_ns=observed["ctimeNs"])
    lock.iterdir.side_effect = lambda: iter([])
    with patch.object(RECOVERY, "Path", side_effect=lambda value: app if str(value) == RECOVERY.APP_DIR else Path(value)), \
         patch.object(RECOVERY.os, "getuid", return_value=credentials.get("uid", 1001), create=True), \
         patch.object(RECOVERY.os, "geteuid", return_value=credentials.get("euid", 1001), create=True), \
         patch.object(RECOVERY.os, "getgid", return_value=credentials.get("gid", 1001), create=True), \
         patch.object(RECOVERY.os, "getegid", return_value=credentials.get("egid", 1001), create=True), \
         patch.object(RECOVERY.os, "getpid", return_value=3), \
         patch.object(RECOVERY, "process_status_records", return_value=[]), \
         patch.object(RECOVERY.os, "chmod") as chmod, patch.object(RECOVERY.os, "chown", create=True) as chown:
        yield app, lock, chain
        chmod.assert_not_called()
        chown.assert_not_called()


def legacy_host():
    host = FakeHost()
    host.lock_identity = RECOVERY.Host().lock_identity
    for item in host.process_records:
        item["uid"] = 1001 if item["pid"] in {2, 3} else 0
        item.update(uids=[item["uid"]] * 4, gids=[item["uid"]] * 4, groups=[])
    return host


def foreign_peer():
    return {"pid": 4, "ppid": 1, "uid": 1002, "uids": [1002] * 4, "gids": [1002] * 4, "groups": [],
            "comm": "other", "cwd": "/", "fds": []}


def reviewed_namespace(chain):
    for path, observed in zip(chain, RECOVERY.REVIEWED_NAMESPACE):
        path.lstat.return_value = SimpleNamespace(st_mode=RECOVERY.stat.S_IFDIR | observed["mode"], st_uid=observed["uid"],
                                                 st_gid=observed["gid"], st_dev=observed["device"], st_ino=observed["inode"],
                                                 st_mtime_ns=observed["mtimeNs"], st_ctime_ns=observed["ctimeNs"])


def isolation_fixture():
    spec = importlib.util.spec_from_file_location("isolation_fixtures", ROOT / "ansible/tests/test_deployment_isolation.py")
    fixtures = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(fixtures)
    unused, snapshot = fixtures.proof()
    current = fixtures.process(3, 2, 25, uid=1001)
    snapshot["processes"].append(current)
    chain, body = storage_fixture()
    names = sorted(RECOVERY.PREFIX + "_" + service + "_data" for service in ("auth_database", "main_database"))
    nodes = [copy.deepcopy(body["nodes"][0]) for name in names]
    for index, node in enumerate(nodes):
        node["index"] = index
        node["directory"]["inode"] += index
        node["data"]["inode"] += index
    snapshot["storageProof"] = {"rootChain": chain, "hostMount": storage_mount_fixture(),
                                "volumeNames": names, "volumes": body["volumes"], "nodes": nodes}
    snapshot["containers"][0]["HostConfig"]["MountDeclarations"] = []
    snapshot["containers"][0]["Mounts"] = []
    snapshot["volumes"] = []
    return snapshot


class ProcTree:
    """An in-memory /proc double; forbidden files do not exist in this tree."""
    def __init__(self, snapshot):
        self.files, self.entries, self.reads = {}, {}, []
        self.entries["/proc"] = [str(process["pid"]) for process in snapshot["processes"]]
        for process in snapshot["processes"]:
            base = "/proc/" + str(process["pid"])
            self.files[base + "/stat"] = self.stat(process["pid"], process["ppid"], process["startTicks"])
            self.entries[base + "/task"] = [str(task["tid"]) for task in process["tasks"]]
            for task in process["tasks"]:
                directory = base + "/task/" + str(task["tid"])
                self.files[directory + "/stat"] = self.stat(task["tid"], task["ppid"], task["startTicks"])
                fields = {"Pid": task["tid"], "Tgid": task["tgid"], "PPid": task["ppid"], "NoNewPrivs": task["noNewPrivs"]}
                for source, target in (("uids", "Uid"), ("gids", "Gid"), ("groups", "Groups"), ("nsPid", "NSpid"), ("nsTgid", "NStgid")):
                    fields[target] = " ".join(map(str, task[source]))
                for key in ("capEff", "capPrm", "capInh", "capAmb", "capBnd"):
                    fields[key[0].upper() + key[1:]] = format(task[key], "016x")
                self.files[directory + "/status"] = "Name: private-name-not-collected\n" + "\n".join(f"{key}: {value}" for key, value in fields.items())
                self.files[directory + "/cgroup"] = "0::" + task["cgroupPath"] + "\n"

    @staticmethod
    def stat(pid, parent, start):
        return f"{pid} (private ) name) " + " ".join(["S", str(parent)] + ["0"] * 17 + [str(start)])

    def path(self, value):
        tree = self
        class Entry:
            def __init__(self, path):
                self.value, self.name = path, path.rsplit("/", 1)[-1]
            def __truediv__(self, child):
                return Entry(self.value + "/" + str(child))
            def iterdir(self):
                tree.reads.append(self.value)
                return iter(Entry(self.value + "/" + name) for name in tree.entries[self.value])
            def open(self, *args, **kwargs):
                tree.reads.append(self.value)
                value = tree.files[self.value]
                if isinstance(value, Exception):
                    raise value
                return io.StringIO(value() if callable(value) else value)
        return Entry(str(value))


def observed_shared_ancestor(chain):
    node = chain[2].lstat.return_value
    node.st_uid, node.st_gid, node.st_mode = 1000, 1010, RECOVERY.stat.S_IFDIR | 0o775
    node.st_dev, node.st_ino, node.st_mtime_ns, node.st_ctime_ns = 2049, 3161822, 400, 500


def status_only_path(statuses):
    class StatusPath:
        def __init__(self, value):
            self.value = str(value)
            self.name = self.value.rsplit("/", 1)[-1]
        def __truediv__(self, value):
            return StatusPath(self.value + "/" + str(value))
        def iterdir(self):
            if self.value != "/proc":
                raise AssertionError("Diagnostic accessed a directory other than /proc")
            return (StatusPath("/proc/" + str(pid)) for pid in statuses)
        def open(self, mode, *, encoding):
            if not self.value.endswith("/status"):
                raise AssertionError("Diagnostic accessed non-status process data")
            return io.StringIO(statuses[int(self.value.split("/")[2])])
        def exists(self):
            return True
    return StatusPath


class WorkflowAndPlaybookTests(unittest.TestCase):
    """Applied CI reads the real bin helper and tracked YAML, never draft snapshots."""

    def setUp(self):
        # BaseLoader retains the GitHub Actions key 'on' as text under YAML 1.1.
        self.workflow = yaml.load(WORKFLOW.read_text(encoding="utf-8"), Loader=yaml.BaseLoader)
        self.play = yaml.safe_load(PLAYBOOK.read_text(encoding="utf-8"))[0]
        self.tasks = self.play["tasks"]
        self.recovery = self.task("Recover only the explicitly reviewed historical development operation lock")
        self.jinja = Environment(undefined=StrictUndefined)

    def task(self, name, entries=None):
        matches = [entry for entry in (self.tasks if entries is None else entries) if entry.get("name") == name]
        self.assertEqual(1, len(matches), name)
        return matches[0]

    def step(self, job, name):
        return self.task(name, self.workflow["jobs"][job]["steps"])

    def identity(self, **changes):
        values = {"fireguard_reviewed_lock_recovery_run_id": RECOVERY.CANDIDATE,
                  "fireguard_deployment_environment": "development", "fireguard_app_dir": RECOVERY.APP_DIR,
                  "fireguard_compose_project_name": RECOVERY.PROJECT, "fireguard_reset_development_fixtures": False}
        values.update(changes)
        return values

    def evaluate(self, expression, values):
        return self.jinja.compile_expression(expression)(**values)

    def test_applied_tests_import_bin_and_actual_yaml_without_snapshot_fallback(self):
        self.assertEqual(HELPER.resolve(), Path(RECOVERY.__file__).resolve())
        if DRAFT.name == "tests" and DRAFT.parent.name == "ansible":
            self.assertEqual(ROOT / "bin/fireguard-deployment-recovery.py", HELPER)
            self.assertEqual(ROOT / ".github/workflows/deploy-vps.yml", WORKFLOW)
            self.assertEqual(ROOT / "ansible/deploy.yml", PLAYBOOK)
        self.assertIn("workflow_dispatch", self.workflow["on"])

    def test_empty_default_uses_original_raw_mkdir_and_never_manual_recovery(self):
        requested = self.workflow["on"]["workflow_dispatch"]["inputs"]["reviewed_lock_recovery_run_id"]
        self.assertEqual("false", requested["required"])
        self.assertEqual("string", requested["type"])
        self.assertEqual("", requested.get("default", ""))
        self.assertEqual("{{ lookup('env', 'REVIEWED_LOCK_RECOVERY_RUN_ID') | default('', true) }}",
                         self.play["vars"]["fireguard_reviewed_lock_recovery_run_id"])
        acquire = self.task("Acquire the installation operation lock before changing runtime configuration")
        self.assertEqual({"argv": ["mkdir", "{{ fireguard_app_dir }}/.fireguard-operation.lock"]},
                         acquire["ansible.builtin.command"])
        self.assertEqual("fireguard_reviewed_lock_recovery_run_id | length == 0", acquire["when"])
        self.assertEqual("fireguard_reviewed_lock_recovery_run_id | length > 0", self.recovery["when"])
        for value, ordinary, reviewed in [("", True, False), (RECOVERY.CANDIDATE, False, True)]:
            context = {"fireguard_reviewed_lock_recovery_run_id": value}
            with self.subTest(value=value):
                self.assertEqual(ordinary, self.evaluate(acquire["when"], context))
                self.assertEqual(reviewed, self.evaluate(self.recovery["when"], context))
        self.assertEqual({"name", "ansible.builtin.command", "when"}, set(acquire))
        self.assertLess(self.tasks.index(acquire), self.tasks.index(self.task("Copy base Docker Compose file")))

    def test_manual_identity_asserts_reject_each_other_installation_and_rollback_or_reset(self):
        assertion = self.task("Validate the fixed development recovery identity", self.recovery["block"])
        conditions = assertion["ansible.builtin.assert"]["that"]
        self.assertEqual(7, len(conditions))
        def accepted(values, prefix=RECOVERY.PREFIX, image=""):
            values = values | {"lookup": lambda kind, name: {"VOLUME_PREFIX": prefix, "IMAGE_INPUT": image}[name]}
            return all(self.evaluate(condition, values) for condition in conditions)
        self.assertTrue(accepted(self.identity()))
        for key, value in [("fireguard_reviewed_lock_recovery_run_id", "37154241435"),
                           ("fireguard_deployment_environment", "production"),
                           ("fireguard_app_dir", "/srv/apps/fireguard/production/back"),
                           ("fireguard_compose_project_name", "other"),
                           ("fireguard_reset_development_fixtures", True)]:
            with self.subTest(field=key):
                self.assertFalse(accepted(self.identity(**{key: value})))
        self.assertFalse(accepted(self.identity(), prefix="other"))
        self.assertFalse(accepted(self.identity(), image=IMAGE))
        self.assertEqual(assertion, self.recovery["block"][0], "Identity must precede every recovery operation")

    def test_manual_workflow_requires_fixed_incident_develop_fresh_image_and_no_reset(self):
        option = self.step("guard", "Validate rollback option")
        self.assertEqual("${{ github.event_name == 'workflow_dispatch' }}", option["if"])
        self.assertEqual("${{ inputs.reviewed_lock_recovery_run_id }}", option["env"]["REVIEWED_LOCK_RECOVERY_RUN_ID"])
        self.assertEqual("${{ steps.context.outputs.deploy-environment }}", option["env"]["DEPLOY_ENVIRONMENT"])
        self.assertIn('if [ -n "$REVIEWED_LOCK_RECOVERY_RUN_ID" ]; then', option["run"])
        self.assertIn('test "$REVIEWED_LOCK_RECOVERY_RUN_ID" = \'37080669400\' &&\n'
                      '  test "$SOURCE_BRANCH" = develop && test "$DEPLOY_ENVIRONMENT" = development &&\n'
                      '  test -z "$IMAGE_INPUT" && test "$RESET_DEVELOPMENT_FIXTURES" = false || {', option["run"])
        provenance = self.step("deploy", "Verify reviewed development lock recovery provenance")
        self.assertEqual("${{ inputs.reviewed_lock_recovery_run_id != '' }}", provenance["if"])
        self.assertEqual({"GH_TOKEN": "${{ github.token }}"}, provenance["env"])
        self.assertEqual("python3 -B bin/fireguard-deployment-recovery.py provenance --output .deploy/lock-recovery-proof.json",
                         provenance["run"])
        self.assertLess(self.workflow["jobs"]["deploy"]["steps"].index(provenance),
                        self.workflow["jobs"]["deploy"]["steps"].index(self.step("deploy", "Configure SSH")))

    def test_existing_ci_sonar_source_environment_and_digest_gates_are_preserved(self):
        readiness = self.step("guard", "Require SonarQube deployment readiness")
        sonar = "${{ vars[steps.context.outputs.source-branch == 'main' && 'SONAR_READY_MAIN' || 'SONAR_READY_DEVELOP'] }}"
        self.assertEqual({"SONAR_DEPLOYMENTS_READY": sonar}, readiness["env"])
        self.assertIn('test "$SONAR_DEPLOYMENTS_READY" = \'true\' || {', readiness["run"])
        self.assertIn("exit 1", readiness["run"])
        verify = self.step("guard", "Verify exact CI and SonarQube gate")
        self.assertEqual("node .github/scripts/verify-ci.mjs", verify["run"])
        self.assertEqual({"GH_TOKEN": "${{ github.token }}", "SOURCE_BRANCH": "${{ steps.context.outputs.source-branch }}",
                          "SOURCE_SHA": "${{ steps.image.outputs.source-sha || steps.context.outputs.source-sha }}",
                          "EXPECTED_RUN_ID": "${{ github.event_name == 'workflow_run' && steps.context.outputs.run-id || inputs.source_run_id }}",
                          "SONAR_PROJECT_PREFIX": "fireguard-api", "SONAR_DEPLOYMENTS_READY": sonar}, verify["env"])
        deploy = self.workflow["jobs"]["deploy"]
        self.assertEqual(["guard", "build"], deploy["needs"])
        self.assertEqual("${{ always() && github.event_name == 'workflow_dispatch' && needs.guard.result == 'success' && "
                         "needs.guard.outputs.should-deploy == 'true' && (needs.build.result == 'success' || "
                         "(needs.build.result == 'skipped' && needs.guard.outputs.image-ref != '')) }}",
                         " ".join(deploy["if"].split()))
        self.assertEqual("${{ needs.guard.outputs.deploy-environment }}", deploy["environment"])
        self.assertEqual("${{ needs.guard.outputs.source-sha }}", deploy["env"]["SOURCE_SHA"])
        self.assertEqual("${{ needs.guard.outputs.image-ref || needs.build.outputs.image-ref }}", deploy["env"]["IMAGE_REF"])
        self.assertEqual("${{ needs.guard.outputs.verified-run-id }}", deploy["env"]["VERIFIED_CI_RUN_ID"])
        self.assertEqual("false", self.workflow["concurrency"]["cancel-in-progress"])
        image = self.task("Validate required deployment image", self.play["pre_tasks"])
        self.assertIn("fireguard_image is match('^[a-z0-9][a-z0-9._/:+-]+@sha256:[a-f0-9]{64}$')",
                      image["ansible.builtin.assert"]["that"])
        metadata = self.step("build", "Build and push Docker image")["with"]["labels"]
        self.assertIn("org.opencontainers.image.revision=${{ needs.guard.outputs.source-sha }}", metadata)
        selected = self.step("build", "Select deployed image")["run"]
        self.assertIn('test -n "$PROD_DIGEST"', selected)
        self.assertIn('echo "ref=${image_name}@${PROD_DIGEST}" >> "$GITHUB_OUTPUT"', selected)

    def test_recovery_only_registry_credentials_fail_closed_and_use_private_stdin_before_helper(self):
        entries = self.recovery["block"]
        validate = self.task("Require current registry credentials for reviewed image inspection", entries)
        login = self.task("Authenticate current GHCR credentials only for reviewed recovery", entries)
        inspect = self.task("Verify quiescence and histories then reacquire the reviewed mutex once", entries)
        self.assertIs(True, validate["no_log"])
        self.assertIs(True, login["no_log"])
        conditions = validate["ansible.builtin.assert"]["that"]
        self.assertEqual(["ghcr_username | length > 0", "ghcr_token | length > 0"], conditions)
        for username, token, valid in [("actor", "fixture", True), ("", "fixture", False), ("actor", "", False)]:
            self.assertEqual(valid, all(self.evaluate(condition, {"ghcr_username": username, "ghcr_token": token})
                                        for condition in conditions))
        self.assertEqual({"argv": ["docker", "login", "ghcr.io", "--username", "{{ ghcr_username }}", "--password-stdin"],
                          "stdin": "{{ ghcr_token }}"}, login["ansible.builtin.command"])
        self.assertLess(entries.index(entries[0]), entries.index(validate))
        self.assertLess(entries.index(validate), entries.index(login))
        self.assertLess(entries.index(login), entries.index(inspect))
        for task in [validate, login, inspect]:
            self.assertNotIn("when", task, "Missing credentials must abort, never silently skip login")
            self.assertNotIn("ignore_errors", task)
            self.assertNotIn("failed_when", task)
        self.assertNotIn("ghcr", json.dumps(inspect).lower())
        self.assertNotIn("environment", inspect, "Credentials never reach the helper")
        self.assertEqual("{{ lookup('env', 'GHCR_TOKEN') | default('', true) }}", self.play["vars"]["ghcr_token"])
        normal = self.task("Log in to GHCR on VPS")
        self.assertEqual({"cmd": "docker login ghcr.io -u {{ ghcr_username }} --password-stdin",
                          "stdin": "{{ ghcr_token }}"}, normal["ansible.builtin.command"])
        self.assertIs(True, normal["no_log"])
        self.assertEqual(["ghcr_username | length > 0", "ghcr_token | length > 0"], normal["when"])
        self.assertLess(self.tasks.index(self.task("Copy base Docker Compose file")), self.tasks.index(normal))

    def test_partial_recovery_cannot_continue_rollout_or_remove_the_new_mutex(self):
        self.assertNotIn("rescue", self.recovery)
        self.assertNotIn("ignore_errors", self.recovery)
        inspect = self.task("Verify quiescence and histories then reacquire the reviewed mutex once", self.recovery["block"])
        self.assertNotIn("failed_when", inspect)
        confirm = self.task("Require acquisition success before any rollout task", self.recovery["block"])
        self.assertEqual(["(fireguard_reviewed_recovery.stdout | from_json).result == 'reviewed-lock-reacquired-awaiting-rollout'"],
                         confirm["ansible.builtin.assert"]["that"])
        self.assertEqual(confirm, self.recovery["block"][-1])
        cleanup = self.recovery["always"]
        self.assertEqual(1, len(cleanup))
        self.assertEqual({"path": "{{ fireguard_recovery_staging.path }}", "state": "absent"}, cleanup[0]["ansible.builtin.file"])
        self.assertNotIn(".fireguard-operation.lock", json.dumps(cleanup))
        self.assertLess(self.tasks.index(self.recovery), self.tasks.index(self.task("Copy base Docker Compose file")))


class ProvenanceTests(unittest.TestCase):
    def test_valid_exact_candidate_and_ci_produce_public_manifest_without_token(self):
        data = RECOVERY.provenance(environment() | {"GH_TOKEN": "DO_NOT_EXPORT"}, FakeAPI(), ROOT, FakeGit())
        self.assertEqual(MANIFEST, data["migrations"])
        self.assertEqual(RECOVERY.CANDIDATE, data["candidateRunId"])
        self.assertNotIn("DO_NOT_EXPORT", json.dumps(data))

    def test_rejects_auto_prod_reset_rollback_arbitrary_id_repo_sha_and_missing_gate(self):
        invalid = {"GITHUB_EVENT_NAME": "workflow_run", "SOURCE_BRANCH": "main", "DEPLOY_ENVIRONMENT": "production",
                   "REVIEWED_LOCK_RECOVERY_RUN_ID": "1", "GITHUB_REPOSITORY": "other/repo", "IMAGE_INPUT": "old:image",
                   "RESET_DEVELOPMENT_FIXTURES": "true", "SOURCE_SHA": "wrong", "IMAGE_REF": "ghcr.io/devskylex/fireguard-api:develop",
                   "VERIFIED_CI_RUN_ID": "", "GITHUB_RUN_ID": RECOVERY.CANDIDATE}
        for key, value in invalid.items():
            with self.subTest(field=key), self.assertRaises(RECOVERY.RecoveryBlocked):
                RECOVERY.provenance(environment() | {key: value}, FakeAPI(), ROOT, FakeGit())

    def test_rejects_changed_branch_or_foreign_failed_source(self):
        for object_name, field, value in [("old", "head_sha", SOURCE), ("old", "head_branch", "main"),
                                          ("old", "status", "in_progress"), ("old", "conclusion", "success"),
                                          ("old", "run_attempt", 2), ("old", "event", "pull_request"),
                                          ("current", "path", ".github/workflows/other.yml"),
                                          ("ci", "head_sha", RECOVERY.FAILED_SHA), ("ci", "conclusion", "failure")]:
            api = FakeAPI()
            getattr(api, object_name)[field] = value
            with self.subTest(object=object_name, field=field), self.assertRaises(RECOVERY.RecoveryBlocked):
                RECOVERY.provenance(environment(), api, ROOT, FakeGit())
        api = FakeAPI()
        api.tip = RECOVERY.FAILED_SHA
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "source-no-longer-branch-tip"):
            RECOVERY.provenance(environment(), api, ROOT, FakeGit())

    def test_rejects_duplicate_jobs_incomplete_sonar_or_non_ansible_failure(self):
        for mutation in [lambda api: api.jobs.append(copy.deepcopy(api.jobs[0])),
                         lambda api: api.jobs[0]["steps"][0].update(conclusion="success"),
                         lambda api: api.gates.append(copy.deepcopy(api.gates[0])),
                         lambda api: api.gates[0]["steps"].pop(),
                         lambda api: api.gates[0]["steps"][0].update(conclusion="skipped")]:
            api = FakeAPI()
            mutation(api)
            with self.subTest(mutation=mutation), self.assertRaises(RECOVERY.RecoveryBlocked):
                RECOVERY.provenance(environment(), api, ROOT, FakeGit())

    def test_any_other_queued_waiting_or_running_develop_deploy_blocks(self):
        for status in RECOVERY.ACTIVE:
            api = FakeAPI()
            api.runs.append({"id": 999, "status": status})
            with self.subTest(status=status), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "concurrent-development-deployment"):
                RECOVERY.provenance(environment(), api, ROOT, FakeGit())

    def test_git_ancestor_head_and_immutable_history_are_required(self):
        for field, value in [("ancestor", False), ("head", RECOVERY.FAILED_SHA),
                             ("changes", "M\tmigrations/main/" + VERSION + ".php\n"),
                             ("changes", "D\tmigrations/auth/" + VERSION + ".php\n"),
                             ("changes", "M\tconfig/migrations/main.yaml\n")]:
            git = FakeGit()
            setattr(git, field, value)
            with self.subTest(field=field, value=value), self.assertRaises(RECOVERY.RecoveryBlocked):
                RECOVERY.provenance(environment(), FakeAPI(), ROOT, git)

    def test_complete_github_pagination_is_required(self):
        api = RECOVERY.GitHub("DO_NOT_EXPORT")
        with patch.object(api, "get", side_effect=[{"jobs": [0] * 100}, {"jobs": [1]}]) as getter:
            self.assertEqual(101, len(api.pages("/fixture", "jobs")))
            self.assertTrue(getter.call_args.args[0].endswith("page=2"))
        with patch.object(api, "get", return_value={"jobs": [0] * 100}):
            with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "github-pagination-incomplete"):
                api.pages("/fixture", "jobs")


class RecoveryTests(unittest.TestCase):
    def test_verified_empty_historical_lock_released_without_writer_start_or_db_write(self):
        host = FakeHost()
        self.assertEqual("reviewed-lock-reacquired-awaiting-rollout", recover(host)["result"])
        self.assertTrue(host.released)
        self.assertTrue(host.reacquired)
        self.assertEqual(2, host.lock_reads)
        self.assertEqual(2, host.process_reads)
        self.assertEqual(2, host.container_reads)

    def test_identity_candidate_and_canonical_environment_are_mandatory(self):
        for fields in [{"app_dir": "/srv/apps/fireguard/production/back"}, {"project": "other"}, {"prefix": "back"}]:
            host = FakeHost()
            with self.subTest(fields=fields), self.assertRaises(RECOVERY.RecoveryBlocked):
                recover(host, **fields)
            self.assertFalse(host.released)
        for key, value in [("candidateRunId", "1"), ("image", "untrusted:image"), ("sourceSha", "invalid"),
                           ("migrations", {}), ("currentRunId", RECOVERY.CANDIDATE)]:
            evidence = proof()
            evidence[key] = value
            host = FakeHost()
            with self.subTest(key=key), self.assertRaises(RECOVERY.RecoveryBlocked):
                recover(host, evidence)
            self.assertFalse(host.released)

    def test_old_age_does_not_authorize_a_lock_outside_the_reviewed_window(self):
        for field in ["mtimeNs", "ctimeNs"]:
            host = FakeHost()
            host.identity[field] = 1
            with self.subTest(field=field), self.assertRaisesRegex(RECOVERY.LockInspectionBlocked, "lock-not-from-reviewed") as caught:
                recover(host)
            self.assertEqual(host.identity | {"appDir": RECOVERY.APP_DIR, "expectedStartSeconds": proof()["lockWindow"][0],
                                              "expectedEndSeconds": proof()["lockWindow"][1]}, caught.exception.diagnostic)
            self.assertEqual(caught.exception.diagnostic, json.loads(str(caught.exception).split(" ", 1)[1]))
            self.assertEqual(0, host.process_reads)
            self.assertEqual(0, host.container_reads)
            self.assertFalse(host.released)

    def test_any_concurrent_owner_or_process_holding_app_path_blocks(self):
        for comm, cwd, fds in [("python3", "/tmp", []), ("sh", "/tmp", []), ("restic", "/tmp", []),
                               ("docker", "/tmp", []), ("other", RECOVERY.APP_DIR, []),
                               ("other", "/tmp", [RECOVERY.APP_DIR + "/compose.yaml"])]:
            host = FakeHost()
            host.process_records.append({"pid": 4, "ppid": 1, "uid": MEMBER, "comm": comm, "cwd": cwd, "fds": fds})
            with self.subTest(comm=comm, cwd=cwd), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "concurrent-host-owner"):
                recover(host)
            self.assertFalse(host.released)

    def test_unknown_own_process_ancestry_and_permissions_fail_closed(self):
        host = FakeHost()
        host.process_records.pop(1)
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "own-process-ancestry"):
            recover(host)
        self.assertFalse(host.released)
        host = FakeHost()
        with patch.object(host, "processes", side_effect=PermissionError("PRIVATE_ERROR")):
            with self.assertRaises(PermissionError):
                recover(host)
        self.assertFalse(host.released)

    def test_all_writers_must_be_stopped_oneoffs_absent_and_dependencies_identified(self):
        for mutation in [lambda host: host.container_records[0].update(running=True, status="running"),
                         lambda host: host.container_records[0].update(restarting=True),
                         lambda host: host.container_records[0].update(status="created"),
                         lambda host: host.container_records.pop(),
                         lambda host: host.container_records[0].update(workingDir="/other"),
                         lambda host: host.container_records[1].update(oneoff="True"),
                         lambda host: next(value for value in host.container_records if value["service"] == "auth_database").update(mounts=[])]:
            host = FakeHost()
            mutation(host)
            with self.subTest(mutation=mutation), self.assertRaises(RECOVERY.RecoveryBlocked):
                recover(host)
            self.assertFalse(host.released)

    def test_stopped_writer_refusal_reports_only_five_fixed_service_state_counts_in_both_phases(self):
        def absent(host):
            host.container_records[:] = [item for item in host.container_records if item["service"] != "app"]
        def duplicate(host):
            item = copy.deepcopy(next(item for item in host.container_records if item["service"] == "app"))
            item.update(id="PRIVATE_ID", status="created")
            host.container_records.append(item)
        def state(value):
            return lambda host: next(item for item in host.container_records if item["service"] == "app").update(status=value)
        def oneoff(host):
            next(item for item in host.container_records if item["service"] == "app").update(oneoff="True")
        cases = ((absent, 0, {}), (duplicate, 2, {"exited": 1, "created": 1}), (state("created"), 1, {"created": 1}),
                 (state("dead"), 1, {"dead": 1}), (state("PRIVATE_STATE"), 1, {"unknown": 1}),
                 (state({"PRIVATE_KEY": "PRIVATE_VALUE"}), 1, {"unknown": 1}), (oneoff, 0, {}))
        for phase in (1, 2):
            for mutation, match_count, expected_counts in cases:
                host = FakeHost()
                foreign = container("app", "PRIVATE_FOREIGN_ID")
                foreign.update(project="PRIVATE_PROJECT", status="PRIVATE_STATE")
                host.container_records.append(foreign)
                host.container_records.append(container("PRIVATE_SERVICE", "PRIVATE_ID"))
                if phase == 1:
                    mutation(host)
                else:
                    host.before_final_containers = mutation
                with self.subTest(phase=phase, match_count=match_count, counts=expected_counts), \
                     self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "^stopped-writer-state-unverified ") as caught:
                    recover(host)
                public = str(caught.exception)
                self.assertNotIn("PRIVATE", public)
                diagnostics = json.loads(public.split(" ", 1)[1])
                self.assertEqual({"writers"}, set(diagnostics))
                self.assertEqual(sorted(RECOVERY.WRITERS), [item["service"] for item in diagnostics["writers"]])
                for item in diagnostics["writers"]:
                    self.assertEqual({"service", "matchCount", "statusCounts", "countsTruncated"}, set(item))
                    self.assertEqual(set(RECOVERY.WRITER_STATUSES), set(item["statusCounts"]))
                    self.assertFalse(item["countsTruncated"])
                    wanted = expected_counts if item["service"] == "app" else {"exited": 1}
                    self.assertEqual(wanted, {status: count for status, count in item["statusCounts"].items() if count})
                    self.assertEqual(match_count if item["service"] == "app" else 1, item["matchCount"])
                self.assertEqual(phase, host.container_reads)
                self.assertFalse(host.released)
                self.assertFalse(host.reacquired)

    def test_writer_diagnostics_bound_counts_and_map_unknown_states_without_free_values(self):
        item = container("app", "PRIVATE_ID")
        item["status"] = "PRIVATE_STATE"
        diagnostics = RECOVERY.writer_state_diagnostics([item] * 32769)
        app = next(item for item in diagnostics if item["service"] == "app")
        self.assertEqual(32768, app["matchCount"])
        self.assertEqual(32768, app["statusCounts"]["unknown"])
        self.assertTrue(app["countsTruncated"])
        self.assertNotIn("PRIVATE", json.dumps(diagnostics))

    def test_running_foreign_project_sharing_development_volume_blocks(self):
        host = FakeHost()
        foreign = container("other", "e")
        foreign.update(project="foreign", running=True, mounts=[{"name": RECOVERY.PREFIX + "_app_var", "source": "/unused"}])
        host.container_records.append(foreign)
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "foreign-writer-mounts"):
            recover(host)
        self.assertFalse(host.released)

    def test_foreign_bind_exact_child_or_parent_of_installation_blocks(self):
        sources = [RECOVERY.APP_DIR, RECOVERY.APP_DIR + "/data", "/srv/apps/fireguard/development",
                   "/srv/apps/fireguard", "/srv", "/", RECOVERY.APP_DIR + "/./data/..", RECOVERY.APP_DIR + "/"]
        for source in sources:
            host = FakeHost()
            foreign = container("foreign", "e")
            foreign.update(project="foreign", running=True, mounts=[{"name": "", "source": source}])
            host.container_records.append(foreign)
            with self.subTest(source=source), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "foreign-writer-mounts"):
                recover(host)
            self.assertFalse(host.released)

    def test_foreign_bind_alias_to_scoped_physical_volume_source_or_parent_blocks(self):
        physical = "/var/lib/docker/volumes/" + RECOVERY.PREFIX + "_app_var/_data"
        for source in [physical, physical + "/cache", physical.rsplit("/", 1)[0], "/var/lib/docker/volumes", "/var/lib/docker"]:
            host = FakeHost()
            owned = next(item for item in host.container_records if item["service"] == "app")
            owned["mounts"] = [{"name": RECOVERY.PREFIX + "_app_var", "source": physical}]
            foreign = container("foreign", "e")
            foreign.update(project="foreign", running=True, mounts=[{"name": "", "source": source}])
            host.container_records.append(foreign)
            with self.subTest(source=source), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "foreign-writer-mounts"):
                recover(host)
            self.assertFalse(host.released)

    def test_foreign_sibling_storage_and_stopped_foreign_bind_do_not_block(self):
        for source, running in [(RECOVERY.APP_DIR + "-other", True), ("/srv/apps/fireguard/production/back", True),
                                ("/var/lib/docker/volumes/fixture-other", True), (RECOVERY.APP_DIR, False)]:
            host = FakeHost()
            foreign = container("foreign", "e")
            foreign.update(project="foreign", running=running, mounts=[{"name": "", "source": source}])
            host.container_records.append(foreign)
            with self.subTest(source=source, running=running):
                recover(host)
            self.assertTrue(host.reacquired)

    def test_missing_scoped_physical_source_preserves_lock(self):
        host = FakeHost()
        owned = next(item for item in host.container_records if item["service"] == "auth_database")
        owned["mounts"][0].pop("source")
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "mount-source-path-unverified"):
            recover(host)
        self.assertFalse(host.released)

    def test_unknown_applied_history_or_modified_image_manifest_preserves_lock(self):
        for history in ["auth", "main"]:
            host = FakeHost()
            host.histories[history].append(RECOVERY.SCHEMA_PREFIX[history] + "Version20000101000000")
            with self.subTest(history=history), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "executed-migration-unavailable"):
                recover(host)
            self.assertFalse(host.released)
        host = FakeHost()
        host.manifest["main"][RECOVERY.SCHEMA_PREFIX["main"] + VERSION] = "0" * 64
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "image-migration-manifest"):
            recover(host)
        self.assertFalse(host.released)

    def test_new_forward_migrations_are_allowed_without_applying_them_in_helper(self):
        evidence = proof()
        host = FakeHost()
        next_version = RECOVERY.SCHEMA_PREFIX["main"] + "Version20261003130000"
        evidence["migrations"]["main"][next_version] = "0" * 64
        host.manifest["main"][next_version] = "0" * 64
        recover(host, evidence)
        self.assertTrue(host.released)
        self.assertNotIn(next_version, host.histories["main"])

    def test_rechecks_inode_times_processes_and_database_identity_after_inspection(self):
        mutations = [lambda host: setattr(host, "before_final_lock", lambda item: item.identity.update(inode=43)),
                     lambda host: setattr(host, "before_final_lock", lambda item: item.identity.update(ctimeNs=1)),
                     lambda host: setattr(host, "before_final_process", lambda item: item.process_records.append(
                         {"pid": 4, "ppid": 1, "uid": MEMBER, "comm": "sh", "cwd": "/tmp", "fds": []})),
                     lambda host: setattr(host, "before_final_containers", lambda item: next(
                         value for value in item.container_records if value["service"] == "auth_database").update(id="e" * 64))]
        for mutation in mutations:
            host = FakeHost()
            mutation(host)
            with self.subTest(mutation=mutation), self.assertRaises(RECOVERY.RecoveryBlocked):
                recover(host)
            self.assertFalse(host.released)


class ReviewedLegacyLockTests(unittest.TestCase):
    def test_only_exact_observed_lock_with_protected_namespace_completes_existing_recovery(self):
        with legacy_fixture() as (app, lock, chain):
            identity = RECOVERY.Host().lock_identity()
            self.assertEqual(RECOVERY.REVIEWED_LEGACY_LOCK["inode"], identity["inode"])
            self.assertEqual(1001, identity["reviewedLegacy"]["gid"])
            self.assertRegex(identity["reviewedLegacy"]["namespaceSha256"], r"^[a-f0-9]{64}$")
            self.assertEqual(list(range(6)), [item["depth"] for item in identity["reviewedLegacy"]["namespace"]])
            self.assertTrue(all(type(value) is int for item in identity["reviewedLegacy"]["namespace"] for value in item.values()))
            host = legacy_host()
            self.assertEqual("reviewed-lock-reacquired-awaiting-rollout", recover(host, current_uid=1001)["result"])
            self.assertTrue(host.reacquired)
            self.assertEqual(2, host.process_reads)
            self.assertEqual(2, host.container_reads)
            for path in chain:
                self.assertEqual(3, path.lstat.call_count, "Identity and namespace rechecked before release")
            app.read_text.assert_not_called()

    def test_every_observed_pin_field_is_required_without_enumerating_or_unlocking(self):
        fields = {"st_dev": 2050, "st_ino": 3149774, "st_uid": 1002, "st_gid": 1002,
                  "st_mtime_ns": RECOVERY.REVIEWED_LEGACY_LOCK["mtimeNs"] + 1,
                  "st_ctime_ns": RECOVERY.REVIEWED_LEGACY_LOCK["ctimeNs"] + 1,
                  "st_mode": RECOVERY.stat.S_IFDIR | 0o777}
        for field, value in fields.items():
            with self.subTest(field=field), legacy_fixture() as (app, lock, chain):
                setattr(lock.lstat.return_value, field, value)
                host = legacy_host()
                with self.assertRaisesRegex(RECOVERY.LockInspectionBlocked, "lock-identity-unverified"):
                    recover(host, current_uid=1001)
                self.assertFalse(host.released)
                self.assertEqual(0, host.process_reads)
                lock.iterdir.assert_not_called()

    def test_only_exact_0775_permission_case_can_use_exception(self):
        for mode in [0o773, 0o777, 0o2775, 0o1775, 0o757, 0o770]:
            with self.subTest(mode=mode), legacy_fixture() as (app, lock, chain):
                lock.lstat.return_value.st_mode = RECOVERY.stat.S_IFDIR | mode
                with self.assertRaises(RECOVERY.LockInspectionBlocked):
                    RECOVERY.Host().lock_identity()
                lock.iterdir.assert_not_called()

    def test_real_effective_uid_and_primary_effective_gid_must_match_observed_owner(self):
        for field in ["uid", "euid", "gid", "egid"]:
            with self.subTest(field=field), legacy_fixture(**{field: 1002}) as (app, lock, chain):
                with self.assertRaises(RECOVERY.LockInspectionBlocked):
                    RECOVERY.Host().lock_identity()
                lock.iterdir.assert_not_called()

    def test_each_ancestor_must_be_real_protected_and_owned_only_by_root_or_deployment_uid(self):
        for depth in range(6):
            for field, value in [("st_uid", 1002), ("st_mode", RECOVERY.stat.S_IFDIR | 0o775),
                                 ("st_mode", RECOVERY.stat.S_IFDIR | 0o757),
                                 ("st_mode", RECOVERY.stat.S_IFREG | 0o755), ("st_mode", RECOVERY.stat.S_IFLNK | 0o755)]:
                with self.subTest(depth=depth, field=field, value=value), legacy_fixture() as (app, lock, chain):
                    setattr(chain[depth].lstat.return_value, field, value)
                    host = legacy_host()
                    with self.assertRaises(RECOVERY.LockInspectionBlocked) as caught:
                        recover(host, current_uid=1001)
                    self.assertEqual(depth, caught.exception.diagnostic["namespaceRefusalDepth"])
                    self.assertFalse(host.released)
                    lock.iterdir.assert_not_called()
        with legacy_fixture() as (app, lock, chain):
            app.lstat.return_value.st_uid = 0
            with self.assertRaises(RECOVERY.LockInspectionBlocked):
                RECOVERY.Host().lock_identity()

    def test_each_symlink_alias_or_missing_ancestor_metadata_blocks_before_release(self):
        for depth in range(6):
            for kind in ["symlink", "permission"]:
                with self.subTest(depth=depth, kind=kind), legacy_fixture() as (app, lock, chain):
                    if kind == "symlink":
                        chain[depth].is_symlink.return_value = True
                    else:
                        chain[depth].lstat.side_effect = PermissionError("PRIVATE_PERMISSION")
                    host = legacy_host()
                    with self.assertRaises((RECOVERY.RecoveryBlocked, PermissionError)):
                        recover(host, current_uid=1001)
                    self.assertFalse(host.released)
                    lock.iterdir.assert_not_called()

    def test_namespace_identity_or_security_change_at_final_review_never_releases(self):
        for field, value in [("st_ino", 9000), ("st_uid", 1002), ("st_mode", RECOVERY.stat.S_IFDIR | 0o775)]:
            with self.subTest(field=field), legacy_fixture() as (app, lock, chain):
                host = legacy_host()
                host.before_final_process = lambda unused: setattr(chain[2].lstat.return_value, field, value)
                with self.assertRaises(RECOVERY.RecoveryBlocked):
                    recover(host, current_uid=1001)
                self.assertFalse(host.released)

    def test_lock_change_at_final_review_never_releases_even_within_original_window(self):
        with legacy_fixture() as (app, lock, chain):
            host = legacy_host()
            host.before_final_process = lambda unused: setattr(lock.lstat.return_value, "st_ctime_ns",
                                                               RECOVERY.REVIEWED_LEGACY_LOCK["ctimeNs"] + 1)
            with self.assertRaises(RECOVERY.LockInspectionBlocked):
                recover(host, current_uid=1001)
            self.assertFalse(host.released)

    def test_peer_real_effective_saved_fs_uid_or_gid_and_supplementary_group_blocks(self):
        for kind in ["uids", "gids", "groups", "mixed_owner"]:
            for index in range(4 if kind in {"uids", "gids"} else 1):
                with self.subTest(kind=kind, index=index), legacy_fixture():
                    host = legacy_host()
                    peer = foreign_peer()
                    if kind in {"uids", "gids"}:
                        peer[kind][index] = 1001
                        peer["uid"] = peer["uids"][0]
                    elif kind == "groups":
                        peer["groups"] = [1001]
                    else:
                        peer.update(uid=1001, uids=[1001, 1001, 1002, 1001])
                    host.process_records.append(peer)
                    with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "peer-group-writer"):
                        recover(host, current_uid=1001)
                    self.assertFalse(host.released)

    def test_peer_group_permission_appearing_before_final_review_blocks(self):
        with legacy_fixture():
            host = legacy_host()
            peer = foreign_peer() | {"groups": [1001]}
            host.before_final_process = lambda value: value.process_records.append(peer)
            with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "peer-group-writer"):
                recover(host, current_uid=1001)
            self.assertFalse(host.released)

    def test_missing_or_ambiguous_peer_credentials_block_without_release(self):
        mutations = [lambda peer: peer.pop("uids"), lambda peer: peer.pop("gids"), lambda peer: peer.pop("groups"),
                     lambda peer: peer.update(uids=[1002]), lambda peer: peer.update(gids=[1002]),
                     lambda peer: peer.update(groups=["1001"]), lambda peer: peer.update(groups=[-1]),
                     lambda peer: peer.update(uid=1003)]
        for mutation in mutations:
            with self.subTest(mutation=mutation), legacy_fixture():
                host = legacy_host()
                peer = foreign_peer()
                mutation(peer)
                host.process_records.append(peer)
                with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "peer-group-metadata-unverified"):
                    recover(host, current_uid=1001)
                self.assertFalse(host.released)

    def test_unrelated_foreign_peers_remain_allowed_and_same_uid_owner_checks_stay_active(self):
        with legacy_fixture():
            host = legacy_host()
            host.process_records.append(foreign_peer())
            recover(host, current_uid=1001)
            self.assertTrue(host.reacquired)
        for comm, cwd in [("python3", "/"), ("other", RECOVERY.APP_DIR)]:
            with self.subTest(comm=comm), legacy_fixture():
                host = legacy_host()
                host.process_records.append(foreign_peer() | {"uid": 1001, "uids": [1001] * 4, "comm": comm, "cwd": cwd})
                with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "concurrent-host-owner"):
                    recover(host, current_uid=1001)
                self.assertFalse(host.released)


class NamespaceRefusalDiagnosticTests(unittest.TestCase):
    def test_cli_shared_ancestor_diagnostic_exits_78_without_release_or_nonmetadata_reads(self):
        evidence = proof()
        statuses = {pid: f"PPid: {parent}\nUid: {uid} {uid} {uid} {uid}\nGid: {uid} {uid} {uid} {uid}\nGroups:\n"
                    for pid, parent, uid in [(1, 0, 0), (2, 1, 1001), (3, 2, 1001), (4, 1, 1000)]}
        proc_path = status_only_path(statuses)
        with legacy_fixture() as (app, lock, chain):
            observed_shared_ancestor(chain)
            proof_file = MagicMock()
            proof_file.read_text.return_value = json.dumps(evidence)
            def path(value):
                if str(value) == RECOVERY.APP_DIR:
                    return app
                if str(value) == "fixture.json":
                    return proof_file
                return proc_path(value) if str(value) == "/proc" else Path(value)
            arguments = [str(HELPER), "acquire-reviewed-lock", "--proof", "fixture.json", "--app-dir", RECOVERY.APP_DIR,
                         "--project", RECOVERY.PROJECT, "--volume-prefix", RECOVERY.PREFIX]
            stderr, stdout = io.StringIO(), io.StringIO()
            with patch("pathlib.Path", side_effect=path), patch.object(sys, "argv", arguments), \
                 patch.object(RECOVERY.os, "readlink") as links, patch.object(RECOVERY.os, "rmdir") as remove, \
                 patch.object(RECOVERY.os, "mkdir") as acquire, patch.object(RECOVERY.subprocess, "run") as commands, \
                 redirect_stderr(stderr), redirect_stdout(stdout):
                with self.assertRaises(SystemExit) as caught:
                    runpy.run_path(str(HELPER), run_name="__main__")
            self.assertEqual(78, caught.exception.code)
            self.assertEqual("", stdout.getvalue())
            prefix = "Reviewed recovery blocked: lock-identity-unverified "
            self.assertTrue(stderr.getvalue().startswith(prefix), stderr.getvalue())
            diagnostic = json.loads(stderr.getvalue()[len(prefix):])
            self.assertEqual(1000, diagnostic["namespaceUid"])
            self.assertEqual(1010, diagnostic["namespaceGid"])
            self.assertEqual(6, len(diagnostic["namespaceChain"]))
            self.assertEqual("available", diagnostic["namespacePeerCounts"]["status"])
            self.assertEqual(1, diagnostic["namespacePeerCounts"]["peerCount"])
            for call in [links, remove, acquire, commands]:
                call.assert_not_called()
            lock.iterdir.assert_not_called()

    def test_actual_shared_ancestor_stays_refused_with_full_numeric_chain_and_metadata_only_peer_counts(self):
        with legacy_fixture() as (app, lock, chain):
            observed_shared_ancestor(chain)
            host = legacy_host()
            records = copy.deepcopy(host.process_records)
            records += [foreign_peer() | {"pid": 4, "uid": 1000, "uids": [1000] * 4},
                        foreign_peer() | {"pid": 5, "gids": [1002, 1002, 1002, 1010]},
                        foreign_peer() | {"pid": 6, "uids": [1002, 1002, 1002, 1000]},
                        foreign_peer() | {"pid": 7, "groups": [1010]},
                        foreign_peer() | {"pid": 8, "uid": 1001, "uids": [1001] * 4},
                        foreign_peer() | {"pid": 9, "uid": 0, "uids": [0] * 4}]
            with patch.object(RECOVERY, "process_status_records", return_value=records) as reader, \
                 patch.object(RECOVERY.os, "readlink") as links, patch.object(RECOVERY.os, "rmdir") as remove, \
                 patch.object(RECOVERY.os, "mkdir") as acquire, patch.object(RECOVERY.subprocess, "run") as commands:
                with self.assertRaisesRegex(RECOVERY.LockInspectionBlocked, "lock-identity-unverified") as caught:
                    recover(host, current_uid=1001)
            diagnostic = caught.exception.diagnostic
            self.assertEqual(2, diagnostic["namespaceRefusalDepth"])
            self.assertEqual(1000, diagnostic["namespaceUid"])
            self.assertEqual(1010, diagnostic["namespaceGid"])
            self.assertEqual(0o775, diagnostic["namespaceMode"])
            nodes = diagnostic["namespaceChain"]
            self.assertEqual(list(range(6)), [node["depth"] for node in nodes])
            for depth, node in enumerate(nodes):
                value = chain[depth].lstat.return_value
                self.assertEqual({"depth": depth, "status": "available", "uid": value.st_uid, "gid": value.st_gid,
                                  "mode": RECOVERY.stat.S_IMODE(value.st_mode), "device": value.st_dev, "inode": value.st_ino,
                                  "mtimeNs": value.st_mtime_ns, "ctimeNs": value.st_ctime_ns,
                                  "isDirectory": True, "isSymlink": False}, node)
            self.assertEqual({"status": "available", "reason": "none", "peerCount": 6,
                              "uidCounts": [{"identity": 0, "processCount": 1}, {"identity": 1000, "processCount": 2},
                                            {"identity": 1001, "processCount": 1}],
                              "writableGidCounts": [{"identity": 1010, "processCount": 2}]}, diagnostic["namespacePeerCounts"])
            reader.assert_called_once_with(include_names=False, bounded=True)
            for call in [links, remove, acquire, commands]:
                call.assert_not_called()
            lock.iterdir.assert_not_called()
            self.assertFalse(host.released)
            self.assertEqual(0, host.process_reads)
            self.assertEqual(0, host.container_reads)

    def test_zero_authority_peers_cannot_authorize_the_refused_ancestor(self):
        with legacy_fixture() as (app, lock, chain):
            observed_shared_ancestor(chain)
            host = legacy_host()
            with patch.object(RECOVERY, "process_status_records", return_value=host.process_records):
                with self.assertRaises(RECOVERY.LockInspectionBlocked) as caught:
                    recover(host, current_uid=1001)
            self.assertEqual(0, caught.exception.diagnostic["namespacePeerCounts"]["peerCount"])
            self.assertFalse(host.released)

    def test_unavailable_or_malformed_peer_metadata_keeps_original_refusal_without_private_error(self):
        failures = [PermissionError("PRIVATE_PERMISSION"), ValueError("PRIVATE_FORMAT"),
                    RECOVERY.RecoveryBlocked("PRIVATE_FAILURE"), RECOVERY.RecoveryBlocked("diagnostic-peer-limit")]
        for failure in failures:
            with self.subTest(failure=type(failure)), legacy_fixture() as (app, lock, chain):
                observed_shared_ancestor(chain)
                host = legacy_host()
                with patch.object(RECOVERY, "process_status_records", side_effect=failure):
                    with self.assertRaises(RECOVERY.LockInspectionBlocked) as caught:
                        recover(host, current_uid=1001)
                reason = "bounded_limit" if str(failure) == "diagnostic-peer-limit" else "peer_metadata"
                self.assertEqual({"status": "unavailable", "reason": reason}, caught.exception.diagnostic["namespacePeerCounts"])
                self.assertNotIn("PRIVATE", str(caught.exception))
                self.assertFalse(host.released)

    def test_unavailable_later_ancestor_retains_six_depths_and_never_reads_peers(self):
        with legacy_fixture() as (app, lock, chain):
            observed_shared_ancestor(chain)
            chain[4].lstat.side_effect = PermissionError("PRIVATE_PERMISSION")
            with patch.object(RECOVERY, "process_status_records") as reader:
                with self.assertRaises(RECOVERY.LockInspectionBlocked) as caught:
                    RECOVERY.Host().lock_identity()
            diagnostic = caught.exception.diagnostic
            self.assertEqual(list(range(6)), [node["depth"] for node in diagnostic["namespaceChain"]])
            self.assertEqual({"depth": 4, "status": "unavailable"}, diagnostic["namespaceChain"][4])
            self.assertEqual({"status": "unavailable", "reason": "namespace_metadata"}, diagnostic["namespacePeerCounts"])
            reader.assert_not_called()
            lock.iterdir.assert_not_called()

    def test_status_only_reader_counts_quartets_without_names_fd_cwd_or_other_data(self):
        statuses = {pid: f"Name: PRIVATE_PROCESS_NAME\nPPid: {parent}\nUid: {uid} {uid} {uid} {uid}\n"
                         f"Gid: {uid} {uid} {uid} {uid}\nGroups:\n" for pid, parent, uid in [(1, 0, 0), (2, 1, 1001), (3, 2, 1001)]}
        statuses[4] = "PPid: 1\nUid: 1002 1002 1002 1000\nGid: 1002 1002 1002 1010\nGroups: 1010 1011\n"
        chain = [{"depth": 0, "status": "available", "uid": 1000, "gid": 1010, "mode": 0o775}]
        with patch.object(RECOVERY, "Path", status_only_path(statuses)), patch.object(RECOVERY.os, "getpid", return_value=3), \
             patch.object(RECOVERY.os, "readlink") as links, patch.object(RECOVERY.subprocess, "run") as commands:
            records = RECOVERY.process_status_records(include_names=False, bounded=True)
            diagnostic = RECOVERY.namespace_peer_counts(chain)
        self.assertTrue(all("comm" not in item and "cwd" not in item and "fds" not in item for item in records))
        self.assertNotIn("PRIVATE", json.dumps(records))
        self.assertEqual({"status": "available", "reason": "none", "peerCount": 1,
                          "uidCounts": [{"identity": 1000, "processCount": 1}],
                          "writableGidCounts": [{"identity": 1010, "processCount": 1}]}, diagnostic)
        links.assert_not_called()
        commands.assert_not_called()

    def test_status_only_format_permission_and_size_limits_report_fixed_unavailable(self):
        samples = ["PPid: 0\nUid: 1001\nGid: 1001 1001 1001 1001\nGroups:\n",
                   "PPid: 0\nUid: 1001 1001 1001 1001\nGid: 1001 1001 1001 1001\nGroups: PRIVATE_VALUE\n",
                   "PRIVATE_VALUE" * (512 * 1024)]
        chain = [{"depth": 0, "status": "available", "uid": 1000, "gid": 1010, "mode": 0o775}]
        for sample in samples:
            with self.subTest(size=len(sample)), patch.object(RECOVERY, "Path", status_only_path({3: sample})):
                result = RECOVERY.namespace_peer_counts(chain)
                self.assertEqual("unavailable", result["status"])
                self.assertNotIn("PRIVATE", json.dumps(result))
        path = MagicMock()
        path.iterdir.side_effect = PermissionError("PRIVATE_PERMISSION")
        with patch.object(RECOVERY, "Path", return_value=path):
            self.assertEqual({"status": "unavailable", "reason": "peer_metadata"}, RECOVERY.namespace_peer_counts(chain))


class AdapterTests(unittest.TestCase):
    def test_real_lock_validator_requires_owned_empty_directory_canonical_path_and_private_writes(self):
        self.assertEqual({"namespaceRefusalDepth", "namespaceUid", "namespaceGid", "namespaceMode", "namespaceDevice", "namespaceInode",
                          "namespaceChain", "namespacePeerCounts"},
                         RECOVERY.LockIdentityDiagnostic.__optional_keys__)
        self.assertIn("lockUid", RECOVERY.LockIdentityDiagnostic.__required_keys__)
        self.assertIn("empty", RECOVERY.LockIdentityDiagnostic.__required_keys__)
        for invalid in [None, "symlink", "not-directory", "foreign-uid", "group-writable", "world-writable",
                        "not-empty", "app-alias", "app-symlink"]:
            app, lock = MagicMock(), MagicMock()
            app.resolve.return_value = app
            app.parents = []
            app.is_symlink.return_value = False
            app.__truediv__.return_value = lock
            lock.iterdir.return_value = iter([])
            metadata = SimpleNamespace(st_mode=RECOVERY.stat.S_IFDIR | 0o755, st_uid=MEMBER, st_gid=MEMBER + 11,
                                       st_dev=1, st_ino=42, st_mtime_ns=123, st_ctime_ns=123)
            lock.lstat.return_value = metadata
            if invalid == "symlink":
                metadata.st_mode = RECOVERY.stat.S_IFLNK | 0o755
            if invalid == "not-directory":
                metadata.st_mode = RECOVERY.stat.S_IFREG | 0o755
            if invalid == "foreign-uid":
                metadata.st_uid = MEMBER + 1
            if invalid == "group-writable":
                metadata.st_mode = RECOVERY.stat.S_IFDIR | 0o775
            if invalid == "world-writable":
                metadata.st_mode = RECOVERY.stat.S_IFDIR | 0o757
            if invalid == "not-empty":
                lock.iterdir.return_value = iter(["PRIVATE_OWNER_FILENAME"])
            if invalid == "app-alias":
                app.resolve.return_value = "elsewhere"
            if invalid == "app-symlink":
                app.is_symlink.return_value = True
            with self.subTest(invalid=invalid), patch.object(RECOVERY, "Path", return_value=app), \
                 patch.object(RECOVERY.os, "getuid", return_value=MEMBER, create=True), \
                 patch.object(RECOVERY.os, "geteuid", return_value=MEMBER, create=True), \
                 patch.object(RECOVERY.os, "getgid", return_value=MEMBER + 12, create=True), \
                 patch.object(RECOVERY.os, "getegid", return_value=MEMBER + 13, create=True):
                if invalid:
                    with self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                        RECOVERY.Host().lock_identity()
                    if invalid not in {"app-alias", "app-symlink"}:
                        self.assertIsInstance(caught.exception, RECOVERY.LockInspectionBlocked)
                        diagnostic = caught.exception.diagnostic
                        self.assertEqual({"appDir": RECOVERY.APP_DIR, "isDirectory": RECOVERY.stat.S_ISDIR(metadata.st_mode),
                                          "isSymlink": RECOVERY.stat.S_ISLNK(metadata.st_mode), "lockUid": metadata.st_uid,
                                          "processUid": MEMBER, "effectiveUid": MEMBER, "mode": RECOVERY.stat.S_IMODE(metadata.st_mode),
                                          "lockGid": MEMBER + 11, "processGid": MEMBER + 12, "effectiveGid": MEMBER + 13,
                                          "modeOctal": format(RECOVERY.stat.S_IMODE(metadata.st_mode), "04o"),
                                          "groupWritable": invalid == "group-writable", "worldWritable": invalid == "world-writable",
                                          "device": 1, "inode": 42, "mtimeNs": 123, "ctimeNs": 123,
                                          "empty": False if invalid == "not-empty" else None}, diagnostic)
                        self.assertEqual(diagnostic, json.loads(str(caught.exception).split(" ", 1)[1]))
                        self.assertNotIn("PRIVATE", str(caught.exception))
                    if invalid != "not-empty":
                        lock.iterdir.assert_not_called()
                else:
                    self.assertEqual({"device": 1, "inode": 42, "uid": MEMBER, "mtimeNs": 123, "ctimeNs": 123},
                                     RECOVERY.Host().lock_identity())

    def test_cli_identity_refusal_prints_safe_numeric_metadata_and_exits_78_without_inspection_or_unlock(self):
        app, lock = MagicMock(), MagicMock()
        app.resolve.return_value = app
        app.parents = []
        app.is_symlink.return_value = False
        app.__truediv__.return_value = lock
        app.read_text.return_value = json.dumps(proof())
        lock.lstat.return_value = SimpleNamespace(st_mode=RECOVERY.stat.S_IFDIR | 0o775, st_uid=MEMBER, st_gid=MEMBER + 11,
                                                 st_dev=1, st_ino=42, st_mtime_ns=123, st_ctime_ns=456)
        stderr, stdout = io.StringIO(), io.StringIO()
        arguments = [str(HELPER), "acquire-reviewed-lock", "--proof", "fixture.json", "--app-dir", RECOVERY.APP_DIR,
                     "--project", RECOVERY.PROJECT, "--volume-prefix", RECOVERY.PREFIX]
        with patch("pathlib.Path", return_value=app), patch.object(sys, "argv", arguments), \
             patch.object(RECOVERY.os, "getuid", return_value=MEMBER, create=True), \
             patch.object(RECOVERY.os, "geteuid", return_value=MEMBER, create=True), \
             patch.object(RECOVERY.os, "getgid", return_value=MEMBER + 12, create=True), \
             patch.object(RECOVERY.os, "getegid", return_value=MEMBER + 13, create=True), \
             patch.object(RECOVERY.os, "getpid", return_value=3), \
             patch.object(RECOVERY.os, "rmdir") as remove, patch.object(RECOVERY.os, "mkdir") as acquire, \
             patch.object(RECOVERY.subprocess, "run") as commands, redirect_stderr(stderr), redirect_stdout(stdout):
            with self.assertRaises(SystemExit) as caught:
                runpy.run_path(str(HELPER), run_name="__main__")
        self.assertEqual(78, caught.exception.code)
        self.assertEqual("", stdout.getvalue())
        prefix = "Reviewed recovery blocked: lock-identity-unverified "
        self.assertTrue(stderr.getvalue().startswith(prefix), stderr.getvalue())
        diagnostic = json.loads(stderr.getvalue()[len(prefix):])
        self.assertEqual("0775", diagnostic["modeOctal"])
        self.assertEqual(0o775, diagnostic["mode"])
        self.assertEqual(MEMBER, diagnostic["lockUid"])
        self.assertEqual(MEMBER, diagnostic["processUid"])
        self.assertEqual(MEMBER + 11, diagnostic["lockGid"])
        self.assertEqual(MEMBER + 12, diagnostic["processGid"])
        self.assertEqual(MEMBER + 13, diagnostic["effectiveGid"])
        self.assertIs(True, diagnostic["groupWritable"])
        self.assertIsNone(diagnostic["empty"])
        lock.iterdir.assert_not_called()
        commands.assert_not_called()
        remove.assert_not_called()
        acquire.assert_not_called()

    def test_proc_reader_skips_own_fds_and_kernel_threads_but_reads_peer_metadata(self):
        records = {1: ("systemd", 0, 0), 2: ("sshd", 1, 0), 3: ("python3", 2, 0),
                   4: ("other", 1, 0), 5: ("kthreadd", 1, 1)}
        class FakeProcPath:
            def __init__(self, value):
                self.value = str(value)
                self.name = self.value.rsplit("/", 1)[-1]
            def __truediv__(self, value):
                return FakeProcPath(self.value + "/" + str(value))
            def iterdir(self):
                return [FakeProcPath("/proc/" + str(pid)) for pid in records] if self.value == "/proc" else [self / "1"]
            def read_text(self):
                pid = int(self.value.split("/")[2])
                name, parent, kernel = records[pid]
                return f"Name:\t{name}\nPPid:\t{parent}\nUid:\t0 0 0 0\nGid:\t0 0 0 0\nGroups:\t\nKthread:\t{kernel}\n"
            def exists(self):
                return True
        links = []
        def readlink(path):
            links.append(path.value)
            return "/" if path.value.endswith("/cwd") else "socket:[1]"
        with patch.object(RECOVERY, "Path", FakeProcPath), patch.object(RECOVERY.os, "getuid", return_value=0, create=True), \
             patch.object(RECOVERY.os, "getpid", return_value=3), patch.object(RECOVERY.os, "readlink", side_effect=readlink):
            observed = RECOVERY.Host().processes()
        self.assertEqual(["/proc/4/cwd", "/proc/4/fd/1"], links)
        RECOVERY.check_processes(observed, 3, 0)

    def test_proc_reader_preserves_uid_gid_quartets_and_groups_without_foreign_fd_reads(self):
        class ProcPath:
            def __init__(self, value):
                self.value = str(value)
                self.name = self.value.rsplit("/", 1)[-1]
            def __truediv__(self, value):
                return ProcPath(self.value + "/" + str(value))
            def iterdir(self):
                return [ProcPath("/proc/" + str(pid)) for pid in [1, 2, 3, 4]]
            def read_text(self):
                pid = int(self.value.split("/")[2])
                uid = 1001 if pid in {2, 3} else 0
                identity = f"Uid:\t{uid} {uid} {uid} {uid}\nGid:\t{uid} {uid} {uid} {uid}\nGroups:\t\n"
                if pid == 4:
                    identity = "Uid:\t1002 1002 1002 1002\nGid:\t1002 1002 1002 1001\nGroups:\t1002 1003\n"
                return f"Name:\tother\nPPid:\t{pid - 1}\n" + identity + "Kthread:\t0\n"
        with patch.object(RECOVERY, "Path", ProcPath), patch.object(RECOVERY.os, "getuid", return_value=1001, create=True), \
             patch.object(RECOVERY.os, "getpid", return_value=3), patch.object(RECOVERY.os, "readlink") as readlink:
            records = RECOVERY.Host().processes()
        peer = next(item for item in records if item["pid"] == 4)
        self.assertEqual([1002] * 4, peer["uids"])
        self.assertEqual([1002, 1002, 1002, 1001], peer["gids"])
        self.assertEqual([1002, 1003], peer["groups"])
        readlink.assert_not_called()
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "peer-group-writer"):
            RECOVERY.check_processes(records, 3, 1001, group_gid=1001)

    def test_proc_reader_rejects_missing_duplicate_malformed_or_incomplete_credentials(self):
        samples = ["Uid: 1001\nGid: 1001 1001 1001 1001\nGroups:\n",
                   "Uid: 1001 1001 1001 1001\nGroups:\n",
                   "Uid: 1001 1001 1001 1001\nGid: 1001 1001 1001 1001\n",
                   "Uid: 1001 1001 1001 1001\nGid: 1001 1001 1001 1001\nGroups: 1001\nGroups: 1002\n",
                   "Uid: 1001 1001 1001 1001\nGid: 1001 1001 1001\nGroups:\n",
                   "Uid: 1001 1001 1001 1001\nGid: 1001 1001 1001 1001\nGroups: PRIVATE_VALUE\n"]
        for sample in samples:
            path, process = MagicMock(), MagicMock()
            process.name = "3"
            path.iterdir.return_value = [process]
            (process / "status").read_text.return_value = "Name: other\nPPid: 0\n" + sample
            with self.subTest(sample=sample), patch.object(RECOVERY, "Path", return_value=path):
                with self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                    RECOVERY.Host().processes()
                self.assertNotIn("PRIVATE", str(caught.exception))

    def test_private_child_error_never_reaches_public_diagnostic(self):
        completed = subprocess.CompletedProcess(["fixture"], 1, stdout=b"PRIVATE_VALUE", stderr=b"PRIVATE_PASSWORD")
        with patch.object(RECOVERY.subprocess, "run", return_value=completed):
            with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "metadata-command-failed") as caught:
                RECOVERY.command(["fixture"])
        self.assertNotIn("PRIVATE", str(caught.exception))

    def test_command_preserves_blob_bytes_including_crlf_for_image_hashes(self):
        completed = subprocess.CompletedProcess(["fixture"], 0, stdout=b"<?php\r\n", stderr=b"")
        with patch.object(RECOVERY.subprocess, "run", return_value=completed):
            self.assertEqual(b"<?php\r\n", RECOVERY.command(["fixture"]).encode())

    def test_schema_probe_uses_fixed_tables_read_only_and_no_credential_export(self):
        calls = []
        def run(argv, **options):
            calls.append(argv)
            return RECOVERY.SCHEMA_PREFIX["auth"] + VERSION + "\n"
        self.assertEqual([RECOVERY.SCHEMA_PREFIX["auth"] + VERSION], RECOVERY.Host(run).history("a" * 64, "auth"))
        self.assertEqual("BEGIN READ ONLY; SELECT version FROM doctrine_migration_versions_auth ORDER BY version; COMMIT;", calls[0][-1])
        self.assertNotIn("env", calls[0])
        self.assertNotIn("PASSWORD", " ".join(calls[0]))
        with self.assertRaises(RECOVERY.RecoveryBlocked):
            RECOVERY.Host(run).history("a" * 64, "main;DROP TABLE anything")
        self.assertEqual(1, len(calls))

    def test_inspected_image_is_immutable_has_exact_provenance_and_no_network_mount_or_bootstrap(self):
        calls = []
        def run(argv, **options):
            calls.append(argv)
            if argv[1] == "pull":
                return ""
            if argv[1] == "image":
                return json.dumps({"digests": [IMAGE], "source": "https://github.com/" + RECOVERY.REPOSITORY, "revision": SOURCE})
            return json.dumps(MANIFEST)
        self.assertEqual(MANIFEST, RECOVERY.Host(run).image_manifest(proof()))
        self.assertEqual(["docker", "pull", IMAGE], calls[0])
        self.assertIn("--read-only", calls[2])
        self.assertEqual("none", calls[2][calls[2].index("--network") + 1])
        self.assertNotIn("--volume", calls[2])
        self.assertNotIn(".env", calls[2][-1])
        self.assertNotIn("bin/console", calls[2][-1])

    def test_image_provenance_mismatch_does_not_run_manifest_container(self):
        calls = []
        def run(argv, **options):
            calls.append(argv)
            return "" if argv[1] == "pull" else json.dumps({"digests": [IMAGE], "source": "https://github.com/foreign/repo", "revision": SOURCE})
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "immutable-image-provenance"):
            RECOVERY.Host(run).image_manifest(proof())
        self.assertEqual(2, len(calls))

    def test_rmdir_then_one_ordinary_mkdir_without_recursive_remove(self):
        calls = []
        @contextmanager
        def mask():
            yield
        with patch.object(RECOVERY, "defer_cancellation", mask), \
             patch.object(RECOVERY.os, "rmdir", side_effect=lambda path: calls.append(("rmdir", path))) as remove, \
             patch.object(RECOVERY.os, "mkdir", side_effect=lambda path: calls.append(("mkdir", path))):
            RECOVERY.Host().replace_empty_lock()
        self.assertEqual(Path(RECOVERY.APP_DIR) / ".fireguard-operation.lock", remove.call_args.args[0])
        self.assertEqual(["rmdir", "mkdir"], [call[0] for call in calls])

    def test_cron_wins_mkdir_race_and_its_new_lock_is_never_removed_or_retried(self):
        @contextmanager
        def mask():
            yield
        with patch.object(RECOVERY, "defer_cancellation", mask), patch.object(RECOVERY.os, "rmdir") as remove, \
             patch.object(RECOVERY.os, "mkdir", side_effect=FileExistsError("cron owns new lock")) as acquire:
            with self.assertRaises(FileExistsError):
                RECOVERY.Host().replace_empty_lock()
        self.assertEqual(1, remove.call_count)
        self.assertEqual(1, acquire.call_count)

    def test_cancel_before_critical_section_keeps_historical_lock(self):
        @contextmanager
        def mask():
            raise KeyboardInterrupt
            yield
        with patch.object(RECOVERY, "defer_cancellation", mask), patch.object(RECOVERY.os, "rmdir") as remove, \
             patch.object(RECOVERY.os, "mkdir") as acquire:
            with self.assertRaises(KeyboardInterrupt):
                RECOVERY.Host().replace_empty_lock()
        remove.assert_not_called()
        acquire.assert_not_called()

    def test_deferred_cancel_after_rmdir_is_delivered_with_reacquired_lock_retained(self):
        events = []
        @contextmanager
        def mask():
            yield
            events.append("cancel-delivered")
            raise KeyboardInterrupt
        with patch.object(RECOVERY, "defer_cancellation", mask), \
             patch.object(RECOVERY.os, "rmdir", side_effect=lambda path: events.append("rmdir")), \
             patch.object(RECOVERY.os, "mkdir", side_effect=lambda path: events.append("mkdir")):
            with self.assertRaises(KeyboardInterrupt):
                RECOVERY.Host().replace_empty_lock()
        self.assertEqual(["rmdir", "mkdir", "cancel-delivered"], events)

    def test_partial_recovery_failure_never_reports_success_or_removes_again(self):
        @contextmanager
        def mask():
            yield
        with patch.object(RECOVERY, "defer_cancellation", mask), patch.object(RECOVERY.os, "rmdir") as remove, \
             patch.object(RECOVERY.os, "mkdir", side_effect=PermissionError("private failure")) as acquire:
            with self.assertRaises(PermissionError):
                RECOVERY.Host().replace_empty_lock()
        self.assertEqual(1, remove.call_count)
        self.assertEqual(1, acquire.call_count)

    def test_signal_mask_restored_only_after_critical_work(self):
        events = []
        signals = SimpleNamespace(SIG_BLOCK=1, SIG_SETMASK=2, SIGINT=3, SIGTERM=4, SIGHUP=5,
                                  pthread_sigmask=lambda mode, values: events.append((mode, values)) or {7})
        with patch.object(RECOVERY, "signal", signals):
            with RECOVERY.defer_cancellation():
                events.append("work")
        self.assertEqual([(1, {3, 4, 5}), "work", (2, {7})], events)


class ContainerIsolationIntegrationTests(unittest.TestCase):
    def setUp(self):
        spec = importlib.util.spec_from_file_location("recovery_real_isolation", ROOT / "bin/fireguard-deployment-isolation.py")
        module = importlib.util.module_from_spec(spec)
        sys.modules[spec.name] = module
        spec.loader.exec_module(module)
        self.module_patch = patch.object(RECOVERY, "isolation_validator", return_value=module.validate_isolation)
        self.module_patch.start()
        self.addCleanup(self.module_patch.stop)

    def test_exact_observed_shared_chain_requires_two_real_pure_proofs_before_acquisition(self):
        snapshot = isolation_fixture()
        with legacy_fixture() as (app, lock, chain), patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
            reviewed_namespace(chain)
            host = legacy_host()
            host.isolation_snapshot = MagicMock(side_effect=[copy.deepcopy(snapshot), copy.deepcopy(snapshot)])
            result = recover(host, current_uid=1001)
            self.assertEqual("reviewed-lock-reacquired-awaiting-rollout", result["result"])
            self.assertEqual(2, host.isolation_snapshot.call_count)
            self.assertTrue(host.reacquired)
            self.assertTrue(host.lock_identity()["reviewedLegacy"]["isolationRequired"])

    def test_physical_dev_or_root_identity_change_never_releases_but_unrelated_volume_churn_does(self):
        for kind in ("root", "host-mount", "development", "unrelated"):
            with self.subTest(kind=kind), legacy_fixture() as (app, lock, chain):
                reviewed_namespace(chain)
                host = legacy_host()
                before, after = isolation_fixture(), isolation_fixture()
                if kind == "root":
                    after["storageProof"]["rootChain"][0]["inode"] += 1
                elif kind == "host-mount":
                    after["storageProof"]["hostMount"]["mountId"] += 1
                elif kind == "development":
                    after["storageProof"]["nodes"][0]["data"]["inode"] += 1
                else:
                    # An unrelated root volume can sort before relevant names; indices are not stable keys.
                    extra = copy.deepcopy(after["storageProof"]["nodes"][0])
                    after["storageProof"]["volumeNames"].insert(0, "benign-unrelated")
                    after["storageProof"]["nodes"].insert(0, extra)
                    for index, node in enumerate(after["storageProof"]["nodes"]):
                        node["index"] = index
                host.isolation_snapshot = MagicMock(side_effect=[before, after])
                if kind == "unrelated":
                    recover(host, current_uid=1001)
                    self.assertTrue(host.reacquired)
                else:
                    with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "storage-physical-identity-changed"):
                        recover(host, current_uid=1001)
                    self.assertFalse(host.released)
                    self.assertFalse(host.reacquired)

    def test_manifest_failure_precedes_storage_actor_and_namespace_authority_proof(self):
        with legacy_fixture() as (app, lock, chain):
            reviewed_namespace(chain)
            host = legacy_host()
            host.manifest = {}
            host.prepare_storage_inspector = MagicMock()
            host.isolation_snapshot = MagicMock()
            with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "image-migration-manifest-mismatch"):
                recover(host, current_uid=1001)
            host.prepare_storage_inspector.assert_not_called()
            host.isolation_snapshot.assert_not_called()
            self.assertFalse(host.released)

    def test_every_full_namespace_pin_field_is_required_before_snapshot_or_unlock(self):
        fields = ("st_uid", "st_gid", "st_dev", "st_ino", "st_mode", "st_mtime_ns", "st_ctime_ns")
        for depth in range(6):
            for field in fields:
                with self.subTest(depth=depth, field=field), legacy_fixture() as (app, lock, chain):
                    reviewed_namespace(chain)
                    node = chain[depth].lstat.return_value
                    setattr(node, field, getattr(node, field) + 1)
                    host = legacy_host()
                    host.isolation_snapshot = MagicMock()
                    with self.assertRaises(RECOVERY.RecoveryBlocked):
                        recover(host, current_uid=1001)
                    host.isolation_snapshot.assert_not_called()
                    self.assertFalse(host.released)

    def test_host_authority_peer_nnp_caps_mount_or_relevant_churn_keeps_lock(self):
        mutations = (
            lambda state: state["processes"][-2]["tasks"][0].update(containerId=None, cgroupPath="/user.slice"),
            lambda state: state["processes"][-2]["tasks"][0].update(noNewPrivs=0),
            lambda state: state["processes"][-2]["tasks"][0].update(capAmb=1),
            lambda state: state["containers"][0]["HostConfig"].update(Privileged=True),
            lambda state: state["containers"][0]["HostConfig"].update(SecurityOpt=[]),
            lambda state: state["containers"][0]["HostConfig"].update(MountDeclarationsComplete=False),
        )
        for mutation in mutations:
            for phase in (0, 1):
                with self.subTest(phase=phase, mutation=mutation), legacy_fixture() as (app, lock, chain), \
                     patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
                    reviewed_namespace(chain)
                    states = [isolation_fixture(), isolation_fixture()]
                    mutation(states[phase])
                    host = legacy_host()
                    host.isolation_snapshot = MagicMock(side_effect=states)
                    with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "container-isolation-") as error:
                        recover(host, current_uid=1001)
                    counts = json.loads(str(error.exception).split(" ", 1)[1])["counts"]
                    self.assertTrue(all(type(value) is int and value >= 0 for value in counts.values()))
                    self.assertNotIn("private-name", str(error.exception))
                    self.assertNotIn("/user.slice", str(error.exception))
                    self.assertFalse(host.released)

    def test_pid_reuse_or_pin_change_after_first_proof_never_releases(self):
        for kind in ("pid", "pin"):
            with self.subTest(kind=kind), legacy_fixture() as (app, lock, chain), \
                 patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
                reviewed_namespace(chain)
                states = [isolation_fixture(), isolation_fixture()]
                host = legacy_host()
                if kind == "pid":
                    states[1]["processes"][-2]["startTicks"] += 1
                    states[1]["processes"][-2]["tasks"][0]["startTicks"] += 1
                else:
                    host.before_final_containers = lambda unused: setattr(chain[3].lstat.return_value, "st_ctime_ns", 9)
                host.isolation_snapshot = MagicMock(side_effect=states)
                with self.assertRaises(RECOVERY.RecoveryBlocked):
                    recover(host, current_uid=1001)
                self.assertFalse(host.released)

    def test_private_and_previously_protected_legacy_paths_do_not_load_or_collect_isolation(self):
        with patch.object(RECOVERY, "isolation_validator") as validator:
            host = FakeHost()
            host.isolation_snapshot = MagicMock()
            recover(host)
            host.isolation_snapshot.assert_not_called()
            with legacy_fixture():
                host = legacy_host()
                host.isolation_snapshot = MagicMock()
                recover(host, current_uid=1001)
                host.isolation_snapshot.assert_not_called()
            validator.assert_not_called()

    def test_foreign_gid1001_guard_exempts_only_live_identity_of_fully_proven_uid1000(self):
        for changed in (False, True):
            with self.subTest(changed=changed), legacy_fixture() as (app, lock, chain), \
                 patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
                reviewed_namespace(chain)
                snapshot = isolation_fixture()
                for process in snapshot["processes"]:
                    for task in process["tasks"]:
                        if task["uids"] == [1000] * 4:
                            task["gids"] = [1001] * 4
                leader = next(task for process in snapshot["processes"] if process["pid"] == 110 for task in process["tasks"] if task["tid"] == 110)
                host = legacy_host()
                peer = foreign_peer() | {"pid": 110, "ppid": 100, "uid": 1000, "uids": [1000] * 4, "gids": [1001] * 4, "groups": leader["groups"]}
                parent = foreign_peer() | {"pid": 100, "ppid": 2, "uid": 1000, "uids": [1000] * 4, "gids": [1001] * 4, "groups": leader["groups"]}
                host.process_records.extend([parent, peer])
                host.isolation_snapshot = MagicMock(side_effect=[copy.deepcopy(snapshot), copy.deepcopy(snapshot)])
                reads = []
                def live(pid, driver):
                    task = copy.deepcopy(next(task for process in snapshot["processes"] if process["pid"] == pid for task in process["tasks"] if task["tid"] == pid))
                    reads.append(pid)
                    if changed and len(reads) >= 3:
                        task["startTicks"] += 1
                    return task
                host.isolation_peer_task = MagicMock(side_effect=live)
                if changed:
                    with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "isolated-peer-identity-changed"):
                        recover(host, current_uid=1001)
                    self.assertFalse(host.released)
                else:
                    recover(host, current_uid=1001)
                    self.assertTrue(host.reacquired)
                    self.assertEqual([100, 110, 100, 110], reads)

    def test_unproven_foreign_gid1001_or_same_uid_host_owner_is_never_exempted(self):
        for peer in (foreign_peer() | {"gids": [1001] * 4},
                     foreign_peer() | {"uid": 1001, "uids": [1001] * 4, "gids": [1001] * 4, "comm": "rsync"}):
            with self.subTest(peer=peer), legacy_fixture() as (app, lock, chain), \
                 patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
                reviewed_namespace(chain)
                host = legacy_host()
                host.process_records.append(peer)
                host.isolation_snapshot = MagicMock(return_value=isolation_fixture())
                host.isolation_peer_task = MagicMock()
                with self.assertRaises(RECOVERY.RecoveryBlocked):
                    recover(host, current_uid=1001)
                self.assertFalse(host.released)
                host.isolation_peer_task.assert_not_called()

    def test_new_unproven_authority_peer_after_either_safe_snapshot_never_releases(self):
        credential_slots = [(key, slot) for key in ("uids", "gids") for slot in range(4)] + [("groups", 0), ("full", 0)]
        for key, slot in credential_slots:
            for phase in (1, 2):
                with self.subTest(key=key, slot=slot, phase=phase), legacy_fixture() as (app, lock, chain), \
                     patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
                    reviewed_namespace(chain)
                    host = legacy_host()
                    peer = foreign_peer()
                    if key == "full":
                        peer.update(uids=[1000] * 4, gids=[1000] * 4, groups=[1000])
                    else:
                        peer[key] = [1002] * 4 if key != "groups" else [1002]
                        peer[key][slot] = 1000
                    peer["uid"] = peer["uids"][0]
                    reads = []
                    safe = isolation_fixture()
                    def snapshot():
                        reads.append(True)
                        if len(reads) == phase:
                            host.process_records.append(peer)
                        return copy.deepcopy(safe)
                    host.isolation_snapshot = MagicMock(side_effect=snapshot)
                    host.isolation_peer_task = MagicMock()
                    with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "unproved-namespace-authority-peer-before-owner-guard"):
                        recover(host, current_uid=1001)
                    self.assertEqual(phase, host.isolation_snapshot.call_count)
                    self.assertFalse(host.released)
                    self.assertFalse(host.reacquired)
                    host.isolation_peer_task.assert_not_called()

    def test_no_proved_authority_peers_does_not_disable_guard_on_new_host_peer(self):
        host = legacy_host()
        peer = foreign_peer() | {"uids": [1000] * 4, "uid": 1000, "gids": [1000] * 4}
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "unproved-namespace-authority-peer-before-owner-guard"):
            RECOVERY.check_processes(host.process_records + [peer], 3, 1001, group_gid=1001, isolated_peers={}, peer_reader=MagicMock())

    def test_missing_or_aliased_sibling_module_fails_closed(self):
        self.module_patch.stop()
        source = MagicMock()
        source.lstat.return_value = SimpleNamespace(st_mode=RECOVERY.stat.S_IFREG | 0o600, st_uid=1001)
        source.is_symlink.return_value = True
        helper = MagicMock()
        helper.resolve.return_value = helper
        helper.parent.__truediv__.return_value = source
        helper.stat.return_value = SimpleNamespace(st_uid=1001)
        with patch.object(RECOVERY, "Path", return_value=helper):
            with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "isolation-module-unverified"):
                RECOVERY.isolation_validator()
            source.lstat.side_effect = PermissionError("private-storage-message")
            with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "isolation-module-unavailable") as error:
                RECOVERY.isolation_validator()
            self.assertNotIn("private-storage-message", str(error.exception))

    def test_trusted_volume_policy_remains_exact_and_cannot_expand_from_runtime(self):
        with patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
            policy = RECOVERY.isolation_policy(FakeHost().container_records, 3, 1001)
        self.assertEqual([{"Name": "back_app_var", "Destination": "/var/www/html/var", "RW": True},
                          {"Name": "back_jwt_keys", "Destination": "/var/www/html/config/jwt", "RW": False},
                          {"Name": "back_geoip_data", "Destination": "/var/lib/fireguard/geoip", "RW": False}], policy["allowedVolumeMounts"])
        self.assertEqual([1000], policy["authorityGids"])
        self.assertEqual(["/srv"], policy["protectedPaths"])
        self.assertNotIn("fixture", json.dumps(policy["allowedVolumeMounts"]))


class IsolationProcCollectorTests(unittest.TestCase):
    def test_all_threads_credentials_cgroups_and_stat_identity_are_read_without_private_files(self):
        snapshot = isolation_fixture()
        tree = ProcTree(snapshot)
        with patch.object(RECOVERY, "Path", side_effect=tree.path):
            result = RECOVERY.isolation_process_inventory("systemd")
        child = next(item for item in result if item["pid"] == 110)
        self.assertEqual([110, 111], [task["tid"] for task in child["tasks"]])
        self.assertEqual([110, 7], child["tasks"][1]["nsTgid"])
        self.assertEqual("a" * 64, child["tasks"][1]["containerId"])
        self.assertNotIn("private-name", json.dumps(result))
        self.assertTrue(all(path.endswith(("/stat", "/status", "/cgroup", "/task")) or path == "/proc" for path in tree.reads))

    def test_exact_rootful_cgroup_driver_paths_and_coherent_controllers_are_required(self):
        identifier = "a" * 64
        for driver, path in (("systemd", "/system.slice/docker-" + identifier + ".scope"), ("cgroupfs", "/docker/" + identifier)):
            self.assertEqual(identifier, RECOVERY.task_cgroup("0::" + path, driver)["containerId"])
        for path in ("/user.slice/docker-" + identifier + ".scope", "/docker/" + identifier + "/child"):
            self.assertIsNone(RECOVERY.task_cgroup("0::" + path, "systemd")["containerId"])
        for malformed in ("0::/docker/../docker/a", "0::/\n1:cpu:/else", "invalid", "", "0::relative"):
            with self.subTest(value=malformed), self.assertRaises(RECOVERY.RecoveryBlocked):
                RECOVERY.task_cgroup(malformed, "systemd")

    def test_live_exemption_reader_reuses_the_same_metadata_only_thread_identity(self):
        tree = ProcTree(isolation_fixture())
        with patch.object(RECOVERY, "Path", side_effect=tree.path):
            result = RECOVERY.Host().isolation_peer_task(110, "systemd")
        self.assertEqual(10005, result["startTicks"])
        self.assertEqual([1000] * 4, result["uids"])
        self.assertTrue(all(path.startswith("/proc/110/task/110/") for path in tree.reads))

    def test_incomplete_duplicate_or_denied_task_metadata_never_marks_inventory_complete(self):
        for change in ("missing", "duplicate", "permission", "quartet", "capability"):
            with self.subTest(change=change):
                tree = ProcTree(isolation_fixture())
                path = "/proc/110/task/111/status"
                if change == "missing":
                    tree.files[path] = tree.files[path].replace("NStgid:", "missing:")
                elif change == "duplicate":
                    tree.files[path] += "\nUid: 1000 1000 1000 1000"
                elif change == "permission":
                    tree.files[path] = PermissionError("secret-child-error")
                elif change == "quartet":
                    tree.files[path] = tree.files[path].replace("Uid: 1000 1000 1000 1000", "Uid: 1000 1000")
                else:
                    tree.files[path] = tree.files[path].replace("CapAmb: 0000000000000000", "CapAmb: malformed")
                with patch.object(RECOVERY, "Path", side_effect=tree.path), self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                    RECOVERY.isolation_process_inventory("systemd")
                self.assertNotIn("secret-child-error", str(caught.exception))

    def test_pid_reuse_or_thread_creation_during_scan_is_refused(self):
        for kind in ("pid", "thread"):
            tree = ProcTree(isolation_fixture())
            calls = []
            if kind == "pid":
                def changing_stat():
                    calls.append(True)
                    return tree.stat(111, 100, 10006 if len(calls) == 1 else 20000)
                tree.files["/proc/110/task/111/stat"] = changing_stat
            else:
                original = RECOVERY.numeric_proc_entries
                def changing_entries(path, limit):
                    result = original(path, limit)
                    if path.value == "/proc/110/task":
                        calls.append(True)
                        if len(calls) == 2:
                            result.append(112)
                    return result
            with patch.object(RECOVERY, "Path", side_effect=tree.path):
                replacement = patch.object(RECOVERY, "numeric_proc_entries", side_effect=changing_entries) if kind == "thread" else nullcontext()
                with replacement, self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "identity-changed"):
                    RECOVERY.isolation_process_inventory("systemd")


class DockerMetadataStageTests(unittest.TestCase):
    def test_legacy_projection_uses_typed_id_and_fixed_list_and_inspect_failure_codes(self):
        self.assertIn("{{json .ID}}", RECOVERY.CONTAINER_FORMAT)
        self.assertNotIn("{{json .Id}}", RECOVERY.CONTAINER_FORMAT)
        for phase in ("list", "inspect"):
            def unavailable(argv):
                if phase == "inspect" and argv[1] == "ps":
                    return "a" * 64 + "\n"
                raise RECOVERY.RecoveryBlocked("private-child-message-and-identifier")
            with self.subTest(phase=phase), self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                RECOVERY.Host(unavailable).containers()
            self.assertEqual("docker-legacy-container-" + phase + "-command-failed", str(caught.exception))

    def test_each_allowlisted_isolation_command_has_a_fixed_phase_without_private_output(self):
        commands = ((["version", "--format", RECOVERY.ISOLATION_VERSION_FORMAT], "version"),
                    (["info", "--format", RECOVERY.ISOLATION_INFO_FORMAT], "info"),
                    (["ps", "--quiet", "--no-trunc"], "container-list"),
                    (["container", "inspect", "--format", RECOVERY.ISOLATION_CONTAINER_FORMAT, "a" * 64], "container-inspect"),
                    (["image", "inspect", "--format", RECOVERY.ISOLATION_IMAGE_FORMAT, "sha256:" + "b" * 64], "image-inspect"),
                    (["volume", "inspect", "--format", RECOVERY.ISOLATION_VOLUME_FORMAT, "private-volume-fixture"], "volume-inspect"))
        for arguments, phase in commands:
            for error in (RECOVERY.RecoveryBlocked("private-stderr"), PermissionError("private-path"),
                          subprocess.TimeoutExpired("private-command", 1, stderr=b"private-secret")):
                with self.subTest(phase=phase, error=type(error)), self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                    RECOVERY.Host(MagicMock(side_effect=error)).isolation_docker(arguments)
                self.assertEqual("docker-isolation-" + phase + "-command-failed", str(caught.exception))

    def test_unknown_operation_is_rejected_before_the_runner_and_invalid_json_has_fixed_phase(self):
        runner = MagicMock(return_value="{}")
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "docker-isolation-operation-unverified"):
            RECOVERY.Host(runner).isolation_docker(["exec", "private-argument"])
        runner.assert_not_called()
        for value in ("private-malformed-json", "[]"):
            with self.subTest(value=value), self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                RECOVERY.Host(lambda argv: value).isolation_json(["volume", "inspect", "--format", RECOVERY.ISOLATION_VOLUME_FORMAT, "fixture"])
            self.assertEqual("docker-isolation-volume-inspect-metadata-unverified", str(caught.exception))
            with self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                RECOVERY.Host(lambda argv: "a" * 64 + "\n" if argv[1] == "ps" else value).containers()
            self.assertEqual("docker-legacy-container-inspect-metadata-unverified", str(caught.exception))

    def test_command_phase_refusal_preserves_lock_and_all_recovery_gates(self):
        host = FakeHost()
        host.containers = RECOVERY.Host(MagicMock(side_effect=RECOVERY.RecoveryBlocked("private-child-stderr"))).containers
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "docker-legacy-container-list-command-failed"):
            recover(host)
        self.assertFalse(host.released)
        self.assertFalse(host.reacquired)
        self.assertEqual(1, host.lock_reads)


class IsolationDockerCollectorTests(unittest.TestCase):
    def setUp(self):
        self.snapshot = isolation_fixture()
        self.identifier = self.snapshot["containers"][0]["Id"]
        self.version = {"client": "1.45", "server": "1.48", "minimum": "1.24"}
        self.info = {"Rootful": True, "CgroupDriver": "systemd"}
        self.calls = []

    def runner(self, argv):
        self.calls.append(argv)
        self.assertEqual(["/usr/bin/env", "DOCKER_API_VERSION=1.45", "docker", "--host", "unix:///var/run/docker.sock"], argv[:5])
        arguments = argv[5:]
        if arguments[0] == "version":
            return json.dumps(self.version)
        if arguments[0] == "info":
            return json.dumps(self.info)
        if arguments[0] == "ps":
            return self.identifier + "\n"
        if arguments[:2] == ["image", "inspect"]:
            return json.dumps({"Id": "sha256:" + "c" * 64, "RootFS": {"Type": "layers"}})
        if arguments[:2] == ["container", "inspect"]:
            record = copy.deepcopy(self.snapshot["containers"][0])
            record.pop("RootFS")
            config = record["HostConfig"]
            config["Mounts"] = config.pop("MountDeclarations")
            config["Binds"] = []
            return json.dumps(record)
        raise AssertionError("Unexpected public metadata command")

    def test_actual_fixed_api_local_daemon_and_separate_image_rootfs_projection(self):
        host = RECOVERY.Host(self.runner)
        with patch.dict(RECOVERY.os.environ, {"DOCKER_API_VERSION": "1.48"}):
            self.assertEqual("systemd", host.isolation_api())
        records = host.isolation_containers(host.isolation_container_ids())
        self.assertEqual({"Type": "layers"}, records[0]["RootFS"])
        self.assertTrue(records[0]["HostConfig"]["MountDeclarationsComplete"])
        self.assertNotIn("RootFS", RECOVERY.ISOLATION_CONTAINER_FORMAT)
        self.assertIn(".RootFS.Type", RECOVERY.ISOLATION_IMAGE_FORMAT)

    def test_snapshot_completeness_requires_both_docker_and_proc_inventories(self):
        tree = ProcTree(self.snapshot)
        tree.files["/proc/sys/kernel/random/boot_id"] = self.snapshot["bootId"] + "\n"
        tree.files["/proc/stat"] = "cpu 1 2 3 4\nbtime " + str(self.snapshot["bootTimeSeconds"]) + "\n"
        with patch.dict(RECOVERY.os.environ, {}, clear=True), patch.object(RECOVERY, "Path", side_effect=tree.path), \
             patch.object(RECOVERY.os, "sysconf", return_value=100, create=True):
            collected = RECOVERY.Host(self.runner).isolation_snapshot()
        self.assertIs(collected["complete"], True)
        self.assertEqual("1.45", collected["dockerApiVersion"])
        self.assertEqual("systemd", collected["cgroupDriver"])
        self.assertEqual(self.snapshot["bootId"], collected["bootId"])
        self.assertEqual(2, sum(argv[5] == "ps" for argv in self.calls))

    def test_named_volume_projection_accepts_only_boolean_options_flag_and_canonical_identity(self):
        volume = {"Name": "back_app_var", "Driver": "local", "Scope": "local", "OptionsEmpty": True,
                  "Mountpoint": "/var/lib/docker/volumes/back_app_var/_data"}
        records = [{"Mounts": [{"Type": "volume", "Name": "back_app_var", "Source": volume["Mountpoint"]}]}]
        calls = []
        host = RECOVERY.Host(lambda argv: calls.append(argv) or json.dumps(volume))
        with patch.object(RECOVERY, "canonical_storage", side_effect=lambda value: value):
            self.assertEqual([volume], host.isolation_volumes(records))
            volume["OptionsEmpty"] = "true"
            with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "isolation-volume-metadata-unverified"):
                host.isolation_volumes(records)
        self.assertEqual(RECOVERY.ISOLATION_VOLUME_FORMAT, calls[0][-2])
        self.assertNotIn("Options", volume.keys() - {"OptionsEmpty"})

    def test_low_or_malformed_api_override_is_refused_before_any_docker_query(self):
        for override in ("1.24", "1.44", "invalid", ""):
            with self.subTest(override=override), patch.dict(RECOVERY.os.environ, {"DOCKER_API_VERSION": override}):
                with self.assertRaises(RECOVERY.RecoveryBlocked):
                    RECOVERY.Host(self.runner).isolation_api()
        self.assertEqual([], self.calls)

    def test_client_server_minimum_rootless_and_driver_ambiguity_are_denied(self):
        cases = (("client", "1.44"), ("server", "1.44"), ("minimum", "1.46"), ("Rootful", False), ("CgroupDriver", "unknown"))
        for key, value in cases:
            with self.subTest(key=key), patch.dict(RECOVERY.os.environ, {}, clear=True):
                original_version, original_info = dict(self.version), dict(self.info)
                (self.version if key in self.version else self.info)[key] = value
                with self.assertRaises(RECOVERY.RecoveryBlocked):
                    RECOVERY.Host(self.runner).isolation_api()
                self.version, self.info = original_version, original_info

    def test_only_selected_typed_public_fields_and_empty_option_flags_leave_docker(self):
        templates = (RECOVERY.ISOLATION_CONTAINER_FORMAT, RECOVERY.ISOLATION_IMAGE_FORMAT, RECOVERY.ISOLATION_VOLUME_FORMAT, RECOVERY.ISOLATION_INFO_FORMAT)
        for template in templates:
            for forbidden in (".Env", ".Cmd", ".Config.Labels", "json $m.VolumeOptions.Labels", "{{json .HostConfig}}", "json .Options", "{{json .Config}}", "json .SecurityOptions"):
                self.assertNotIn(forbidden, template)
        self.assertIn("eq (len .Options) 0", RECOVERY.ISOLATION_VOLUME_FORMAT)
        self.assertIn(".VolumeOptions.Subpath", RECOVERY.ISOLATION_CONTAINER_FORMAT)
        for boolean in ("not $m.ImageOptions", "not $m.ClusterOptions", "len $m.VolumeOptions.Labels"):
            self.assertIn(boolean, RECOVERY.ISOLATION_CONTAINER_FORMAT)
        self.assertIn("split $b", RECOVERY.ISOLATION_CONTAINER_FORMAT)

    def test_sibling_staging_and_python_310_syntax_remain_reviewable(self):
        playbook = yaml.safe_load(PLAYBOOK.read_text(encoding="utf-8"))
        recovery = next(task for task in playbook[0]["tasks"] if "Recover only" in task.get("name", ""))
        staging = next(task["ansible.builtin.copy"] for task in recovery["block"] if task.get("ansible.builtin.copy", {}).get("src") == "../bin/fireguard-deployment-isolation.py")
        self.assertTrue(staging["dest"].endswith("/fireguard-deployment-isolation.py"))
        self.assertEqual("0600", staging["mode"])
        ast.parse(HELPER.read_text(encoding="utf-8"), feature_version=(3, 10))


def storage_mount_fixture():
    return RECOVERY.storage_host_mounts("10 1 8:1 / / rw - ext4 /private-source rw\n", [])


class StorageHostMountTests(unittest.TestCase):
    def test_nearest_private_source_is_pinned_and_unrelated_overlays_do_not_change_it(self):
        base = "10 1 8:1 / / rw - ext4 /private-source rw\n"
        nearest = "11 10 8:2 / /var/lib rw - ext4 /private-second rw\n"
        expected = RECOVERY.storage_host_mounts(base + nearest, ["fixture"])
        self.assertEqual(11, expected["mountId"])
        self.assertEqual(1, expected["sourceDepth"])
        self.assertTrue(expected["private"])
        for overlay in ("", "22 11 8:3 / /var/lib/docker/overlay2/transient/merged rw - overlay private rw\n",
                        "33 11 8:3 / /var/lib/docker/volumes/unrelated/_data rw - ext4 private rw\n"):
            self.assertEqual(expected, RECOVERY.storage_host_mounts(base + nearest + overlay, ["fixture"]))
        self.assertNotIn("/private", json.dumps(expected))

    def test_private_shared_slave_source_pins_and_unbindable_relevant_mount_refusals(self):
        base = "10 1 8:1 / / rw - ext4 private rw\n"
        for option in ("shared:7", "master:7", "master:7 propagate_from:8", "shared:7 master:8"):
            with self.subTest(option=option):
                proof = RECOVERY.storage_host_mounts(base.replace(" rw -", " rw " + option + " -"), ["fixture"])
                self.assertFalse(proof["private"])
                self.assertTrue(any(value is not None for value in proof["propagation"].values()))
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "source-unbindable"):
            RECOVERY.storage_host_mounts(base.replace(" rw -", " rw unbindable -"), ["fixture"])
        for option in ("shared:invalid", "master:0", "shared:1 shared:2", "unknown:1"):
            with self.subTest(option=option), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "source-mount-unverified"):
                RECOVERY.storage_host_mounts(base.replace(" rw -", " rw " + option + " -"), ["fixture"])
        for suffix in ("volumes", "volumes/fixture", "volumes/fixture/_data", "volumes/fixture/_data/child"):
            line = "11 10 8:2 / /var/lib/docker/" + suffix + " ro - ext4 private rw\n"
            with self.subTest(suffix=suffix), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "host-volume-submount"):
                RECOVERY.storage_host_mounts(base + line, ["fixture"])
        for text in (base + base, "malformed-private-metadata", base.replace("8:1", "invalid"),
                     base.replace("10 1", "18446744073709551616 1"), base.replace(" / rw", " relative rw")):
            with self.subTest(text=text), self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                RECOVERY.storage_host_mounts(text, ["fixture"])
            self.assertNotIn("private-metadata", str(caught.exception))


def storage_fixture():
    def node(inode, uid=0, mode=0o700):
        return {"device": 2049, "inode": inode, "uid": uid, "gid": uid, "mode": mode,
                "isDirectory": True, "isSymlink": False}
    chain = [node(100 + index, mode=0o710 if index == 0 else 0o755) for index in range(4)]
    body = {"ok": True, "actorVerified": True, "root": chain[0], "volumes": node(200),
            "nodes": [{"index": 0, "directory": node(300), "data": node(400, uid=1000, mode=0o775)}]}
    return chain, body


class StorageInspectorTests(unittest.TestCase):
    def setUp(self):
        self.chain, self.body = storage_fixture()
        self.calls = []
        self.output = json.dumps(self.body)
        self.nonce = "e" * 32
        self.host = RECOVERY.Host(self.runner)
        self.host._verified_storage_image = "sha256:" + "b" * 64
        self.host.prepare_storage_inspector([{"id": "d" * 64, "project": RECOVERY.PROJECT, "mounts": [
            {"name": "development", "source": RECOVERY.storage_volume_path("development")}]}])
        self.host._storage_authority_verified = True
        self.root_patch = patch.object(RECOVERY, "storage_root_chain", return_value=self.chain)
        self.kernel_patch = patch.object(RECOVERY, "storage_kernel")
        self.uuid_patch = patch.object(RECOVERY.uuid, "uuid4", return_value=SimpleNamespace(hex=self.nonce))
        self.mount_patch = patch.object(RECOVERY, "storage_host_mount_proof", return_value=storage_mount_fixture())
        self.root_patch.start()
        self.kernel_patch.start()
        self.uuid_patch.start()
        self.mount_patch.start()
        self.addCleanup(self.root_patch.stop)
        self.addCleanup(self.kernel_patch.stop)
        self.addCleanup(self.uuid_patch.stop)
        self.addCleanup(self.mount_patch.stop)

    def runner(self, argv):
        self.calls.append(argv)
        arguments = argv[5:]
        if arguments[0] == "create":
            return "c" * 64
        if arguments[0] == "start":
            return self.output
        if arguments[0] == "rm":
            return ""
        if arguments[0] == "ps":
            return "c" * 64
        if arguments[:2] == ["container", "inspect"]:
            if arguments[-2] == RECOVERY.STORAGE_REFERENCE_FORMAT:
                return json.dumps({"Id": "d" * 64, "Mounts": [
                    {"Type": "volume", "Name": "development", "Source": RECOVERY.storage_volume_path("development")} ]})
            mounts, declarations = RECOVERY.storage_expected_mounts(["development"])
            return json.dumps({"Id": "c" * 64, "Image": self.host._verified_storage_image,
                               "Name": "/fireguard-reviewed-storage-" + self.nonce, "Owner": self.nonce,
                               "Mounts": mounts, "Declarations": declarations,
                               "State": {"Status": "created", "Running": False, "Restarting": False, "Paused": False}})
        if arguments[:2] == ["volume", "inspect"]:
            name = arguments[-1]
            return json.dumps({"Name": name, "Driver": "local", "Scope": "local", "OptionsEmpty": True,
                               "Mountpoint": RECOVERY.storage_volume_path(name)})
        raise AssertionError("Unexpected storage operation")

    def inspect(self):
        volume = {"Name": "development", "Driver": "local", "Scope": "local", "OptionsEmpty": True,
                  "Mountpoint": RECOVERY.storage_volume_path("development")}
        return self.host.inspect_storage(["development"], {"development": {"d" * 64}}, {"development": volume})

    def test_actor_is_immutable_offline_readonly_private_and_cleans_only_its_id(self):
        result = self.inspect()
        argv = next(argv[5:] for argv in self.calls if argv[5] == "create")
        for flag, value in (("--pull", "never"), ("--user", "0:0"), ("--cap-drop", "ALL"), ("--network", "none"),
                            ("--pid", ""), ("--runtime", "runc"), ("--ipc", "private"), ("--workdir", "/"),
                            ("--security-opt", "no-new-privileges:true"), ("--entrypoint", "/usr/bin/env")):
            self.assertEqual(value, argv[argv.index(flag) + 1])
        self.assertIn("--read-only", argv)
        self.assertIn("--no-healthcheck", argv)
        self.assertIn("--init=false", argv)
        self.assertEqual("type=bind,src=/var/lib/docker,dst=/var/lib/docker,readonly,bind-recursive=disabled",
                         argv[argv.index("--mount") + 1])
        self.assertFalse(any("bind-propagation" in value for value in argv))
        self.assertEqual(2, argv.count("--mount"))
        self.assertIn("type=volume,src=development,dst=/__fireguard_volume_proof/0,readonly,volume-nocopy", argv)
        self.assertEqual([self.host._verified_storage_image, "-i", "/usr/local/bin/php", "-n", "-r"], argv[-7:-2])
        self.assertEqual({"root": RECOVERY.STORAGE_ROOT, "names": ["development"]}, json.loads(argv[-1]))
        self.assertTrue({"LD_PRELOAD=", "LD_AUDIT=", "LD_LIBRARY_PATH="}.issubset(argv))
        for forbidden in ("--privileged", "--volume", "--env-file", "--health-cmd", "--publish", "--device"):
            self.assertNotIn(forbidden, argv)
        self.assertIn(["rm", "--force", "c" * 64], [argv[5:] for argv in self.calls])
        self.assertEqual(self.chain, result["rootChain"])
        self.assertEqual(storage_mount_fixture(), result["hostMount"])

    def test_host_mount_refusal_precedes_actor_and_source_churn_never_returns_proof(self):
        for code in ("storage-host-source-unbindable", "storage-host-volume-submount", "storage-host-mount-metadata-unverified"):
            with self.subTest(code=code), patch.object(RECOVERY, "storage_host_mount_proof", side_effect=RECOVERY.RecoveryBlocked(code)), \
                 self.assertRaisesRegex(RECOVERY.RecoveryBlocked, code):
                self.inspect()
        self.assertEqual([], self.calls)
        before, after = storage_mount_fixture(), storage_mount_fixture()
        after["mountId"] += 1
        with patch.object(RECOVERY, "storage_host_mount_proof", side_effect=[before, after]), \
             self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "storage-host-source-mount-changed"):
            self.inspect()
        self.assertIsNone(self.host._storage_proof)
        self.assertEqual(["rm", "--force", "c" * 64], self.calls[-1][5:])

    def test_shared_source_is_pinned_without_adding_capabilities_or_changing_named_mount_guards(self):
        proof = RECOVERY.storage_host_mounts("10 1 8:1 / / rw shared:7 - ext4 private rw\n", ["development"])
        with patch.object(RECOVERY, "storage_host_mount_proof", return_value=proof):
            self.assertEqual(proof, self.inspect()["hostMount"])
        create = next(argv for argv in self.calls if argv[5] == "create")
        self.assertEqual("ALL", create[create.index("--cap-drop") + 1])
        self.assertNotIn("--cap-add", create)
        self.assertNotIn("apparmor=unconfined", create)
        changed = copy.deepcopy(proof)
        changed["propagation"]["shared"] += 1
        with patch.object(RECOVERY, "storage_host_mount_proof", side_effect=[proof, changed]), \
             self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "storage-host-source-mount-changed"):
            self.inspect()

    def test_origin_reference_loss_before_create_after_create_or_after_start_never_returns_proof(self):
        original = self.runner
        for phase in (1, 2, 3):
            calls, reference_reads = [], []
            def lost(argv):
                calls.append(argv[5:])
                output = original(argv)
                if argv[-2] == RECOVERY.STORAGE_REFERENCE_FORMAT:
                    reference_reads.append(True)
                    if len(reference_reads) == phase:
                        return json.dumps({"Id": "d" * 64, "Mounts": []})
                return output
            with self.subTest(phase=phase), patch.object(self.host, "run", side_effect=lost), \
                 self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "storage-volume-reference-unverified"):
                self.inspect()
            self.assertIsNone(self.host._storage_proof)
            self.assertEqual(phase != 1, any(item[0] == "create" for item in calls))
            self.assertEqual(phase == 3, any(item[0] == "start" for item in calls))
            self.assertEqual(phase != 1, ["rm", "--force", "c" * 64] in calls)
            self.assertFalse(any(item[0] == "volume" and item[1] in {"create", "rm"} for item in calls))

    def test_missing_origin_reference_or_missing_volume_never_creates_or_starts_actor(self):
        volume = {"Name": "development", "Driver": "local", "Scope": "local", "OptionsEmpty": True,
                  "Mountpoint": RECOVERY.storage_volume_path("development")}
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "storage-volume-reference-unverified"):
            self.host.inspect_storage(["development"], {"development": set()}, {"development": volume})
        self.assertEqual([], self.calls)
        original = self.runner
        def unavailable(argv):
            if argv[5:7] == ["volume", "inspect"]:
                raise RECOVERY.RecoveryBlocked("private-missing-volume-error")
            return original(argv)
        with patch.object(self.host, "run", side_effect=unavailable), \
             self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "docker-isolation-volume-inspect-command-failed"):
            self.inspect()
        self.assertFalse(any(argv[5] in {"create", "start", "rm"} for argv in self.calls))

    def test_actor_mount_bijection_ro_nocopy_and_fixed_targets_are_attested_before_start(self):
        original = self.runner
        mutations = (("Mounts", 1, "Name", "different"), ("Mounts", 1, "Source", "/private-alias"),
                     ("Mounts", 1, "Destination", "/private-target"), ("Mounts", 1, "RW", True),
                     ("Mounts", 1, "RW", 0), ("Declarations", 1, "NoCopy", False),
                     ("Declarations", 0, "NonRecursive", False), ("Declarations", 0, "PropagationEmpty", False))
        for collection, index, key, value in mutations:
            calls = []
            def wrong(argv):
                calls.append(argv[5:])
                output = original(argv)
                if argv[-2] == RECOVERY.STORAGE_ACTOR_FORMAT:
                    record = json.loads(output)
                    record[collection][index][key] = value
                    return json.dumps(record)
                return output
            with self.subTest(key=key, value=value), patch.object(self.host, "run", side_effect=wrong), \
                 self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "storage-inspector-mount-declaration-unverified"):
                self.inspect()
            self.assertFalse(any(item[0] == "start" for item in calls))
            self.assertIn(["rm", "--force", "c" * 64], calls)
        def reordered(argv):
            output = original(argv)
            if argv[-2] == RECOVERY.STORAGE_ACTOR_FORMAT:
                record = json.loads(output)
                record["Mounts"].reverse()
                record["Declarations"].reverse()
                return json.dumps(record)
            return output
        with patch.object(self.host, "run", side_effect=reordered):
            self.assertEqual(["development"], self.inspect()["volumeNames"])

    def test_no_verified_image_or_unattested_docker_root_never_creates_actor(self):
        for field, value in (("_verified_storage_image", None), ("_storage_authority_verified", False)):
            with self.subTest(field=field), patch.object(self.host, field, value):
                with self.assertRaises(RECOVERY.RecoveryBlocked):
                    self.inspect()
        self.assertEqual([], self.calls)

    def test_parent_root_alias_symlink_and_malformed_numeric_metadata_refuse(self):
        cases = []
        for key, value in (("uid", True), ("mode", -1), ("isSymlink", True), ("isDirectory", False), ("gid", 2 ** 32)):
            body = copy.deepcopy(self.body)
            body["nodes"][0]["data"][key] = value
            cases.append(body)
        for target in ("root", "volumes", "directory"):
            body = copy.deepcopy(self.body)
            if target == "root":
                body["root"]["inode"] += 1
            elif target == "volumes":
                body["volumes"]["mode"] |= 0o020
            else:
                body["nodes"][0]["directory"]["uid"] = 1000
            cases.append(body)
        for alias in (self.chain[0], RECOVERY.REVIEWED_NAMESPACE[2], self.body["nodes"][0]["directory"]):
            body = copy.deepcopy(self.body)
            body["nodes"][0]["data"].update(device=alias["device"], inode=alias["inode"])
            cases.append(body)
        for body in cases:
            with self.subTest(body=body), self.assertRaises(RECOVERY.RecoveryBlocked):
                self.output = json.dumps(body)
                self.inspect()
        self.assertTrue(all(argv[-3:] == ["rm", "--force", "c" * 64] for argv in self.calls if argv[5] == "rm"))

    def test_child_fixed_denials_never_export_mount_paths_or_private_errors(self):
        for code in ("submount", "root-mount", "not-readonly", "not-private", "symlink", "changed", "stat-unavailable", "process-proof",
                     "named-mount", "named-identity", "root-shared", "root-unbindable", "named-shared", "named-slave", "named-unbindable"):
            self.output = json.dumps({"ok": False, "code": code})
            with self.subTest(code=code), self.assertRaises(RECOVERY.RecoveryBlocked) as caught:
                self.inspect()
            self.assertEqual("storage-inspector-" + code, str(caught.exception))
        for output in ("private-malformed", json.dumps({"ok": False, "code": "private-path"}),
                       json.dumps({"ok": False, "code": []}), json.dumps({"ok": True, "private": "secret"})):
            self.output = output
            with self.subTest(output=output), self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "metadata-unverified"):
                self.inspect()

    def test_start_failure_or_cancellation_cleans_actor_and_never_returns_proof(self):
        original = self.runner
        for failure in (RECOVERY.RecoveryBlocked("private-stderr"), KeyboardInterrupt()):
            def failing(argv):
                if argv[5] == "start":
                    raise failure
                return original(argv)
            with self.subTest(error=type(failure)), patch.object(self.host, "run", side_effect=failing):
                with self.assertRaises((RECOVERY.RecoveryBlocked, KeyboardInterrupt)):
                    self.inspect()
            self.assertEqual("rm", self.calls[-1][5])
            self.assertIsNone(self.host._storage_proof)

    def test_root_inode_changes_between_actor_and_return_refuses(self):
        changed = copy.deepcopy(self.chain)
        changed[0]["inode"] += 1
        with patch.object(RECOVERY, "storage_root_chain", side_effect=[self.chain, changed]), \
             self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "root-identity-changed"):
            self.inspect()

    def test_explicit_volume_protocol_never_uses_permission_error_as_fallback(self):
        records = [{"Id": "d" * 64, "Mounts": [{"Type": "volume", "Name": "development", "Source": RECOVERY.storage_volume_path("development")}]}]
        with patch.object(RECOVERY, "canonical_storage", side_effect=PermissionError("private")) as canonical:
            self.assertEqual(1, len(self.host.isolation_volumes(records, required_names={"development"})))
            canonical.assert_not_called()
            legacy = RECOVERY.Host(self.runner)
            with self.assertRaises(PermissionError):
                legacy.isolation_volumes(records)
            canonical.assert_called_once()

    def test_partial_create_or_start_failure_cleans_only_attested_nonce_image_name(self):
        for phase in ("create", "start"):
            def failed(argv):
                if argv[5] == phase:
                    self.calls.append(argv)
                    raise RECOVERY.RecoveryBlocked("private-child-value")
                return self.runner(argv)
            with self.subTest(phase=phase), patch.object(self.host, "run", side_effect=failed), \
                 self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "docker-storage-inspector-" + phase + "-command-failed"):
                self.inspect()
            self.assertEqual(["rm", "--force", "c" * 64], self.calls[-1][5:])
        for field in ("Id", "Image", "Owner", "Name", "State"):
            def foreign(argv):
                result = self.runner(argv)
                if argv[5:7] == ["container", "inspect"]:
                    value = json.loads(result)
                    value[field] = "private-foreign-value"
                    return json.dumps(value)
                return result
            before = len(self.calls)
            with self.subTest(field=field), patch.object(self.host, "run", side_effect=foreign), self.assertRaises(RECOVERY.RecoveryBlocked):
                self.inspect()
            self.assertFalse(any(argv[5] in {"rm", "start"} for argv in self.calls[before:]))

    def test_cleanup_failure_or_missing_actor_metadata_never_returns_physical_proof(self):
        for phase in ("ps", "rm"):
            def failed(argv):
                if argv[5] == phase:
                    raise RECOVERY.RecoveryBlocked("private-child-value")
                return self.runner(argv)
            with self.subTest(phase=phase), patch.object(self.host, "run", side_effect=failed), self.assertRaises(RECOVERY.RecoveryBlocked):
                self.inspect()
            self.assertIsNone(self.host._storage_proof)

    def test_only_source_verified_manifest_matching_volumeless_image_can_prepare_inspector(self):
        identity = {"id": "sha256:" + "b" * 64, "volumesEmpty": True, "digests": [IMAGE],
                    "source": "https://github.com/" + RECOVERY.REPOSITORY, "revision": SOURCE}
        for field, value in ((None, None), ("id", "mutable-tag"), ("volumesEmpty", False), ("volumesEmpty", "true"),
                             ("revision", "c" * 40), ("source", "https://github.com/other/repo"), ("manifest", {})):
            inspected = dict(identity)
            if field not in (None, "manifest"):
                inspected[field] = value
            def runner(argv):
                if argv[1] == "pull":
                    return ""
                if argv[1] == "image":
                    return json.dumps(inspected)
                return json.dumps(value if field == "manifest" else MANIFEST)
            host = RECOVERY.Host(runner)
            with self.subTest(field=field):
                if field in {"revision", "source"}:
                    with self.assertRaises(RECOVERY.RecoveryBlocked):
                        host.image_manifest(proof())
                else:
                    host.image_manifest(proof())
                if field is None:
                    host.prepare_storage_inspector(FakeHost().container_records)
                    self.assertEqual(identity["id"], host._verified_storage_image)
                else:
                    with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "storage-source-image-unverified"):
                        host.prepare_storage_inspector(FakeHost().container_records)

    def test_local_option_free_volume_metadata_and_sources_are_required_before_actor(self):
        records = [{"Mounts": [{"Type": "volume", "Name": "development", "Source": RECOVERY.storage_volume_path("development")}]}]
        for field, value in (("Driver", "remote"), ("Scope", "global"), ("OptionsEmpty", False),
                             ("Mountpoint", "/private/other"), ("Name", "different")):
            def wrong(argv):
                result = self.runner(argv)
                volume = json.loads(result)
                volume[field] = value
                return json.dumps(volume)
            before = len(self.calls)
            with self.subTest(field=field), patch.object(self.host, "run", side_effect=wrong), self.assertRaises(RECOVERY.RecoveryBlocked):
                self.host.isolation_volumes(records, required_names={"development"})
            self.assertFalse(any(argv[5] == "create" for argv in self.calls[before:]))
        records[0]["Mounts"][0]["Source"] = "/different"
        with self.assertRaisesRegex(RECOVERY.RecoveryBlocked, "volume-source-unverified"):
            self.host.isolation_volumes(records, required_names={"development"})

    def test_unrelated_root_plugin_volume_remains_typed_inventory_without_physical_probe(self):
        unrelated = {"Name": "unrelated", "Driver": "plugin", "Scope": "global", "OptionsEmpty": False,
                     "Mountpoint": "/unrelated/plugin"}
        records = [{"Mounts": [{"Type": "volume", "Name": "unrelated", "Source": unrelated["Mountpoint"]}]}]
        original = self.runner
        def runner(argv):
            if argv[5:7] == ["volume", "inspect"] and argv[-1] == "unrelated":
                return json.dumps(unrelated)
            return original(argv)
        with patch.object(self.host, "run", side_effect=runner):
            self.assertEqual([unrelated], self.host.isolation_volumes(records, required_names=set()))
        self.assertEqual(["development"], self.host._storage_proof["volumeNames"])
        self.assertEqual(["development"], json.loads(next(argv[-1] for argv in self.calls if argv[5] == "create"))["names"])

    def test_all_credential_slots_groups_and_independent_cgroup_choose_potential_volume_cohort(self):
        snapshot = isolation_fixture()
        owner = snapshot["containers"][0]["Id"]
        snapshot["containers"][0]["Mounts"] = [{"Type": "volume", "Name": "back_app_var"}]
        self.assertEqual({"back_app_var"}, RECOVERY.potential_authority_volume_names(snapshot["processes"], snapshot["containers"], 3))
        for field in ("uids", "gids", "groups"):
            for slot in range(4 if field != "groups" else 1):
                state = copy.deepcopy(snapshot)
                for process in state["processes"]:
                    if process["pid"] not in {100, 110}:
                        continue
                    for task in process["tasks"]:
                        task["uids"], task["gids"], task["groups"] = [2000] * 4, [2000] * 4, []
                task = next(process for process in state["processes"] if process["pid"] == 100)["tasks"][0]
                if field == "groups":
                    task[field] = [1000]
                else:
                    task[field][slot] = 1000
                with self.subTest(field=field, slot=slot):
                    self.assertEqual({"back_app_var"}, RECOVERY.potential_authority_volume_names(state["processes"], state["containers"], 3))
                    task["containerId"] = None
                    self.assertEqual(set(), RECOVERY.potential_authority_volume_names(state["processes"], state["containers"], 3))
        self.assertIsNotNone(owner)

    def test_protected_root_chain_and_kernel_refusals_precede_docker_mutation(self):
        self.root_patch.stop()
        self.kernel_patch.stop()
        for release in ("5.11.99", "4.19.0", "private-invalid"):
            with self.subTest(release=release), patch.object(RECOVERY.os, "uname", return_value=SimpleNamespace(release=release), create=True), \
                 self.assertRaises(RECOVERY.RecoveryBlocked):
                self.inspect()
        for field, value in (("st_uid", 1000), ("st_mode", RECOVERY.stat.S_IFDIR | 0o775), ("st_mode", RECOVERY.stat.S_IFLNK | 0o777)):
            for depth in range(4):
                values = [SimpleNamespace(st_dev=item["device"], st_ino=item["inode"], st_uid=item["uid"], st_gid=item["gid"],
                                          st_mode=RECOVERY.stat.S_IFDIR | item["mode"]) for item in self.chain]
                setattr(values[depth], field, value)
                with self.subTest(field=field, depth=depth), patch.object(RECOVERY, "storage_kernel"), \
                     patch.object(RECOVERY.Path, "lstat", side_effect=values), self.assertRaises(RECOVERY.RecoveryBlocked):
                    self.inspect()
        self.assertEqual([], self.calls)


@unittest.skipUnless(shutil.which("php"), "PHP runtime required for hermetic parser validation")
class StoragePHPParserTests(unittest.TestCase):
    def php(self, expression, payload):
        functions = RECOVERY.STORAGE_INSPECT_PHP.split("\ntry {", 1)[0]
        result = subprocess.run([shutil.which("php"), "-n", "-r", functions + expression, json.dumps(payload)],
                                capture_output=True, timeout=30, check=False)
        self.assertEqual(0, result.returncode, "Fixed metadata PHP must execute without extensions or configuration")
        return json.loads(result.stdout)

    def test_actual_php_mount_parser_refuses_name_data_children_stacks_and_rw(self):
        root = RECOVERY.STORAGE_ROOT
        base = "10 1 8:1 / " + root + " ro - ext4 /private-source rw\n"
        expression = "$v=json_decode($argv[1],true);storage_mounts($v['text'],$v['root'],[],[$v['name']]);echo json_encode(['ok'=>true]);"
        cases = [(base, True)]
        for point in (root + "/volumes", root + "/volumes/fixture", root + "/volumes/fixture/_data", root + "/volumes/fixture/_data/child"):
            cases.append((base + "11 10 8:2 / " + point + " ro - ext4 /private-second rw\n", False))
        cases.extend(((base + base, False), (base.replace(" ro -", " rw -"), False),
                      (base.replace(" ro -", " ro shared:7 -"), False), (base.replace(" ro -", " ro master:7 -"), True),
                      (base.replace(" ro -", " ro master:7 propagate_from:8 -"), True), (base.replace(" ro -", " ro unbindable -"), False),
                      ("malformed-private-metadata", False),
                      (base + "11 10 8:2 / " + root + "/overlay2/fixture/merged rw - overlay /private-source rw\n", True),
                      (base + "11 10 8:2 / " + root + "/volumes/fixture-other ro - ext4 /private-source rw\n", True),
                      (base + "11 10 8:2 / /unrelated ro - ext4 /private-source rw\n", True)))
        for text, allowed in cases:
            with self.subTest(text=text):
                result = self.php(expression, {"text": text, "root": root, "name": root + "/volumes/fixture"})
                self.assertIs(result["ok"], allowed)
                self.assertNotIn("/private-source", json.dumps(result))
                self.assertNotIn("/private-second", json.dumps(result))

    def test_actual_php_requires_zero_credentials_caps_and_private_pid_nnp(self):
        values = {"Uid": "0 0 0 0", "Gid": "0 0 0 0", "NoNewPrivs": "1", "Pid": "1", "Tgid": "1", "NSpid": "1", "NStgid": "1"}
        values.update({key: "0000000000000000" for key in ("CapEff", "CapPrm", "CapInh", "CapAmb", "CapBnd")})
        expression = "storage_process(json_decode($argv[1],true));echo json_encode(['ok'=>true]);"
        def status(fields):
            return "\n".join(key + ": " + value for key, value in fields.items())
        self.assertIs(self.php(expression, status(values))["ok"], True)
        for key in values:
            for kind in ("missing", "nonzero", "duplicate"):
                invalid = dict(values)
                if kind == "missing":
                    del invalid[key]
                else:
                    invalid[key] = "1 0 0 0" if key in {"Uid", "Gid"} else "0000000000000001" if key.startswith("Cap") else "0"
                text = status(invalid) if kind != "duplicate" else status(values) + "\n" + key + ": " + values[key]
                with self.subTest(key=key, kind=kind):
                    self.assertEqual({"ok": False, "code": "process-proof"}, self.php(expression, text))

    def test_named_target_requires_unique_readonly_private_or_slave_mount_without_any_descendant(self):
        root, target = RECOVERY.STORAGE_ROOT, "/__fireguard_volume_proof/0"
        base = "10 1 8:1 / " + root + " ro - ext4 private rw\n"
        named = "20 1 8:1 /volumes/fixture/_data " + target + " ro - ext4 private rw\n"
        expression = "$v=json_decode($argv[1],true);storage_mounts($v['text'],$v['root'],[$v['target']],[]);echo json_encode(['ok'=>true]);"
        for optional in ("", "master:3", "master:3 propagate_from:4", "propagate_from:4 master:3",
                         "master:4294967295 propagate_from:4294967295"):
            text = base + named.replace(" ro -", " ro" + (" " + optional if optional else "") + " -")
            with self.subTest(optional=optional):
                self.assertEqual({"ok": True}, self.php(expression, {"text": text, "root": root, "target": target}))
        for text in (base, base + named + named, base + named.replace(" ro -", " rw -"),
                     base + named.replace(" ro -", " ro shared:3 -"), base + named.replace(" ro -", " ro shared:3 master:4 -"),
                     base + named.replace(" ro -", " ro unbindable -"),
                     base + named + "21 20 8:2 / " + target + "/child ro - ext4 private rw\n"):
            with self.subTest(text=text):
                result = self.php(expression, {"text": text, "root": root, "target": target})
                self.assertFalse(result["ok"])
                self.assertNotIn(target, json.dumps(result))

    def test_propagation_denials_identify_only_fixed_root_or_named_category(self):
        root, target = RECOVERY.STORAGE_ROOT, "/__fireguard_volume_proof/0"
        base = "10 1 8:1 / " + root + " ro master:701 - ext4 /private-source rw\n"
        named = "20 1 8:1 /volumes/fixture/_data " + target + " ro - ext4 /private-source rw\n"
        expression = "$v=json_decode($argv[1],true);storage_mounts($v['text'],$v['root'],[$v['target']],[]);echo json_encode(['ok'=>true]);"
        cases = (
            (base.replace("master:701", "shared:702") + named, "root-shared"),
            (base.replace("master:701", "unbindable") + named, "root-unbindable"),
            (base + named.replace(" ro -", " ro shared:703 -"), "named-shared"),
            (base + named.replace(" ro -", " ro master:0 -"), "mount-metadata"),
            (base + named.replace(" ro -", " ro propagate_from:705 -"), "mount-metadata"),
            (base + named.replace(" ro -", " ro unbindable -"), "named-unbindable"),
        )
        for text, code in cases:
            with self.subTest(code=code):
                result = self.php(expression, {"text": text, "root": root, "target": target})
                self.assertEqual({"ok": False, "code": code}, result)
                for private in (root, target, "/private-source", "701", "702", "703", "704", "705"):
                    self.assertNotIn(private, json.dumps(result))

    def test_root_and_named_slave_tags_are_positive_bounded_unique_and_complete(self):
        root, target = RECOVERY.STORAGE_ROOT, "/__fireguard_volume_proof/0"
        base = "10 1 8:1 / " + root + " ro - ext4 private rw\n"
        named = "20 1 8:1 /volumes/fixture/_data " + target + " ro - ext4 private rw\n"
        expression = "$v=json_decode($argv[1],true);storage_mounts($v['text'],$v['root'],[$v['target']],[]);echo json_encode(['ok'=>true]);"
        malformed = ("master:0", "master:-1", "master:+1", "master:01", "master:4294967296", "master:99999999999", "master:",
                     "master:PRIVATE_VALUE", "master:1 master:2", "master:1 master:1", "propagate_from:2",
                     "master:1 propagate_from:0", "master:1 propagate_from:4294967296",
                     "master:1 propagate_from:2 propagate_from:3", "unknown:1")
        for category in ("root", "named"):
            for optional in malformed:
                changed = (base if category == "root" else named).replace(" ro -", " ro " + optional + " -")
                text = changed + named if category == "root" else base + changed
                with self.subTest(category=category, optional=optional):
                    result = self.php(expression, {"text": text, "root": root, "target": target})
                    self.assertEqual({"ok": False, "code": "mount-metadata"}, result)
                    self.assertNotIn("PRIVATE", json.dumps(result))

    def test_repeated_php_proof_ignores_unrelated_overlay_churn_but_rejects_relevant_propagation(self):
        root, target = RECOVERY.STORAGE_ROOT, "/__fireguard_volume_proof/0"
        base = ("10 1 8:1 / " + root + " ro master:7 - ext4 private rw\n"
                "20 1 8:1 /volumes/fixture/_data " + target + " ro master:9 propagate_from:10 - ext4 private rw\n")
        expression = ("$v=json_decode($argv[1],true);$p=storage_mounts($v['before'],$v['root'],[$v['target']],[$v['name']]);"
                      "$q=storage_mounts($v['after'],$v['root'],[$v['target']],[$v['name']]);echo json_encode(['ok'=>true,'same'=>$p===$q]);")
        fixture = {"before": base, "root": root, "target": target, "name": root + "/volumes/fixture"}
        unrelated = "30 10 8:2 / " + root + "/overlay2/unrelated/merged rw - overlay private rw\n"
        self.assertEqual({"ok": True, "same": True}, self.php(expression, fixture | {"after": base + unrelated}))
        for after in (base.replace("10 1", "11 1"), base.replace("master:7", "master:8"), base.replace("20 1", "21 1"),
                      base.replace("master:9", "master:11"), base.replace("propagate_from:10", "propagate_from:12")):
            with self.subTest(after=after):
                self.assertEqual({"ok": True, "same": False}, self.php(expression, fixture | {"after": after}))
        for point in (root + "/volumes", root + "/volumes/fixture", root + "/volumes/fixture/_data", root + "/volumes/fixture/_data/child",
                      target + "/child"):
            after = base + "30 10 8:2 / " + point + " rw master:8 - ext4 private rw\n"
            with self.subTest(point=point):
                self.assertEqual({"ok": False, "code": "submount"}, self.php(expression, fixture | {"after": after}))

    def test_named_effective_data_identity_matches_all_numeric_stat_fields_and_detects_recreation(self):
        with tempfile.TemporaryDirectory() as directory:
            expected, different = Path(directory) / "expected", Path(directory) / "different"
            expected.mkdir()
            different.mkdir()
            expression = "$v=json_decode($argv[1],true);$s=storage_stat($v['expected']);storage_named_identity($v['target'],$s);echo json_encode(['ok'=>true]);"
            self.assertEqual({"ok": True}, self.php(expression, {"expected": str(expected), "target": str(expected)}))
            self.assertEqual({"ok": False, "code": "named-identity"}, self.php(expression, {"expected": str(expected), "target": str(different)}))
            expression = "$v=json_decode($argv[1],true);$s=storage_stat($v['target']);$s[$v['field']]++;storage_named_identity($v['target'],$s);echo json_encode(['ok'=>true]);"
            for field in ("device", "inode", "uid", "gid", "mode"):
                with self.subTest(field=field):
                    self.assertEqual({"ok": False, "code": "named-identity"}, self.php(expression, {"target": str(expected), "field": field}))

    def test_entire_php_main_is_warning_free_and_reads_only_fixed_proc_and_stat_metadata(self):
        chain, body = storage_fixture()
        root, target = RECOVERY.STORAGE_ROOT, "/__fireguard_volume_proof/0"
        records = {root: body["root"], root + "/volumes": body["volumes"],
                   root + "/volumes/development": body["nodes"][0]["directory"],
                   root + "/volumes/development/_data": body["nodes"][0]["data"], target: body["nodes"][0]["data"]}
        stats = {path: {"dev": node["device"], "ino": node["inode"], "uid": node["uid"], "gid": node["gid"],
                        "mode": RECOVERY.stat.S_IFDIR | node["mode"]} for path, node in records.items()}
        status = "Uid: 0 0 0 0\nGid: 0 0 0 0\nNoNewPrivs: 1\nPid: 1\nTgid: 1\nNSpid: 1\nNStgid: 1\n"
        status += "\n".join(key + ": 0000000000000000" for key in ("CapEff", "CapPrm", "CapInh", "CapAmb", "CapBnd"))
        mountinfo = ("10 1 8:1 / " + root + " ro master:7 - ext4 private rw\n"
                     "20 1 8:1 /volumes/development/_data " + target + " ro - ext4 private rw\n")
        prefix = r'''
namespace FireguardRecoveryFixture;
use \Throwable;
$fixture = json_decode($argv[2], true, 16, JSON_THROW_ON_ERROR);
function file_get_contents($path) {
  global $fixture;
  if ($path === '/proc/self/status') return $fixture['status'];
  if ($path === '/proc/self/mountinfo') {
    return is_array($fixture['mountinfo']) ? array_shift($fixture['mountinfo']) : $fixture['mountinfo'];
  }
  throw new \RuntimeException('Unexpected metadata read');
}
function lstat($path) {
  global $fixture;
  if (!array_key_exists($path, $fixture['stats'])) throw new \RuntimeException('Unexpected stat metadata');
  $fixture['statReads'][$path] = ($fixture['statReads'][$path] ?? 0) + 1;
  if ($fixture['statReads'][$path] > 1 && isset($fixture['changedStats'][$path])) return $fixture['changedStats'][$path];
  return $fixture['stats'][$path];
}
function clearstatcache($realpath, $path) {}
set_error_handler(static function($severity, $message) { throw new \ErrorException('Fixture PHP warning'); });
'''
        fixture = {"status": status, "mountinfo": mountinfo, "stats": stats}
        denials = {"bad-process": "process-proof", "unexpected-stat": "metadata", "changed-named-master": "changed",
                   "new-named-descendant": "submount", "named-final-shared": "named-shared", "named-final-rw": "not-readonly",
                   "named-data-changed": "named-identity", "root-data-changed": "changed"}
        for kind in ("valid", "valid-named-slave", *denials):
            metadata = copy.deepcopy(fixture)
            if kind != "valid":
                slave = mountinfo.replace(target + " ro -", target + " ro master:9 propagate_from:10 -")
                metadata["mountinfo"] = [slave, slave]
            if kind == "bad-process":
                metadata["status"] = status.replace("NoNewPrivs: 1", "NoNewPrivs: 0")
            elif kind == "unexpected-stat":
                del metadata["stats"][root + "/volumes/development/_data"]
            elif kind == "changed-named-master":
                metadata["mountinfo"][1] = slave.replace("master:9", "master:11")
            elif kind == "new-named-descendant":
                metadata["mountinfo"][1] += "30 20 8:2 / " + target + "/child rw master:9 - ext4 private rw\n"
            elif kind == "named-final-shared":
                metadata["mountinfo"][1] = slave.replace("master:9", "shared:11 master:9")
            elif kind == "named-final-rw":
                metadata["mountinfo"][1] = slave.replace(target + " ro ", target + " rw ")
            elif kind in {"named-data-changed", "root-data-changed"}:
                path = target if kind == "named-data-changed" else root + "/volumes/development/_data"
                changed = dict(stats[path])
                changed["ino"] += 1
                metadata["changedStats"] = {path: changed}
            result = subprocess.run([shutil.which("php"), "-n", "-r", prefix + RECOVERY.STORAGE_INSPECT_PHP,
                                     json.dumps({"root": root, "names": ["development"]}), json.dumps(metadata)],
                                    capture_output=True, timeout=30, check=False)
            self.assertEqual(0, result.returncode, "Entire fixed PHP main must execute")
            with self.subTest(kind=kind):
                parsed = json.loads(result.stdout)  # A warning prefix or trailing output is a test failure.
                expected = {"ok": False, "code": denials[kind]} if kind in denials else body
                self.assertEqual(expected, parsed)
                self.assertEqual(b"", result.stderr)


NATIVE_ACTOR_FAILURE_FORMAT = ('{"Status":{{json .State.Status}},"Running":{{json .State.Running}},'
                               '"ExitCode":{{json .State.ExitCode}},"Error":{{json .State.Error}}}')
NATIVE_VERSION_FORMAT = '{"clientVersion":{{json .Client.Version}},"serverVersion":{{json .Server.Version}}}'


def native_storage_output(result):
    """Classify a fixture-owned actor's invalid JSON without publishing its output."""
    output = result.stdout if type(result.stdout) is bytes else b""
    suffix_code = None
    if not output.strip():
        category = "empty-output"
        text = ""
    elif len(output) > 2 * 1024 * 1024:
        category = "output-limit"
        text = ""
    else:
        try:
            text = output.decode("utf-8")
            json.loads(text)
            return None  # Valid JSON still goes through all normal helper checks.
        except UnicodeDecodeError:
            category, text = "invalid-encoding", ""
        except ValueError:
            text = text.strip()
            category = "malformed-json"
            try:
                unused, end = json.JSONDecoder().raw_decode(text)
                if text[end:].strip():
                    category = "output-suffix"
            except ValueError:
                pass
            if re.search(r"(?:^|\n)(?:PHP )?(?:Fatal error|Parse error):", text):
                category = "php-error"
            elif re.search(r"(?:^|\n)(?:PHP )?(?:Warning|Notice|Deprecated):", text):
                category = "php-warning"
                opening = text.find("{")
                if opening >= 0:
                    try:
                        suffix = json.loads(text[opening:])
                        category = "json-warning"
                        codes = {"stat-unavailable", "symlink", "not-directory", "mount-metadata", "not-readonly", "not-private",
                                 "root-shared", "root-unbindable", "named-shared", "named-slave", "named-unbindable",
                                 "submount", "root-mount", "input", "changed", "metadata", "process-proof", "named-mount", "named-identity"}
                        if type(suffix) is dict and set(suffix) == {"ok", "code"} and suffix["ok"] is False \
                                and type(suffix["code"]) is str and suffix["code"] in codes:
                            suffix_code = suffix["code"]  # Informational only: the warning remains a test failure.
                    except ValueError:
                        pass
    reason = "unclassified"
    signatures = (("undefined-variable", "undefined variable"), ("undefined-array-key", "undefined array key"),
                  ("pcre-jit-compilation", "jit compilation failed"), ("pcre-pattern", "compilation failed"),
                  ("php-startup-timezone", "invalid date.timezone value"), ("php-startup-dynamic-module", "unable to load dynamic library"),
                  ("php-startup", "php startup:"), ("array-to-string", "array to string conversion"),
                  ("stat-metadata", "lstat():"), ("proc-metadata", "file_get_contents():"))
    for candidate, fragment in signatures:
        if fragment in text[:16384].lower():
            reason = candidate
            break
    return "Native storage actor output failed " + json.dumps({"category": category, "phpReason": reason,
            "stdoutBytes": len(output) if len(output) <= 2 * 1024 * 1024 else None,
            "stderrPresent": bool(result.stderr), "suffixCode": suffix_code}, sort_keys=True)


def native_timezone_archive(runtime):
    # Debian/Ubuntu PHP validates its default UTC against system tzdata even with -n.
    # Public package data only; preserve UTC as a regular file, not a dangling Etc/UTC symlink.
    data = Path("/usr/share/zoneinfo/UTC").resolve(strict=True).read_bytes()
    if not (20 < len(data) <= 4096 and data.startswith(b"TZif")):
        raise AssertionError("Native UTC package data is unavailable or invalid")
    entry = tarfile.TarInfo("usr/share/zoneinfo/UTC")
    entry.size, entry.mode = len(data), 0o444
    runtime.addfile(entry, io.BytesIO(data))


def native_docker_versions(run=subprocess.run):
    try:
        result = run(["/usr/bin/env", "DOCKER_API_VERSION=1.45", "docker", "--host", "unix:///var/run/docker.sock",
                      "version", "--format", NATIVE_VERSION_FORMAT], capture_output=True, timeout=30, check=False)
        if result.returncode != 0 or len(result.stdout) > 1024:
            return {}
        value = json.loads(result.stdout)
        return value if type(value) is dict else {}
    except (OSError, subprocess.TimeoutExpired, ValueError, TypeError):
        return {}  # Informational only: preserve the failing command's refusal.


def native_docker_failure(arguments, result, actor_state=None, versions=None):
    """Fixed enums and numeric metadata only; never publish child output or argv."""
    phases = {("create",): "create", ("start",): "start", ("ps",): "list", ("rm",): "remove",
              ("info",): "info", ("version",): "version", ("import",): "image-import",
              ("container", "inspect"): "container-inspect", ("image", "inspect"): "image-inspect",
              ("image", "rm"): "image-remove", ("volume", "inspect"): "volume-inspect",
              ("volume", "create"): "volume-create", ("volume", "rm"): "volume-remove"}
    key = tuple(arguments[:2]) if arguments and arguments[0] in {"container", "image", "volume"} else tuple(arguments[:1])
    phase = phases.get(key, "unknown-operation")
    text = b"\n".join(value[:16384] for value in (result.stdout, result.stderr) if type(value) is bytes).decode("utf-8", errors="replace").lower()
    state = actor_state if type(actor_state) is dict else {}
    if type(state.get("Error")) is str:
        text += "\n" + state["Error"][:16384].lower()
    category = "unclassified"
    signatures = (("unknown-flag", ("unknown flag", "unknown shorthand flag")),
                  ("invalid-mount", ("invalid mount config", "invalid mount ", "unexpected key", "bind-recursive", "bind-propagation", "readonlyforcerecursive")),
                  ("api-version", ("client version", "api version", "server version")),
                  ("image", ("no such image", "invalid reference format", "unable to find image", "image not found")),
                  ("daemon-connection", ("cannot connect to the docker daemon", "error during connect", "is the docker daemon running", "connection refused")),
                  ("missing-library", ("error while loading shared libraries", "cannot open shared object file")),
                  ("exec-format", ("exec format error",)),
                  ("missing-executable", ("executable file not found", "no such file or directory")),
                  ("permission", ("permission denied", "operation not permitted")),
                  ("mount", ("error mounting", "failed to mount", "mount_setattr", "read-only file system", "mount callback failed")),
                  ("runtime", ("oci runtime", "runc create failed", "failed to create shim", "unknown or invalid runtime name", "unknown runtime", "invalid runtime")),
                  ("invalid-argument", ("invalid argument", "invalid value", "invalid mode", "conflicts with", "unsupported", "not supported")),
                  ("daemon-request", ("error response from daemon",)))
    for candidate, fragments in signatures:
        if any(fragment in text for fragment in fragments):
            category = candidate
            break
    status = state.get("Status")
    diagnostic = {"phase": phase, "category": category,
                  "dockerExitCode": result.returncode if type(result.returncode) is int and -255 <= result.returncode <= 255 else None,
                  "actorStatus": status if type(status) is str and status in {"created", "running", "exited", "dead"} else "unavailable",
                  "actorExitCode": state.get("ExitCode") if type(state.get("ExitCode")) is int and 0 <= state["ExitCode"] <= 255 else None,
                  "actorRunning": state.get("Running") if type(state.get("Running")) is bool else None,
                  "stdoutPresent": bool(result.stdout), "stderrPresent": bool(result.stderr)}
    for key in ("clientVersion", "serverVersion"):
        value = versions.get(key) if type(versions) is dict else None
        match = re.match(r"([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})(?:$|[-+])", value) if type(value) is str else None
        diagnostic[key] = match.group(1) if match else "unavailable"
    return "Native Docker fixture failed " + json.dumps(diagnostic, sort_keys=True)


class NativeDockerDiagnosticTests(unittest.TestCase):
    def test_public_timezone_archive_contains_only_regular_readonly_utc_data(self):
        data = b"TZif2" + bytes(109)
        path = MagicMock()
        path.resolve.return_value.read_bytes.return_value = data
        archive = io.BytesIO()
        with patch(__name__ + ".Path", return_value=path) as factory, tarfile.open(fileobj=archive, mode="w") as runtime:
            native_timezone_archive(runtime)
        factory.assert_called_once_with("/usr/share/zoneinfo/UTC")
        path.resolve.assert_called_once_with(strict=True)
        archive.seek(0)
        with tarfile.open(fileobj=archive, mode="r") as runtime:
            self.assertEqual(["usr/share/zoneinfo/UTC"], runtime.getnames())
            member = runtime.getmembers()[0]
            self.assertTrue(member.isfile())
            self.assertEqual((0o444, 0, 0), (member.mode, member.uid, member.gid))
            self.assertEqual(data, runtime.extractfile(member).read())
        for invalid in (b"PRIVATE_CONTENT", b"TZif" + bytes(16), b"TZif" + bytes(4093)):
            path.resolve.return_value.read_bytes.return_value = invalid
            with patch(__name__ + ".Path", return_value=path), self.assertRaisesRegex(AssertionError, "Native UTC package data"):
                native_timezone_archive(MagicMock())

    def test_startup_subtypes_and_json_suffix_publish_only_allowlisted_fixed_codes(self):
        for startup, reason in (("Invalid date.timezone value 'PRIVATE_VALUE'", "php-startup-timezone"),
                                ("Unable to load dynamic library PRIVATE_LIBRARY", "php-startup-dynamic-module")):
            for code in ("process-proof", "mount-metadata", "PRIVATE_CODE", [], "metadata", "root-shared", "root-unbindable",
                         "named-shared", "named-slave", "named-unbindable"):
                output = "Warning: PHP Startup: " + startup + "\n" + json.dumps({"ok": False, "code": code})
                public = native_storage_output(subprocess.CompletedProcess([], 0, stdout=output.encode(), stderr=b""))
                self.assertNotIn("PRIVATE", public)
                fields = json.loads(public.removeprefix("Native storage actor output failed "))
                self.assertEqual(reason, fields["phpReason"])
                self.assertEqual("json-warning", fields["category"])
                self.assertEqual(code if type(code) is str and code != "PRIVATE_CODE" else None, fields["suffixCode"])

    def test_owned_actor_json_diagnostics_are_fixed_and_never_export_output_or_paths(self):
        cases = ((b"", "empty-output", "unclassified"),
                 (b'{"ok":true}', None, None),
                 (b'Warning: Undefined variable $PRIVATE_NAME in /PRIVATE_PATH on line 9\n{"ok":false,"code":"metadata"}', "json-warning", "undefined-variable"),
                 (b'PHP Warning: preg_match(): JIT compilation failed: PRIVATE_REASON\n{"ok":false}', "json-warning", "pcre-jit-compilation"),
                 (b'Warning: Undefined array key "PRIVATE_KEY" in PRIVATE_PATH', "php-warning", "undefined-array-key"),
                 (b'PHP Fatal error: PRIVATE_MESSAGE in PRIVATE_PATH', "php-error", "unclassified"),
                 (b'{"ok":false}PRIVATE_SUFFIX', "output-suffix", "unclassified"),
                 (b'PRIVATE_UNKNOWN', "malformed-json", "unclassified"),
                 (b'\xffPRIVATE_ENCODING', "invalid-encoding", "unclassified"),
                 (b'x' * (2 * 1024 * 1024 + 1), "output-limit", "unclassified"))
        for output, category, reason in cases:
            with self.subTest(category=category):
                public = native_storage_output(subprocess.CompletedProcess(["PRIVATE_ARGV"], 0, stdout=output, stderr=b"PRIVATE_STDERR"))
                if category is None:
                    self.assertIsNone(public)
                    continue
                self.assertNotIn("PRIVATE", public)
                fields = json.loads(public.removeprefix("Native storage actor output failed "))
                self.assertEqual(category, fields["category"])
                self.assertEqual(reason, fields["phpReason"])
                self.assertTrue(fields["stderrPresent"])

    def test_create_rejection_classes_include_primary_docker_validation_and_api_errors(self):
        samples = (("unknown flag: --PRIVATE_OPTION", "unknown-flag"),
                   ("invalid mount config for type bind: PRIVATE_SOURCE", "invalid-mount"),
                   ("unexpected key PRIVATE_OPTION in PRIVATE_MOUNT", "invalid-mount"),
                   ("bind-recursive PRIVATE_OPTION not supported", "invalid-mount"),
                   ("client version 1.45 is too old: Minimum supported API version is 1.47", "api-version"),
                   ("unknown or invalid runtime name: PRIVATE_RUNTIME", "runtime"),
                   ("invalid argument PRIVATE_VALUE", "invalid-argument"),
                   ("No such image: PRIVATE_IMAGE", "image"),
                   ("invalid reference format PRIVATE_IMAGE", "image"),
                   ("Cannot connect to the Docker daemon at PRIVATE_SOCKET", "daemon-connection"),
                   ("Error response from daemon: PRIVATE_UNCLASSIFIED", "daemon-request"))
        for message, category in samples:
            result = subprocess.CompletedProcess([], 1, stdout=b"", stderr=message.encode())
            with self.subTest(category=category):
                public = native_docker_failure(["create"], result, versions={"clientVersion": "28.0.4+PRIVATE_BUILD", "serverVersion": "28.1.1"})
                self.assertNotIn("PRIVATE", public)
                fields = json.loads(public.removeprefix("Native Docker fixture failed "))
                self.assertEqual(category, fields["category"])
                self.assertEqual("create", fields["phase"])
                self.assertEqual("28.0.4", fields["clientVersion"])
                self.assertEqual("28.1.1", fields["serverVersion"])

    def test_diagnostic_version_query_is_fixed_numeric_only_and_failure_never_overrides_original_refusal(self):
        run = MagicMock(return_value=subprocess.CompletedProcess([], 0, stdout=b'{"clientVersion":"28.0.4","serverVersion":"28.1.1"}', stderr=b"PRIVATE_UNUSED"))
        self.assertEqual("28.0.4", native_docker_versions(run)["clientVersion"])
        self.assertEqual(["/usr/bin/env", "DOCKER_API_VERSION=1.45", "docker", "--host", "unix:///var/run/docker.sock", "version", "--format", NATIVE_VERSION_FORMAT], run.call_args.args[0])
        result = subprocess.CompletedProcess([], 1, stdout=b"", stderr=b"PRIVATE_FAILURE")
        for value in ("PRIVATE_VERSION", True, "9999.1.1", None):
            message = native_docker_failure(["create"], result, versions={"clientVersion": value})
            self.assertNotIn("PRIVATE", message)
            self.assertEqual("unavailable", json.loads(message.removeprefix("Native Docker fixture failed "))["clientVersion"])
        for failure in (PermissionError("PRIVATE_PATH"), subprocess.TimeoutExpired("PRIVATE_ARGV", 1)):
            self.assertEqual({}, native_docker_versions(MagicMock(side_effect=failure)))
        for output in (b"PRIVATE_MALFORMED", b"[]", b"x" * 1025):
            self.assertEqual({}, native_docker_versions(lambda *args, **kwargs: subprocess.CompletedProcess([], 0, stdout=output)))

    def test_fixed_classification_never_exports_commands_env_paths_or_child_text(self):
        samples = ((b"OCI runtime create failed: error mounting /PRIVATE_SOURCE", "mount"),
                   (b"/PRIVATE_ELF: error while loading shared libraries: PRIVATE_LIBRARY: cannot open shared object file", "missing-library"),
                   (b"exec /PRIVATE_EXECUTABLE: no such file or directory", "missing-executable"),
                   (b"PRIVATE_COMMAND: permission denied", "permission"),
                   (b"PRIVATE_ELF: exec format error", "exec-format"),
                   (b"OCI runtime create failed: PRIVATE_OPTIONS", "runtime"),
                   (b"PRIVATE_UNKNOWN_OPTIONS", "unclassified"))
        for stderr, category in samples:
            result = subprocess.CompletedProcess(["PRIVATE_ARGV"], 1, stdout=b"", stderr=stderr)
            with self.subTest(category=category):
                message = native_docker_failure(["start", "--attach", "PRIVATE_ID"], result,
                                                {"Status": "created", "Running": False, "ExitCode": 0, "Error": ""})
                self.assertNotIn("PRIVATE", message)
                diagnostic = json.loads(message.removeprefix("Native Docker fixture failed "))
                self.assertEqual(category, diagnostic["category"])
                self.assertEqual("start", diagnostic["phase"])
                self.assertEqual("created", diagnostic["actorStatus"])
                self.assertEqual(0, diagnostic["actorExitCode"])

    def test_owned_actor_state_error_is_classified_without_export_and_malformed_fields_are_bounded(self):
        result = subprocess.CompletedProcess([], 1, stdout=b"", stderr=b"")
        message = native_docker_failure(["start"], result, {"Status": "created", "Running": False, "ExitCode": 0,
                                                           "Error": "OCI runtime: error mounting /PRIVATE_ROOT"})
        self.assertEqual("mount", json.loads(message.removeprefix("Native Docker fixture failed "))["category"])
        self.assertNotIn("PRIVATE", message)
        for state in ({"Status": ["PRIVATE_STATE"], "ExitCode": True, "Running": 1, "Error": []}, None):
            diagnostic = json.loads(native_docker_failure(["PRIVATE_OPERATION"], result, state).removeprefix("Native Docker fixture failed "))
            self.assertEqual("unknown-operation", diagnostic["phase"])
            self.assertEqual("unavailable", diagnostic["actorStatus"])
            self.assertIsNone(diagnostic["actorExitCode"])
            self.assertIsNone(diagnostic["actorRunning"])


@unittest.skipUnless(os.environ.get("FIREGUARD_TEST_DOCKER_PROJECTIONS") == "1" and os.environ.get("CI") == "true",
                     "Native Docker projection fixture runs only in explicitly enabled CI")
class NativeDockerProjectionTests(unittest.TestCase):
    def test_offline_stopped_owned_fixture_exercises_real_typed_go_projections(self):
        nonce = uuid.uuid4().hex
        name = "fireguard-recovery-proof-" + nonce
        owned_actor_ids = set()
        def docker(arguments, *, data=None):
            result = subprocess.run(["/usr/bin/env", "DOCKER_API_VERSION=1.45", "docker", "--host", "unix:///var/run/docker.sock", *arguments],
                                    input=data, capture_output=True, timeout=60, check=False)
            if result.returncode != 0:
                state = None
                if arguments[:2] == ["start", "--attach"] and len(arguments) == 3 and arguments[-1] in owned_actor_ids:
                    # This fixture-owned actor was attested by the helper before start.
                    # Read State.Error privately, then publish only its fixed category.
                    try:
                        inspection = subprocess.run(["/usr/bin/env", "DOCKER_API_VERSION=1.45", "docker", "--host", "unix:///var/run/docker.sock",
                                                     "container", "inspect", "--format", NATIVE_ACTOR_FAILURE_FORMAT, arguments[-1]],
                                                    capture_output=True, timeout=30, check=False)
                    except (OSError, subprocess.TimeoutExpired):
                        inspection = None  # Diagnostic-only failure never changes the original refusal.
                    if inspection is not None and inspection.returncode == 0 and len(inspection.stdout) <= 16384:
                        try:
                            state = json.loads(inspection.stdout)
                        except (ValueError, TypeError):
                            pass
                self.fail(native_docker_failure(arguments, result, state, native_docker_versions()))
            if arguments[:2] == ["start", "--attach"] and len(arguments) == 3 and arguments[-1] in owned_actor_ids:
                diagnostic = native_storage_output(result)
                if diagnostic is not None:
                    self.fail(diagnostic)
            if arguments[0] == "create" and "--name" in arguments and "--label" in arguments:
                actor_name = arguments[arguments.index("--name") + 1]
                actor_label = arguments[arguments.index("--label") + 1]
                owner = actor_name.removeprefix("fireguard-reviewed-storage-")
                identifier = result.stdout.decode("utf-8").strip()
                if re.fullmatch(r"[a-f0-9]{32}", owner) and actor_label == "fireguard.reviewed-storage-owner=" + owner:
                    self.assertRegex(identifier, r"\A[a-f0-9]{64}\Z")
                    owned_actor_ids.add(identifier)
            return result.stdout.decode("utf-8").strip()
        archive = io.BytesIO()
        # Public CI runtime only: no application tree, image network pull or configuration files.
        runtime_files = {}
        for source, destination in ((shutil.which("php"), "/usr/local/bin/php"), (shutil.which("env"), "/usr/bin/env")):
            self.assertIsNotNone(source)
            source = str(Path(source).resolve(strict=True))
            runtime_files[destination] = source
            dependencies = subprocess.run(["ldd", source], capture_output=True, timeout=30, check=False)
            self.assertEqual(0, dependencies.returncode, "CI PHP/env dynamic runtime dependencies must be available")
            for line in dependencies.stdout.decode().splitlines():
                fields = line.split()
                library = fields[2] if len(fields) >= 3 and fields[1] == "=>" else fields[0] if fields else ""
                if library.startswith("/"):
                    runtime_files[library] = str(Path(library).resolve(strict=True))
        with tarfile.open(fileobj=archive, mode="w") as runtime:
            for destination, source in sorted(runtime_files.items()):
                data = Path(source).read_bytes()
                entry = tarfile.TarInfo(destination.lstrip("/"))
                entry.size, entry.mode = len(data), 0o555
                runtime.addfile(entry, io.BytesIO(data))
            native_timezone_archive(runtime)
        image_id = docker(["import", "--change", "LABEL fireguard.recovery-proof=" + nonce,
                           "--change", "LABEL org.opencontainers.image.source=https://github.com/" + RECOVERY.REPOSITORY,
                           "--change", "LABEL org.opencontainers.image.revision=" + SOURCE, "-"], data=archive.getvalue())
        self.assertRegex(image_id, r"\Asha256:[a-f0-9]{64}\Z")
        self.addCleanup(docker, ["image", "rm", image_id])
        self.assertEqual(name, docker(["volume", "create", name]))
        self.addCleanup(docker, ["volume", "rm", name])
        identity = json.loads(docker(["image", "inspect", "--format", RECOVERY.IMAGE_FORMAT, image_id]))
        self.assertEqual("https://github.com/" + RECOVERY.REPOSITORY, identity["source"])
        self.assertEqual(SOURCE, identity["revision"])
        self.assertIn(identity["digests"], (None, []))
        self.assertEqual(image_id, identity["id"])
        self.assertIs(identity["volumesEmpty"], True)
        bind_directory = tempfile.TemporaryDirectory(prefix="fireguard-recovery-proof-")
        self.addCleanup(bind_directory.cleanup)
        bind_source = str(Path(bind_directory.name).resolve())
        owned_ids = []
        keeper = docker(["create", "--name", name + "-keeper", "--network", "none", "--entrypoint", "/never-executed",
                         "--mount", "type=volume,source=" + name + ",target=/fixture,readonly,volume-nocopy", image_id])
        self.assertRegex(keeper, r"\A[a-f0-9]{64}\Z")
        self.addCleanup(docker, ["rm", keeper])
        owned_ids.append(keeper)
        for kind, declaration in (("hostbind", ["--volume", bind_source + ":/fixture:ro"]),
                                  ("binds", ["--volume", name + ":/fixture:ro"]),
                                  ("mounts", ["--mount", "type=volume,source=" + name + ",target=/fixture,readonly"])):
            container_id = docker(["create", "--name", name + "-" + kind, "--network", "none", "--security-opt", "no-new-privileges",
                                   "--cap-drop", "ALL", "--entrypoint", "/never-executed", *declaration, image_id])
            self.assertRegex(container_id, r"\A[a-f0-9]{64}\Z")
            self.addCleanup(docker, ["rm", container_id])
            owned_ids.append(container_id)
            def legacy_runner(argv):
                if argv == ["docker", "ps", "--all", "--quiet", "--no-trunc"]:
                    return "\n".join(owned_ids)
                self.assertEqual("docker", argv[0])
                self.assertIn(argv[-1], owned_ids)
                return docker(argv[1:])
            legacy_records = RECOVERY.Host(legacy_runner).containers()
            self.assertEqual(set(owned_ids), {item["id"] for item in legacy_records})
            legacy = next(item for item in legacy_records if item["id"] == container_id)
            self.assertFalse(legacy["running"])
            self.assertFalse(legacy["restarting"])
            self.assertEqual(1, len(legacy["mounts"]))
            self.assertEqual("" if kind == "hostbind" else name, legacy["mounts"][0]["name"])
            if kind == "hostbind":
                self.assertEqual(bind_source, legacy["mounts"][0]["source"])
            host = RECOVERY.Host(lambda argv: docker(argv[5:]))
            # This only-owned offline image supplies PHP for the native protocol smoke.
            # Production can set this field only after verified OCI provenance AND manifest.
            host._verified_storage_image = image_id
            host.prepare_storage_inspector([{"id": keeper, "project": RECOVERY.PROJECT, "mounts": [
                {"name": name, "source": RECOVERY.storage_volume_path(name)}]}])
            host.isolation_api()
            records = host.isolation_containers([container_id])
            self.assertEqual(image_id, records[0]["Image"])
            self.assertEqual({"Type": "layers"}, records[0]["RootFS"])
            self.assertFalse(records[0]["State"]["Running"])
            if kind == "hostbind":
                self.assertFalse(records[0]["HostConfig"]["MountDeclarations"][0]["OptionsDefault"])
                self.assertEqual([], host.isolation_volumes(records, required_names=set()))
                self.assertEqual([name], host._storage_proof["volumeNames"])
                continue
            self.assertEqual([{"Type": "volume", "Name": name, "Destination": "/fixture", "RW": False, "OptionsDefault": True}],
                             records[0]["HostConfig"]["MountDeclarations"])
            volume = host.isolation_json(["volume", "inspect", "--format", RECOVERY.ISOLATION_VOLUME_FORMAT, name])
            self.assertIs(volume["OptionsEmpty"], True)
            self.assertEqual("local", volume["Driver"])
            self.assertEqual([volume], host.isolation_volumes(records, required_names={name}))
            first = copy.deepcopy(host._storage_proof)
            self.assertEqual([volume], host.isolation_volumes(records, required_names={name}))
            self.assertEqual(first, host._storage_proof)


if __name__ == "__main__":
    unittest.main()
