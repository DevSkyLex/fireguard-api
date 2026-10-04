"""Hermetic metadata repair tests: no real chown, services, secrets, SSH or VPS."""

import ast
from contextlib import ExitStack
import copy
import importlib.util
import io
import json
import os
from pathlib import Path
import stat
from types import SimpleNamespace
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / "bin/fireguard-deployment-namespace-repair.py"
SPEC = importlib.util.spec_from_file_location("deployment_namespace_repair", HELPER)
REPAIR = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(REPAIR)
RECOVERY_SPEC = importlib.util.spec_from_file_location("deployment_namespace_repair_public",
                                                     ROOT / "bin/fireguard-deployment-recovery.py")
RECOVERY = importlib.util.module_from_spec(RECOVERY_SPEC)
RECOVERY_SPEC.loader.exec_module(RECOVERY)


def reviewed():
    return ({path: copy.deepcopy(RECOVERY.REVIEWED_NAMESPACE[len(REPAIR.PATHS) - 1 - index])
             for index, path in enumerate(REPAIR.PATHS)}, copy.deepcopy(RECOVERY.REVIEWED_LEGACY_LOCK))


class Entries:
    def __init__(self, values):
        self.values = iter(values)

    def __enter__(self):
        return self.values

    def __exit__(self, *args):
        return False


class FakeNamespace:
    """Separate pinned descriptor objects from mutable path attachments."""

    def __init__(self):
        namespace, lock = reviewed()
        self.paths = {**namespace, REPAIR.LOCK: lock}
        self.descriptors = {}
        self.descriptor_paths = {}
        self.opens = []
        self.closed = []
        self.writes = []
        self.contents = []
        self.uid = 0
        self.after_write = None
        self.fail_chown = None
        self.symlink = None
        self.noncanonical = None
        self.relative_mismatch = None

    def snapshot(self, node):
        kind = stat.S_IFLNK if node.get("isSymlink") else stat.S_IFDIR
        return SimpleNamespace(st_dev=node["device"], st_ino=node["inode"], st_uid=node["uid"],
                               st_gid=node["gid"], st_mode=kind | node["mode"],
                               st_mtime_ns=node["mtimeNs"], st_ctime_ns=node["ctimeNs"])

    def open(self, name, flags, *, dir_fd=None):
        path = name if dir_fd is None else self.descriptor_paths[dir_fd] + ("" if self.descriptor_paths[dir_fd] == "/" else "/") + name
        self.opens.append((path, flags, dir_fd))
        if path == self.symlink:
            raise OSError("private child error must never be printed")
        descriptor = 10 + len(self.descriptors)
        self.descriptors[descriptor] = self.paths[path]
        self.descriptor_paths[descriptor] = path
        return descriptor

    def lstat(self, path):
        return self.snapshot(self.paths[path])

    def relative_stat(self, name, *, dir_fd=None, follow_symlinks=True):
        path = name if dir_fd is None else self.descriptor_paths[dir_fd] + ("" if self.descriptor_paths[dir_fd] == "/" else "/") + name
        node = copy.deepcopy(self.paths[path])
        if path == self.relative_mismatch:
            node["inode"] += 1
        return self.snapshot(node)

    def fstat(self, descriptor):
        return self.snapshot(self.descriptors[descriptor])

    def realpath(self, path, *, strict=False):
        return "/foreign" if path == self.noncanonical else path

    def change(self, descriptor, operation):
        path = self.descriptor_paths[descriptor]
        self.writes.append((path, operation))
        node = self.descriptors[descriptor]
        if operation == "chmod":
            node["mode"] = 0o755
        elif self.fail_chown == path:
            raise OSError("private child error must never be printed")
        else:
            node["uid"] = node["gid"] = 0
        node["ctimeNs"] += 1
        # Every intermediate state is checked, not just the final protected state.
        if node["gid"] == 0 and node["mode"] & 0o020:
            raise AssertionError("repair introduced writable group 0")
        if self.after_write is not None:
            self.after_write(path, operation)

    def run(self, *, apply=False):
        self.descriptors = {}
        self.descriptor_paths = {}
        self.opens = []
        self.closed = []
        with ExitStack() as stack:
            for name, value in {"O_DIRECTORY": 0x10000, "O_NOFOLLOW": 0x20000, "O_CLOEXEC": 0x80000}.items():
                stack.enter_context(patch.object(REPAIR.os, name, value, create=True))
            replacements = {"open": self.open, "close": self.closed.append,
                            "lstat": self.lstat, "stat": self.relative_stat, "fstat": self.fstat,
                            "scandir": lambda descriptor: Entries(self.contents),
                            "getuid": lambda: self.uid, "geteuid": lambda: self.uid,
                            "fchmod": lambda descriptor, mode: self.change(descriptor, "chmod"),
                            "fchown": lambda descriptor, uid, gid: self.change(descriptor, "chown")}
            for name, value in replacements.items():
                stack.enter_context(patch.object(REPAIR.os, name, value, create=True))
            stack.enter_context(patch.object(REPAIR.os.path, "realpath", self.realpath))
            stack.enter_context(patch.object(REPAIR, "reviewed_constants", reviewed))
            return REPAIR.repair(apply=apply)


