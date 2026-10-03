"""Hermetic tests: no SSH, network, secret files, databases or Docker daemon."""

import copy
from contextlib import contextmanager, redirect_stderr, redirect_stdout
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import runpy
import subprocess
import sys
from types import SimpleNamespace
import unittest
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
    mounts = [{"name": RECOVERY.PREFIX + "_" + service + "_data", "source": "/var/lib/docker/volumes/fixture"}] if database else []
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
        return self.manifest

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
                                                 st_gid=1001 if depth == 0 else 0, st_dev=2049, st_ino=100 + depth)
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


class AdapterTests(unittest.TestCase):
    def test_real_lock_validator_requires_owned_empty_directory_canonical_path_and_private_writes(self):
        self.assertEqual({"namespaceRefusalDepth", "namespaceUid", "namespaceMode", "namespaceDevice", "namespaceInode"},
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


if __name__ == "__main__":
    unittest.main()
