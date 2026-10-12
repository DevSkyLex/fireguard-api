"""The recovery helper may remove only the proven stale installation mutex."""
import argparse
from contextlib import redirect_stdout
from datetime import datetime, timezone
import importlib.util
import io
import json
import os
from pathlib import Path
import tempfile
import time
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("cutover_recovery", ROOT / "ansible/recover-cutover-lock.py")
RECOVERY = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(RECOVERY)


class CutoverRecoveryTest(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix="fireguard-recovery-")
        self.addCleanup(self.directory.cleanup)
        self.app = Path(self.directory.name).resolve() / "app"
        self.app.mkdir()
        self.lock = self.app / RECOVERY.LOCK
        self.lock.mkdir()
        timestamp = int(time.time())
        self.options = argparse.Namespace(app_dir=self.app, project="fireguard_dev",
                                          failed_source_sha="a" * 40, failed_run_id="38288952348",
                                          failed_start=str(timestamp - 15), failed_end=str(timestamp + 1),
                                          repository="https://github.com/DevSkyLex/fireguard-api")
        finished = datetime.fromtimestamp(timestamp - 2, timezone.utc).isoformat().replace("+00:00", "Z")
        self.containers = {}
        for index, service in enumerate(["auth_database", "app", "async_worker", "webhook_worker",
                                         "assistant_worker", "scheduler_worker"], 1):
            running = service in ("auth_database", "webhook_worker")
            self.containers[f"{index:064x}"] = {
                "project": self.options.project, "working_dir": str(self.app), "service": service,
                "image": "sha256:" + "b" * 64, "running": running, "exit_code": 0,
                "oom_killed": False, "status": "running" if running else "exited", "finished_at": finished,
            }
        self.image = {"revision": self.options.failed_source_sha, "source": self.options.repository}
        self.calls = []
        self.connection = "main"
        self.queue = "0"
        self.table = "present"
        self.probes = RECOVERY.load_cutover()

    def fake_run(self, argv, **_kwargs):
        self.calls.append(argv)
        self.assertEqual("docker", argv[0])
        if argv[1] == "ps":
            self.assertIn("label=com.docker.compose.project=" + self.options.project, argv)
            self.assertIn("label=com.docker.compose.project.working_dir=" + str(self.options.app_dir), argv)
            return "\n".join(self.containers)
        if argv[1:3] == ["image", "inspect"]:
            return json.dumps(self.image)
        if argv[1] == "inspect":
            return json.dumps(self.containers[argv[-1]])
        if argv[1] == "exec":
            if argv[-1] == self.probes["TRANSPORT_PROBE"]:
                return self.connection
            if argv[-1] == self.probes["TABLE_PROBE"]:
                return self.table
            if argv[-1] == self.probes["COUNT_PROBE"]:
                return self.queue
        self.fail("Unexpected command class")

    def execute(self):
        with patch.object(RECOVERY, "run", self.fake_run), \
                patch.object(RECOVERY, "assert_no_operations"), \
                patch.object(RECOVERY.time, "time", return_value=int(self.options.failed_end) + 120):
            return RECOVERY.recover(self.options)

    def refuse(self, reason=None):
        with self.assertRaises(RECOVERY.RecoveryError) as result:
            self.execute()
        if reason:
            self.assertEqual(reason, str(result.exception))
        self.assertTrue(self.lock.is_dir(), "Every denied recovery must retain the mutex")

    def container(self, service):
        return next(value for value in self.containers.values() if value["service"] == service)

    def test_verified_failed_cutover_unlocks_only_the_empty_directory(self):
        marker = self.app / "public-marker"
        marker.write_text("preserved", encoding="utf-8")
        proof = self.execute()
        self.assertEqual("unlocked", proof["outcome"])
        self.assertEqual(0, proof["auth_queue_count"])
        self.assertFalse(self.lock.exists())
        self.assertEqual("preserved", marker.read_text(encoding="utf-8"))
        self.assertEqual(2, sum(argv[1] == "ps" for argv in self.calls))
        self.assertEqual(2, sum(argv[-1] == self.probes["COUNT_PROBE"] for argv in self.calls))
        self.assertTrue(all(argv[1] in {"ps", "inspect", "image", "exec"} for argv in self.calls))
        self.assertFalse(any(".Env" in argument for argv in self.calls for argument in argv))

    def test_github_repository_casing_is_normalized_without_accepting_another_path(self):
        self.image["source"] = self.options.repository.lower()
        self.assertEqual("unlocked", self.execute()["outcome"])

    def test_wrong_installed_source_or_repository_retains_lock(self):
        for key, value in [("revision", "c" * 40), ("source", self.options.repository + "-other")]:
            with self.subTest(key=key):
                original = self.image[key]
                self.image[key] = value
                self.refuse("installed-source-mismatch")
                self.image[key] = original

    def test_container_scope_must_match_both_project_and_real_working_directory(self):
        for key, value in [("project", "other"), ("working_dir", str(self.app.parent))]:
            with self.subTest(key=key):
                original = self.container("app")[key]
                self.container("app")[key] = value
                self.refuse("container-scope-mismatch")
                self.container("app")[key] = original

    def test_nonempty_lock_is_never_removed(self):
        marker = self.lock / "owned"
        marker.write_text("do not remove")
        self.refuse("lock-not-empty")
        self.assertTrue(marker.exists())
        self.assertEqual([], self.calls)

    def test_lock_timestamps_outside_failed_window_are_refused(self):
        for timestamp in (int(self.options.failed_start) - 61, int(self.options.failed_end) + 61):
            with self.subTest(timestamp=timestamp):
                os.utime(self.lock, (timestamp, timestamp))
                self.refuse("lock-outside-failed-window")
        self.assertEqual([], self.calls)

    def test_symlinked_app_or_lock_is_refused(self):
        for target in ("app", "lock"):
            with self.subTest(target=target):
                link = self.app.parent / ("linked-" + target)
                try:
                    link.symlink_to(self.app if target == "app" else self.lock, target_is_directory=True)
                except OSError:
                    self.skipTest("Directory symlinks are unavailable on this host")
                if target == "app":
                    self.options.app_dir = link
                    self.refuse("invalid-app-path")
                    self.options.app_dir = self.app
                    link.unlink()
                else:
                    self.lock.rmdir()
                    link.unlink()
                    self.lock.symlink_to(self.app, target_is_directory=True)
                    with self.assertRaisesRegex(RECOVERY.RecoveryError, "invalid-lock-path"):
                        self.execute()
                    self.assertTrue(self.lock.is_symlink())
                    self.lock.unlink()
                    self.lock.mkdir()

    def test_invalid_public_metadata_fails_before_docker(self):
        cases = {"failed_source_sha": ["a" * 39, "PRIVATE_SECRET"], "failed_run_id": ["0", "1;bad"],
                 "project": ["../other", ""], "repository": ["https://other.invalid/owner/repo", "https://github.com/a/b?token=PRIVATE_SECRET"],
                 "failed_start": ["-1", "1.2"], "failed_end": ["0", str(int(self.options.failed_start) - 1)]}
        for key, values in cases.items():
            original = getattr(self.options, key)
            for value in values:
                with self.subTest(key=key, value=value):
                    setattr(self.options, key, value)
                    self.refuse()
                    self.assertEqual([], self.calls)
            setattr(self.options, key, original)

    def test_auth_queue_must_be_available_and_exactly_zero(self):
        for value in ("1", "PRIVATE_SECRET", "", "00"):
            with self.subTest(value=value):
                self.queue = value
                self.refuse("auth-queue-not-empty")
        self.queue = "0"
        self.table = "absent"
        self.refuse("auth-queue-unavailable")

    def test_transport_must_be_confirmed_main_by_the_shared_probe(self):
        for value in ("unsupported", "auth", "PRIVATE_SECRET"):
            with self.subTest(value=value):
                self.connection = value
                self.refuse("installed-transport-not-main")

    def test_webhook_and_auth_database_must_be_unique_and_running(self):
        for service in ("webhook_worker", "auth_database"):
            with self.subTest(service=service):
                value = self.container(service)
                value["running"] = False
                value["status"] = "exited"
                self.refuse("required-service-not-running")
                value["running"] = True
                value["status"] = "running"
                self.containers["f" * 64] = dict(value)
                self.refuse("ambiguous-installed-service")
                del self.containers["f" * 64]

    def test_missing_or_running_producer_and_an_older_stop_are_ambiguous(self):
        value = self.container("app")
        value["running"] = True
        self.refuse("producer-state-ambiguous")
        value["running"] = False
        value["finished_at"] = "2020-01-01T00:00:00Z"
        self.refuse("producer-state-ambiguous")
        identifier = next(identifier for identifier, item in self.containers.items() if item is value)
        del self.containers[identifier]
        self.refuse("installed-services-incomplete")

    def test_force_kill_or_oom_is_refused_for_every_writer(self):
        for service in RECOVERY.WRITERS:
            for key, bad, good in [("exit_code", 137, 0), ("oom_killed", True, False)]:
                with self.subTest(service=service, key=key):
                    value = self.container(service)
                    value[key] = bad
                    self.refuse("writer-not-graceful")
                    value[key] = good

    def test_active_operation_cannot_unlock(self):
        with patch.object(RECOVERY, "assert_no_operations", side_effect=RECOVERY.RecoveryError("active-operation")), \
                patch.object(RECOVERY, "run") as command:
            with self.assertRaisesRegex(RECOVERY.RecoveryError, "active-operation"):
                RECOVERY.recover(self.options)
        command.assert_not_called()
        self.assertTrue(self.lock.is_dir())

    def test_stat_change_or_a_late_queue_write_retains_lock(self):
        original = RECOVERY.lock_state(self.app, int(self.options.failed_start), int(self.options.failed_end))
        with patch.object(RECOVERY, "lock_state", side_effect=[original, (original[0], ("changed",))]):
            self.refuse("lock-state-changed")
        counts = iter(["0", "1"])
        original_run = self.fake_run

        def late_queue(argv, **kwargs):
            return next(counts) if argv[-1] == self.probes["COUNT_PROBE"] else original_run(argv, **kwargs)

        with patch.object(self, "fake_run", late_queue):
            self.refuse("auth-queue-not-empty")

    def test_replaced_lock_is_rechecked_at_the_removal_boundary(self):
        original = RECOVERY.lock_state(self.app, int(self.options.failed_start), int(self.options.failed_end))
        self.lock.rmdir()
        self.lock.mkdir()
        with self.assertRaisesRegex(RECOVERY.RecoveryError, "lock-state-changed"):
            RECOVERY.remove_verified_lock(self.app, original)
        self.assertTrue(self.lock.is_dir())

    def test_runtime_change_between_the_two_evidence_snapshots_retains_lock(self):
        actual = RECOVERY.installed
        calls = 0

        def changed(options, start, end):
            nonlocal calls
            calls += 1
            value = actual(options, start, end)
            if calls == 2:
                value["webhook_worker"][1]["finished_at"] = "changed"
            return value

        with patch.object(RECOVERY, "installed", changed):
            self.refuse("installed-state-changed")

    def test_command_failure_and_unknown_errors_do_not_print_private_diagnostics(self):
        output = io.StringIO()
        with patch.object(RECOVERY, "recover", side_effect=RuntimeError("PRIVATE_SECRET")), redirect_stdout(output):
            self.assertEqual(1, RECOVERY.main(self.argv()))
        self.assertEqual({"outcome": "refused", "reason": "verification-failed"}, json.loads(output.getvalue()))
        self.assertTrue(self.lock.is_dir())
        with patch.object(RECOVERY.subprocess, "run", return_value=argparse.Namespace(returncode=1, stdout="PRIVATE_SECRET", stderr="PRIVATE_SECRET")):
            with self.assertRaisesRegex(RECOVERY.RecoveryError, "command-failed"):
                RECOVERY.run(["docker", "ps"])

    def argv(self):
        return [argument for key, value in vars(self.options).items()
                for argument in ("--" + key.replace("_", "-"), str(value))]

    def test_cli_refuses_invalid_arguments_without_echoing_them(self):
        output = io.StringIO()
        with redirect_stdout(output):
            self.assertEqual(1, RECOVERY.main(["--PRIVATE_SECRET"]))
        self.assertNotIn("PRIVATE_SECRET", output.getvalue())


