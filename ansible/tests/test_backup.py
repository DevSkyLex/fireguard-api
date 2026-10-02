"""Tests fail-closed backup control flow using no external repository or secret."""
import importlib.util
import json
from pathlib import Path
import tempfile
import io
import tarfile
import unittest
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("backup", ROOT / "bin/fireguard-backup.py")
BACKUP = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(BACKUP)


class SnapshotTest(unittest.TestCase):
    def exercise(self, fail_upload=False, fail_resume=False):
        calls = []
        manifests = []
        stopped = False
        with tempfile.TemporaryDirectory() as directory:
            app = Path(directory)
            value = {"repository": "sftp:synthetic.invalid:/repository", "password_file": "/private/synthetic",
                     "storage_mode": "local", "secret_recovery_reference": "operator-secret-store"}

            def run(argv, **kwargs):
                nonlocal stopped
                calls.append(argv)
                if argv[0] == "restic" and argv[1] == "snapshots":
                    return b"[]"
                if any('case "$STORAGE_DSN"' in token for token in argv):
                    return b"local"
                if "ps" in argv and "--services" in argv:
                    return b"" if stopped else b"app\nasync_worker\nwebhook_worker\nscheduler_worker\n"
                if "stop" in argv:
                    stopped = True
                if "start" in argv:
                    if fail_resume:
                        raise RuntimeError("synthetic restart error")
                    stopped = False
                if "ps" in argv and "--quiet" in argv:
                    return b"source-id"
                if "inspect" in argv:
                    return b"example.invalid/source@sha256:synthetic"
                if "backup_files" in argv:
                    with tarfile.open(fileobj=kwargs["stdout"], mode="w") as archive:
                        data = b"synthetic application attachment"
                        member = tarfile.TarInfo("storage/attachment.txt")
                        member.size = len(data)
                        archive.addfile(member, io.BytesIO(data))
                    return b""
                if kwargs.get("stdout") is not None and hasattr(kwargs["stdout"], "write"):
                    kwargs["stdout"].write(b"synthetic private bytes")
                if argv[:2] == ["restic", "backup"]:
                    stage = Path(argv[-1])
                    manifests.append(json.loads((stage / "manifest.json").read_text()))
                    self.assertEqual({"auth.dump", "main.dump", "files.tar", "manifest.json"}, {p.name for p in stage.iterdir()})
                    if fail_upload:
                        raise RuntimeError("synthetic upload error")
                    return b'{"message_type":"summary","snapshot_id":"synthetic-snapshot"}\n'
                return b""

            with patch.object(BACKUP, "run", side_effect=run), patch.object(BACKUP, "counts", return_value=["table|2"]):
                if fail_resume:
                    with self.assertRaises(BACKUP.RecoveryStateError):
                        BACKUP.snapshot(app, value)
                elif fail_upload:
                    with self.assertRaises(RuntimeError):
                        BACKUP.snapshot(app, value)
                else:
                    BACKUP.snapshot(app, value)
            status = (app / "backup-status.prom").read_text()
            self.assertIn("fireguard_backup_last_attempt_success " + ("0" if fail_upload or fail_resume else "1"), status)
            if fail_upload or fail_resume:
                self.assertNotIn("fireguard_backup_last_success_timestamp_seconds", status)
            self.assertFalse(any(app.glob("fireguard-snapshot-*")))
        return calls, manifests

    def test_writers_stop_before_both_dumps_and_matching_files_then_exact_state_resumes(self):
        calls, manifests = self.exercise()
        stop = next(i for i, argv in enumerate(calls) if "stop" in argv)
        dumps = [i for i, argv in enumerate(calls) if any("pg_dump" in token for token in argv)]
        self.assertEqual(2, len(dumps))
        self.assertTrue(all(stop < i for i in dumps))
        self.assertIn("scheduler_worker", calls[stop])
        self.assertIn("webhook_worker", calls[stop])
        resume = next(argv for argv in calls if "start" in argv)
        self.assertNotIn("assistant_worker", resume, "Initially stopped services must stay stopped")
        self.assertEqual(["table|2"], manifests[0]["databases"]["main"]["table_counts"])

    def test_failed_upload_resumes_previous_services_without_claiming_success(self):
        calls, _ = self.exercise(fail_upload=True)
        self.assertTrue(any("start" in argv for argv in calls))
        self.assertFalse(any(argv[:2] == ["restic", "forget"] for argv in calls))

    def test_repository_failure_precedes_all_writer_changes(self):
        with tempfile.TemporaryDirectory() as directory, patch.object(BACKUP, "run", side_effect=RuntimeError("repository unavailable")) as call:
            with self.assertRaises(RuntimeError):
                BACKUP.snapshot(Path(directory), {"repository": "sftp:synthetic", "password_file": "/synthetic"})
            self.assertEqual(["restic", "snapshots", "--json"], call.call_args.args[0])
            self.assertIn("fireguard_backup_last_attempt_success 0", (Path(directory) / "backup-status.prom").read_text())

    def test_failed_writer_restart_stops_every_writer_and_requires_review(self):
        calls, _ = self.exercise(fail_resume=True)
        stop = [argv for argv in calls if "stop" in argv]
        self.assertEqual(2, len(stop))
        self.assertTrue(all(name in stop[-1] for name in BACKUP.WRITERS))

    def test_uncertain_writer_recovery_retains_the_installation_mutex(self):
        with tempfile.TemporaryDirectory() as directory:
            app = Path(directory)
            args = ["fireguard-backup.py", "snapshot", "--config", str(app / "synthetic.json"), "--app-dir", str(app)]
            with patch("sys.argv", args), patch.object(BACKUP, "config", return_value={}), patch.object(BACKUP, "snapshot", side_effect=BACKUP.RecoveryStateError("synthetic")):
                with self.assertRaises(BACKUP.RecoveryStateError):
                    BACKUP.main()
            self.assertTrue((app / ".fireguard-operation.lock").is_dir())

    def test_empty_operator_example_fails_before_provider_access(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "backup.json"
            path.write_text((ROOT / "ansible/backup.example.json").read_text())
            path.chmod(0o600)
            with self.assertRaises(RuntimeError), patch.object(BACKUP, "run") as call:
                BACKUP.config(path)
            call.assert_not_called()


if __name__ == "__main__":
    unittest.main()