class DeploymentNamespaceRepairTest(unittest.TestCase):
    def setUp(self):
        self.host = FakeNamespace()

    def deny(self, diagnostic=None):
        result = self.host.run(apply=True)
        self.assertEqual("blocked", result["result"])
        self.assertEqual([], self.host.writes)
        self.assertEqual([], result["steps"])
        if diagnostic is not None:
            self.assertEqual(diagnostic, result["diagnostic"])
        self.assertEqual(len(self.host.descriptors), len(self.host.closed))
        return result

    def test_default_dry_run_reports_verified_proposal_without_writes(self):
        self.host.uid = 1000
        result = self.host.run()
        self.assertEqual("dry-run", result["result"])
        self.assertEqual([], self.host.writes)
        self.assertEqual([], result["steps"])
        self.assertEqual(list(REPAIR.TARGETS), [target["path"] for target in result["targets"]])
        for target in result["targets"]:
            self.assertEqual(target["beforeLstat"], target["afterLstat"])
            self.assertEqual("historical", target["state"])
            self.assertEqual({"uid": 0, "gid": 0, "mode": 0o755}, target["desired"])
        self.assertEqual(7, len(self.host.closed))
        for _, flags, _ in self.host.opens:
            self.assertEqual(0xB0000, flags & 0xB0000)

    def test_apply_changes_only_three_pinned_directories_top_down_chmod_before_chown(self):
        original = copy.deepcopy(self.host.paths)
        result = self.host.run(apply=True)
        self.assertEqual("complete", result["result"])
        self.assertEqual([(REPAIR.TARGETS[0], "chown"), (REPAIR.TARGETS[1], "chown"),
                          (REPAIR.TARGETS[2], "chmod"), (REPAIR.TARGETS[2], "chown")], self.host.writes)
        self.assertEqual(4, len(result["steps"]))
        for path in (*REPAIR.PATHS, REPAIR.LOCK):
            if path in REPAIR.TARGETS:
                target = next(target for target in result["targets"] if target["path"] == path)
                self.assertEqual((0, 0, 0o755), tuple(target["afterLstat"][key] for key in ("uid", "gid", "mode")))
                self.assertEqual(original[path]["mtimeNs"], target["afterLstat"]["mtimeNs"])
            else:
                self.assertEqual(original[path], self.host.paths[path])

    def test_apply_requires_root_before_any_namespace_access(self):
        self.host.uid = 1000
        self.deny("apply-requires-root")
        self.assertEqual([], self.host.opens)

    def test_changed_inodes_devices_or_mtime_at_every_depth_refuse_before_any_write(self):
        for path in (*REPAIR.PATHS, REPAIR.LOCK):
            for key in ("device", "inode", "mtimeNs"):
                with self.subTest(path=path, key=key):
                    self.host = FakeNamespace()
                    self.host.paths[path][key] += 1
                    self.deny()

    def test_unreviewed_owners_modes_or_historical_ctime_refuse_before_any_write(self):
        for path in (*REPAIR.PATHS, REPAIR.LOCK):
            for key, value in (("uid", 42), ("gid", 42), ("mode", 0o777), ("ctimeNs", 42)):
                with self.subTest(path=path, key=key):
                    self.host = FakeNamespace()
                    self.host.paths[path][key] = value
                    self.deny()

    def test_symlinks_noncanonical_paths_and_attachment_mismatch_are_denied(self):
        for attribute in ("symlink", "noncanonical", "relative_mismatch"):
            with self.subTest(attribute=attribute):
                self.host = FakeNamespace()
                setattr(self.host, attribute, REPAIR.TARGETS[1])
                self.deny()
        self.host = FakeNamespace()
        self.host.paths[REPAIR.TARGETS[1]]["isSymlink"] = True
        self.deny("namespace-not-directory")

    def test_nonempty_original_lock_is_denied_without_exposing_child_names(self):
        self.host.contents = ["secret-child-name"]
        result = self.deny("original-lock-not-empty")
        self.assertNotIn("secret-child-name", json.dumps(result))

    def test_already_protected_same_inodes_are_idempotent_without_syscalls(self):
        for path in REPAIR.TARGETS:
            self.host.paths[path].update(uid=0, gid=0, mode=0o755, ctimeNs=42)
        result = self.host.run(apply=True)
        self.assertEqual("complete", result["result"])
        self.assertEqual([], self.host.writes)
        self.assertEqual([], result["steps"])
        self.assertTrue(all(target["state"] == "protected" for target in result["targets"]))

    def test_chmod_first_partial_state_resumes_only_pending_chown(self):
        for path in REPAIR.TARGETS[:2]:
            self.host.paths[path].update(uid=0, gid=0, mode=0o755, ctimeNs=42)
        self.host.paths[REPAIR.TARGETS[-1]].update(mode=0o755, ctimeNs=42)
        result = self.host.run(apply=True)
        self.assertEqual("complete", result["result"])
        self.assertEqual([(REPAIR.TARGETS[-1], "chown")], self.host.writes)
        self.assertEqual("chmod-completed-ownership-pending", result["targets"][-1]["state"])

    def test_group_zero_with_write_or_mixed_partial_owner_is_never_admitted(self):
        for uid, gid, mode in ((0, 0, 0o775), (0, 1000, 0o755), (1000, 0, 0o755), (42, 42, 0o755)):
            with self.subTest(uid=uid, gid=gid, mode=mode):
                self.host = FakeNamespace()
                self.host.paths[REPAIR.TARGETS[-1]].update(uid=uid, gid=gid, mode=mode)
                self.deny("namespace-state-not-reviewed")

    def test_failed_chown_keeps_chmod_partial_state_and_can_be_explicitly_resumed(self):
        self.host.fail_chown = REPAIR.TARGETS[-1]
        result = self.host.run(apply=True)
        self.assertEqual("partial", result["result"])
        self.assertEqual(3, len(result["steps"]))
        partial = result["targets"][-1]["afterLstat"]
        self.assertEqual((1000, 1000, 0o755), tuple(partial[key] for key in ("uid", "gid", "mode")))
        self.assertNotIn("private child error", json.dumps(result))
        self.host.fail_chown = None
        self.host.writes = []
        self.assertEqual("complete", self.host.run(apply=True)["result"])
        self.assertEqual([(REPAIR.TARGETS[-1], "chown")], self.host.writes)

    def test_detached_directory_after_syscall_blocks_further_mutation_without_rollback(self):
        def detach(path, operation):
            changed = copy.deepcopy(self.host.paths[path])
            changed["inode"] += 1
            self.host.paths[path] = changed
        self.host.after_write = detach
        result = self.host.run(apply=True)
        self.assertEqual("partial", result["result"])
        self.assertEqual("namespace-attachment-changed", result["diagnostic"])
        self.assertEqual([(REPAIR.TARGETS[0], "chown")], self.host.writes)
        self.assertIsNone(result["targets"][0]["afterLstat"])

    def test_original_lock_change_after_syscall_stops_before_next_target(self):
        self.host.after_write = lambda path, operation: self.host.paths[REPAIR.LOCK].update(ctimeNs=42)
        result = self.host.run(apply=True)
        self.assertEqual("partial", result["result"])
        self.assertEqual("original-lock-identity-changed", result["diagnostic"])
        self.assertEqual([(REPAIR.TARGETS[0], "chown")], self.host.writes)

    def test_main_default_dry_run_is_fixed_json_and_apply_is_explicit(self):
        for args, applying in (([], False), (["--apply"], True)):
            with self.subTest(args=args), patch.object(REPAIR, "repair", return_value={"result": "dry-run"}) as run:
                with patch("sys.stdout", new_callable=io.StringIO) as output:
                    self.assertEqual(0, REPAIR.main(args))
                run.assert_called_once_with(apply=applying)
                self.assertEqual({"result": "dry-run"}, json.loads(output.getvalue()))

    def test_controlled_source_import_reads_only_public_reviewed_constants(self):
        # Windows lacks these flags; this check only imports a local reviewed source.
        native_fstat = os.fstat
        source = ROOT / "bin/fireguard-deployment-recovery.py"

        def compatible_fstat(descriptor):
            value = native_fstat(descriptor)
            if os.name != "nt":
                return value
            # Python's Windows path/descriptor stats expose different legacy ctime semantics.
            fields = ("st_dev", "st_ino", "st_uid", "st_gid", "st_mode", "st_size", "st_mtime_ns")
            return SimpleNamespace(**{key: getattr(value, key) for key in fields},
                                   st_ctime_ns=os.lstat(source).st_ctime_ns)

        with patch.object(REPAIR.os, "O_NOFOLLOW", getattr(os, "O_NOFOLLOW", 0), create=True), \
                patch.object(REPAIR.os, "O_CLOEXEC", getattr(os, "O_CLOEXEC", 0), create=True), \
                patch.object(REPAIR.os, "fstat", compatible_fstat):
            namespace, lock = REPAIR.reviewed_constants()
        self.assertEqual(reviewed(), (namespace, lock))

    def test_both_files_parse_with_python_310_grammar(self):
        for source in (HELPER, Path(__file__)):
            with self.subTest(source=source.name):
                ast.parse(source.read_text(encoding="utf-8"), feature_version=(3, 10))


if __name__ == "__main__":
    unittest.main()