class ProcessClassificationTest(unittest.TestCase):
    def process(self, root, pid, parent, argv):
        directory = root / str(pid)
        directory.mkdir()
        (directory / "stat").write_text(f"{pid} (synthetic name) S {parent} 0 0")
        (directory / "cmdline").write_bytes(b"\0".join(token.encode() for token in argv) + (b"\0" if argv else b""))

    def test_excludes_own_ancestors_but_detects_a_concurrent_operation(self):
        with tempfile.TemporaryDirectory() as directory:
            proc = Path(directory)
            self.process(proc, 1, 0, ["/usr/bin/ansible-playbook"])
            self.process(proc, 2, 1, ["python3", "/app/recover-cutover-lock.py"])
            self.process(proc, 3, 1, ["postgres"])
            self.process(proc, 4, 1, ["php", "bin/console", "messenger:consume", "webhook"])
            RECOVERY.assert_no_operations(proc, pid=2)
            self.process(proc, 5, 1, ["python3", "/app/fireguard-backup.py", "PRIVATE_SECRET"])
            with self.assertRaisesRegex(RECOVERY.RecoveryError, "active-operation") as result:
                RECOVERY.assert_no_operations(proc, pid=2)
            self.assertNotIn("PRIVATE_SECRET", str(result.exception))

    def test_operation_names_are_exact_and_cover_host_and_controller_launchers(self):
        for name in ["docker", "docker-compose", "psql", "restic", "pg_dump", "pg_restore", "ansible-playbook",
                     "fireguard-maintenance.sh", "fireguard-backup.py", "webhook-cutover.py",
                     "fireguard-webhook-cutover.py", "AnsiballZ_command.py", "recover-cutover-lock.py"]:
            with self.subTest(name=name):
                self.assertTrue(RECOVERY.operation_tokens([b"python3", ("/app/" + name).encode()]))
        for name in ["dockerd", "containerd", "containerd-shim", "postgres", "php", "python3", "backup-not-an-operation"]:
            self.assertFalse(RECOVERY.operation_tokens([name.encode()]))

    def test_unknown_process_state_fails_closed(self):
        with tempfile.TemporaryDirectory() as directory:
            proc = Path(directory)
            self.process(proc, 2, 0, ["python3"])
            self.process(proc, 3, 0, ["unrelated"])
            (proc / "3/cmdline").write_bytes(b"unterminated")
            with self.assertRaisesRegex(RECOVERY.RecoveryError, "process-state-unknown"):
                RECOVERY.assert_no_operations(proc, pid=2)


if __name__ == "__main__":
    unittest.main()
