"""Bounded protocol/identity tests; native root fixture is explicitly opt-in."""

import ast
import copy
import errno
import importlib.util
import json
import os
from pathlib import Path
import socket
import stat
import struct
import sys
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "bin/fireguard-deployment-process-reader.py"
SPEC = importlib.util.spec_from_file_location("deployment_process_reader", SOURCE)
READER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(READER)
NONCE = "a" * 64
DIGEST = "b" * 64


def native_failure(phase, error, recovery_error_type):
    """Fixture diagnostics expose fixed phases/codes, never host process data."""
    phases = {"credentials", "direct-read", "identity", "connect", "exchange", "target", "policy", "hostile-policy", "result"}
    recovery_codes = {"own-process-ancestry-unverified", "peer-group-metadata-unverified",
                      "peer-group-writer-or-ambiguous-process", "concurrent-host-owner-or-ambiguous-process"}
    if isinstance(error, READER.ReaderBlocked):
        code = error.code.value
    elif isinstance(error, recovery_error_type):
        code = str(error) if str(error) in recovery_codes else "unexpected-policy-failure"
    elif isinstance(error, OSError):
        code = {errno.EACCES: "EACCES", errno.EPERM: "EPERM"}.get(error.errno, "OTHER")
    elif isinstance(error, StopIteration):
        code = "target-disappeared"
    else:
        code = "unexpected-exception"
    return {"failure": {"phase": phase if phase in phases else "fixture", "code": code}}


class Connection:
    def __init__(self, value, *, raw=False, peer=(10, 1001, 1001)):
        self.data = value if raw else json.dumps(value).encode()
        self.sent = b""
        self.peer = peer
        self.received = False

    def settimeout(self, _timeout):
        pass

    def recv(self, size):
        self.received = True
        result, self.data = self.data[:size], self.data[size:]
        return result

    def sendall(self, data):
        self.sent += data

    def shutdown(self, _direction):
        pass

    def getsockopt(self, _level, _option, _size):
        return struct.pack("3i", *self.peer)


class ReaderTests(unittest.TestCase):
    def setUp(self):
        self.folder = tempfile.TemporaryDirectory()
        self.addCleanup(self.folder.cleanup)
        self.proc = Path(self.folder.name)
        self.write_process(1, 0, 0, "init")
        self.write_process(10, 1, 1001, "python3")
        self.write_process(20, 1, 1001, "fg-peer", groups=(1001, 1001))
        # Windows may not allow symlink creation: link reads are mocked in the
        # hermetic suite. The native Linux test below uses genuine proc links.
        (self.proc / "20" / "fd" / "3").write_text("not a target file")
        self.links = {str(self.proc / "20" / "cwd"): "/tmp",
                      str(self.proc / "20" / "fd" / "3"): "pipe:[123]"}
        self.link_patch = patch.object(READER.os, "readlink", side_effect=lambda path: self.links[str(path)])
        self.link_patch.start()
        self.addCleanup(self.link_patch.stop)

    def write_process(self, pid, ppid, uid, comm, *, start=100, groups=None, uids=None, gids=None):
        path = self.proc / str(pid)
        path.mkdir(exist_ok=True)
        (path / "fd").mkdir(exist_ok=True)
        fields = ["S", str(ppid)] + ["0"] * 17 + [str(start)]
        (path / "stat").write_text(str(pid) + " (" + comm + ") " + " ".join(fields))
        values = {"Name": comm, "PPid": str(ppid), "Uid": " ".join(map(str, uids or [uid] * 4)),
                  "Gid": " ".join(map(str, gids or [uid] * 4)),
                  "Groups": " ".join(map(str, groups if groups is not None else [uid])), "Kthread": "0"}
        (path / "status").write_text("\n".join(key + ":\t" + value for key, value in values.items()))

    def snapshot(self):
        return READER.collect_snapshot(10, 1001, proc=self.proc)

    def envelope(self):
        caller, records = self.snapshot()
        return caller, {"version": 1, "nonce": NONCE, "sourceSha256": DIGEST,
                        "caller": {"pid": 10, "uid": 1001, "startTime": 100}, "records": records}

    def denied(self, operation, code=None):
        with self.assertRaises(READER.ReaderBlocked) as context:
            operation()
        if code is not None:
            self.assertEqual(code, context.exception.code)
        self.assertIn(str(context.exception), [item.value for item in READER.ErrorCode])
        self.assertNotIn(str(self.proc), str(context.exception))
        return context.exception

    def test_snapshot_reads_only_nonancestor_deployment_links(self):
        caller, records = self.snapshot()
        self.assertEqual(10, caller["pid"])
        self.assertEqual([1001, 1001], records[2]["groups"])
        self.assertEqual(("/tmp", ["pipe:[123]"]), (records[2]["cwd"], records[2]["fds"]))
        self.assertEqual(["", ""], [item["cwd"] for item in records[:2]])
        self.assertEqual(2, READER.os.readlink.call_count)

    def test_foreign_credentials_preserved_without_link_reads(self):
        self.write_process(30, 1, 2000, "foreign", uids=[2000, 0, 2000, 2000], gids=[2000, 1001, 2000, 2000], groups=[1001, 1001])
        record = self.snapshot()[1][-1]
        self.assertEqual([2000, 0, 2000, 2000], record["uids"])
        self.assertEqual([2000, 1001, 2000, 2000], record["gids"])
        self.assertEqual("", record["cwd"])
        self.assertEqual([], record["fds"])

    def test_kernel_threads_have_no_link_reads(self):
        path = self.proc / "20" / "status"
        path.write_text(path.read_text().replace("Kthread:\t0", "Kthread:\t1"))
        self.assertTrue(self.snapshot()[1][2]["kernelThread"])
        READER.os.readlink.assert_not_called()

    def test_missing_ancestor_or_cycle_is_denied(self):
        for parent in (99, 20):
            with self.subTest(parent=parent):
                self.write_process(1, parent, 0, "init")
                self.denied(self.snapshot, READER.ErrorCode.CHANGED)

    def test_wrong_or_mixed_caller_identity_is_denied(self):
        self.denied(lambda: READER.collect_snapshot(10, 0, proc=self.proc), READER.ErrorCode.PEER)
        self.write_process(10, 1, 1001, "python3", uids=[1001, 0, 1001, 1001])
        self.denied(self.snapshot, READER.ErrorCode.PEER)

    def test_duplicate_or_incomplete_status_is_denied(self):
        path = self.proc / "20" / "status"
        original = path.read_text()
        for value in (original + "\nUid:\t1001 1001 1001 1001", original.replace("Gid:", "Unknown:")):
            with self.subTest(value=value):
                path.write_text(value)
                self.denied(self.snapshot)

    def test_credentials_do_not_deduplicate_groups(self):
        self.assertEqual([1001, 1001], READER._identity(self.proc, 20)["groups"])

    def test_stat_pid_reuse_is_denied(self):
        original = READER._stat_identity
        calls = 0

        def changed(path, pid):
            nonlocal calls
            calls += 1
            parent, start = original(path, pid)
            return parent, start + (calls == 2)

        with patch.object(READER, "_stat_identity", side_effect=changed):
            self.denied(lambda: READER._identity(self.proc, 20), READER.ErrorCode.CHANGED)

    def test_peer_changes_credentials_after_links_is_denied(self):
        original = READER._identity
        calls = 0

        def changed(proc, pid):
            nonlocal calls
            result = original(proc, pid)
            if pid == 20:
                calls += 1
                if calls > 1:
                    result["gids"] = [0] * 4
            return result

        with patch.object(READER, "_identity", side_effect=changed):
            self.denied(self.snapshot)

    def test_foreign_exec_or_reparent_refreshes_fields_without_relaxing_identity(self):
        self.write_process(30, 1, 0, "before-exec")
        original = READER._identity
        calls = 0

        def changed(proc, pid):
            nonlocal calls
            result = original(proc, pid)
            if pid == 30:
                calls += 1
                if calls > 1:
                    result.update(comm="after-exec", ppid=99)
            return result

        with patch.object(READER, "_identity", side_effect=changed):
            record = self.snapshot()[1][-1]
        self.assertEqual((30, 0, "after-exec", 99, 100), (record["pid"], record["uid"], record["comm"], record["ppid"], record["startTime"]))

    def test_foreign_pid_reuse_or_credential_changes_remain_denied(self):
        self.write_process(30, 1, 0, "foreign")
        original = READER._identity
        for mutation in ({"startTime": 101}, {"uids": [1001] * 4, "uid": 1001}, {"gids": [1001] * 4}, {"groups": [0, 1001]}):
            calls = 0

            def changed(proc, pid):
                nonlocal calls
                result = original(proc, pid)
                if pid == 30:
                    calls += 1
                    if calls > 1:
                        result.update(mutation)
                return result

            with self.subTest(mutation=mutation), patch.object(READER, "_identity", side_effect=changed):
                self.denied(self.snapshot, READER.ErrorCode.FOREIGN_CHANGED)

    def test_ancestor_and_deployment_authority_exec_or_reparent_remains_denied(self):
        original = READER._identity
        for pid, mutation, group in ((1, {"comm": "renamed"}, None), (20, {"comm": "python3"}, None),
                                    (20, {"ppid": 10}, None), (30, {"comm": "renamed"}, 1001),
                                    (30, {"ppid": 10}, 1000)):
            if pid == 30:
                self.write_process(30, 1, 0, "foreign", groups=[0, group])
            calls = 0

            def changed(proc, identifier):
                nonlocal calls
                result = original(proc, identifier)
                if identifier == pid:
                    calls += 1
                    if calls > 1:
                        result.update(mutation)
                return result

            with self.subTest(pid=pid, mutation=mutation), patch.object(READER, "_identity", side_effect=changed):
                self.denied(self.snapshot, READER.ErrorCode.ANCESTOR_CHANGED if pid == 1 else READER.ErrorCode.AUTHORITY_CHANGED)

    def test_new_process_during_collection_is_denied(self):
        original = READER._inventory
        calls = 0

        def changed(proc):
            nonlocal calls
            calls += 1
            return original(proc) + ([99] if calls > 1 else [])

        with patch.object(READER, "_inventory", side_effect=changed):
            self.denied(self.snapshot, READER.ErrorCode.INVENTORY)

    def test_new_foreign_process_is_included_without_private_links(self):
        original = READER._inventory
        calls = 0

        def changed(proc):
            nonlocal calls
            calls += 1
            if calls == 2:
                self.write_process(30, 1, 0, "root-peer")
            return original(proc)

        with patch.object(READER, "_inventory", side_effect=changed):
            record = self.snapshot()[1][-1]
        self.assertEqual((30, 0, "", []), (record["pid"], record["uid"], record["cwd"], record["fds"]))

    def test_new_deployment_peer_is_included_with_metadata(self):
        original = READER._inventory
        calls = 0

        def changed(proc):
            nonlocal calls
            calls += 1
            if calls == 2:
                self.write_process(30, 1, 1001, "python3")
                self.links[str(self.proc / "30" / "cwd")] = "/tmp"
            return original(proc)

        with patch.object(READER, "_inventory", side_effect=changed):
            record = self.snapshot()[1][-1]
        self.assertEqual((30, 1001, "python3", "/tmp"), (record["pid"], record["uid"], record["comm"], record["cwd"]))

    def test_link_permission_denied_fails_without_private_error(self):
        READER.os.readlink.side_effect = PermissionError(errno.EACCES, "SECRET-CWD-DETAIL")
        error = self.denied(self.snapshot)
        self.assertNotIn("SECRET", str(error))

    def test_fd_disappears_while_peer_exists_is_denied(self):
        READER.os.readlink.side_effect = FileNotFoundError(errno.ENOENT, "SECRET-FD-DETAIL")
        self.denied(self.snapshot, READER.ErrorCode.RACE)

    def test_actual_disappearance_is_tolerated(self):
        original = READER._identity

        def vanished(proc, pid):
            if pid == 20:
                for path in (proc / "20" / "fd").iterdir():
                    path.unlink()
                (proc / "20" / "fd").rmdir()
                (proc / "20" / "status").unlink()
                (proc / "20" / "stat").unlink()
                (proc / "20").rmdir()
                raise READER.ReaderBlocked(READER.ErrorCode.METADATA)
            return original(proc, pid)

        with patch.object(READER, "_identity", side_effect=vanished):
            self.assertEqual([1, 10], [item["pid"] for item in self.snapshot()[1]])

    def test_time_record_and_fd_limits_are_closed(self):
        with patch.object(READER, "MAX_PROCESSES", 2):
            self.denied(self.snapshot, READER.ErrorCode.LIMIT)
        with patch.object(READER, "MAX_TOTAL_FDS", 0):
            self.denied(self.snapshot)
        with patch.object(READER.time, "monotonic", side_effect=[0, 21]):
            self.denied(self.snapshot, READER.ErrorCode.LIMIT)

    def test_collection_reserves_encoded_text_before_unbounded_memory_growth(self):
        with patch.object(READER, "MAX_RESPONSE", 1100):
            self.denied(self.snapshot, READER.ErrorCode.LIMIT)
        READER.os.readlink.assert_not_called()
        base = 1024 + sum(len(json.dumps(READER._identity(self.proc, pid), separators=(",", ":"), ensure_ascii=True).encode()) + 1
                          for pid in (1, 10, 20))
        self.links[str(self.proc / "20" / "fd" / "3")] = "é" * 1000
        with patch.object(READER, "MAX_RESPONSE", base + 100):
            self.denied(self.snapshot, READER.ErrorCode.LIMIT)

    def test_protocol_validates_complete_snapshot(self):
        caller, value = self.envelope()
        self.assertEqual(value["records"], READER._validate_response(value, NONCE, caller, DIGEST))

    def test_malformed_replay_hash_or_caller_is_denied(self):
        caller, original = self.envelope()
        mutations = [{"version": True}, {"version": 2}, {"nonce": "c" * 64}, {"sourceSha256": "c" * 64},
                     {"caller": {"pid": 11, "uid": 1001, "startTime": 100}},
                     {"caller": {"pid": 10, "uid": 1001, "startTime": 101}}, {"extra": "private"}]
        for mutation in mutations:
            with self.subTest(mutation=mutation):
                value = {**copy.deepcopy(original), **mutation}
                self.denied(lambda: READER._validate_response(value, NONCE, caller, DIGEST))

    def test_response_record_schema_and_partial_links_are_denied(self):
        caller, original = self.envelope()
        mutations = [{"pid": True}, {"uids": [1001]}, {"gids": [1001, -1, 1001, 1001]},
                     {"kernelThread": 0}, {"cwd": ""}, {"fds": [None]}, {"comm": ""}, {"unknown": "value"}]
        for mutation in mutations:
            with self.subTest(mutation=mutation):
                value = copy.deepcopy(original)
                value["records"][2].update(mutation)
                self.denied(lambda: READER._validate_response(value, NONCE, caller, DIGEST))

    def test_duplicate_process_ids_and_unsolicited_private_links_are_denied(self):
        caller, value = self.envelope()
        value["records"].append(copy.deepcopy(value["records"][2]))
        self.denied(lambda: READER._validate_response(value, NONCE, caller, DIGEST))
        caller, value = self.envelope()
        value["records"][0]["cwd"] = "/private/foreign"
        self.denied(lambda: READER._validate_response(value, NONCE, caller, DIGEST))

    def test_socket_json_truncated_duplicate_or_oversized_is_denied(self):
        for raw in (b'{"nonce":', b'{"nonce":1,"nonce":2}', b"[]", b'"secret"'):
            with self.subTest(raw=raw):
                self.denied(lambda: READER._receive(Connection(raw, raw=True), 100))
        self.denied(lambda: READER._receive(Connection(b"x" * 101, raw=True), 100), READER.ErrorCode.LIMIT)

    def test_slow_chunks_obey_total_deadline_without_renewing_timeout(self):
        connection = Connection(b"{}", raw=True)
        original = connection.recv
        connection.recv = lambda _size: original(1)
        with patch.object(READER.time, "monotonic", side_effect=[0, 1, 31]):
            self.denied(lambda: READER._receive(connection, 100), READER.ErrorCode.LIMIT)

    def test_loaded_source_digest_remains_attested_when_file_is_replaced(self):
        import hashlib

        with patch.object(READER, "_SOURCE_DIGEST", None), patch.object(Path, "read_bytes", side_effect=[b"loaded-source", b"new-source"]) as read:
            first = READER.source_digest()
            self.assertEqual(hashlib.sha256(b"loaded-source").hexdigest(), first)
            self.assertEqual(first, READER.source_digest())
            read.assert_called_once()

    def test_peer_credentials_must_be_kernel_attested(self):
        with patch.object(READER.socket, "SO_PEERCRED", 17, create=True):
            self.assertEqual((10, 1001, 1001), READER._peer(Connection({})))
            self.denied(lambda: READER._peer(Connection({}, peer=(-1, 1001, 1001))))

    def test_server_rejects_foreign_peer_before_reading_request(self):
        connection = Connection({"version": 1, "nonce": NONCE})
        with patch.object(READER, "_peer", return_value=(10, 2000, 2000)):
            self.assertEqual(READER.ErrorCode.PEER, READER._handle(connection))
        self.assertFalse(connection.received)
        self.assertEqual("peer-identity-unverified", json.loads(connection.sent)["error"])

    def test_server_rejects_arbitrary_scope_and_paths(self):
        for extra in ("pid", "path", "command", "proc", "uid"):
            with self.subTest(extra=extra):
                connection = Connection({"version": 1, "nonce": NONCE, extra: "SECRET"})
                with patch.object(READER, "_peer", return_value=(10, 1001, 1001)), patch.object(READER, "collect_snapshot") as collect:
                    self.assertEqual(READER.ErrorCode.REQUEST, READER._handle(connection))
                    collect.assert_not_called()
                self.assertNotIn(b"SECRET", connection.sent)

    def test_server_uses_peer_pid_not_request_and_returns_attested_hash(self):
        caller, records = self.snapshot()
        connection = Connection({"version": 1, "nonce": NONCE})
        with patch.object(READER, "_peer", return_value=(10, 1001, 1001)), \
                patch.object(READER, "collect_snapshot", return_value=(caller, records)) as collect, \
                patch.object(READER, "source_digest", return_value=DIGEST):
            self.assertIsNone(READER._handle(connection))
            collect.assert_called_once_with(10, 1001)
        self.assertEqual(DIGEST, json.loads(connection.sent)["sourceSha256"])

    def test_server_error_response_never_contains_private_paths(self):
        connection = Connection({"version": 1, "nonce": NONCE})
        with patch.object(READER, "_peer", return_value=(10, 1001, 1001)), \
                patch.object(READER, "collect_snapshot", side_effect=OSError("SECRET-PATH")):
            self.assertEqual(READER.ErrorCode.UNAVAILABLE, READER._handle(connection))
        self.assertEqual({"version": 1, "nonce": NONCE, "error": "reader-unavailable"}, json.loads(connection.sent))

    def test_client_accepts_root_systemd_pid_one_and_rejects_nonroot_peer(self):
        caller, value = self.envelope()
        connection = Connection(value, peer=(1, 0, 0))
        with patch.object(READER, "_peer", return_value=(1, 0, 0)), patch.object(READER.secrets, "token_hex", return_value=NONCE):
            self.assertEqual(value["records"], READER._exchange(connection, caller, DIGEST))
        with patch.object(READER, "_peer", return_value=(1, 0, 1001)):
            self.denied(lambda: READER._exchange(connection, caller, DIGEST), READER.ErrorCode.PEER)

    def test_public_client_rejects_foreign_pid_uid_and_partial_fallback(self):
        with patch.object(READER.os, "getuid", return_value=1001, create=True), \
                patch.object(READER.os, "geteuid", return_value=1001, create=True), patch.object(READER.os, "getpid", return_value=10):
            self.denied(lambda: READER.read_snapshot(11, 1001), READER.ErrorCode.PEER)
            self.denied(lambda: READER.read_snapshot(10, 0), READER.ErrorCode.PEER)

    def test_client_rejects_replaced_socket_and_changed_caller(self):
        caller, value = self.envelope()
        fake = Connection(value)
        fake.connect = lambda _path: None
        # Use a context-manager mock without relying on Windows AF_UNIX.
        from unittest.mock import MagicMock

        manager = MagicMock()
        manager.__enter__.return_value = fake
        common = {"getuid": 1001, "geteuid": 1001, "getpid": 10}
        with patch.multiple(READER.os, create=True, **{key: MagicMock(return_value=item) for key, item in common.items()}), \
                patch.object(READER, "_identity", return_value=caller), \
                patch.object(READER, "_socket_identity", side_effect=[(1, 2, 3), (1, 4, 5)]), \
                patch.object(READER.socket, "AF_UNIX", 1, create=True), \
                patch.object(READER.socket, "socket", return_value=manager), \
                patch.object(READER, "_exchange", return_value=value["records"]), \
                patch.object(READER, "source_digest", return_value=DIGEST):
            self.denied(lambda: READER.read_snapshot(10, 1001), READER.ErrorCode.CHANGED)
        changed = copy.deepcopy(caller)
        changed["startTime"] += 1
        with patch.multiple(READER.os, create=True, **{key: MagicMock(return_value=item) for key, item in common.items()}), \
                patch.object(READER, "_identity", side_effect=[caller, changed]), \
                patch.object(READER, "_socket_identity", return_value=(1, 2, 3)), \
                patch.object(READER.socket, "AF_UNIX", 1, create=True), \
                patch.object(READER.socket, "socket", return_value=manager), \
                patch.object(READER, "_exchange", return_value=value["records"]), \
                patch.object(READER, "source_digest", return_value=DIGEST):
            self.denied(lambda: READER.read_snapshot(10, 1001), READER.ErrorCode.CHANGED)

    def test_activation_rejects_saved_uid_or_deployment_group(self):
        from unittest.mock import MagicMock

        root = READER._identity(self.proc, 1)
        for field, value in (("uids", [0, 0, 1001, 0]), ("gids", [0, 0, 1001, 0]), ("groups", [0, 1001])):
            identity = {**root, field: value}
            with self.subTest(field=field), \
                    patch.multiple(READER.os, create=True, **{key: MagicMock(return_value=0) for key in ("getuid", "geteuid", "getgid", "getegid")}), \
                    patch.object(READER.os, "getgroups", return_value=[0], create=True), patch.object(READER, "_identity", return_value=identity):
                self.denied(READER.activated_socket, READER.ErrorCode.IDENTITY)

    def test_activation_requires_exact_pid_and_single_descriptor(self):
        from unittest.mock import MagicMock

        root = READER._identity(self.proc, 1)
        fake_path = MagicMock()
        fake_path.__str__.return_value = READER.INSTALLED_PATH
        for environment in ({"LISTEN_PID": "wrong", "LISTEN_FDS": "1"}, {"LISTEN_PID": str(os.getpid()), "LISTEN_FDS": "2"}):
            with self.subTest(environment=environment), \
                    patch.multiple(READER.os, create=True, **{key: MagicMock(return_value=0) for key in ("getuid", "geteuid", "getgid", "getegid")}), \
                    patch.object(READER.os, "getgroups", return_value=[0], create=True), patch.object(READER, "_identity", return_value=root), \
                    patch.object(READER, "Path", return_value=fake_path), patch.object(READER, "_protected_parents"), \
                    patch.object(READER, "_protected_node"), patch.object(READER, "source_digest", return_value=DIGEST), \
                    patch.object(READER, "_service_security"), \
                    patch.dict(os.environ, environment, clear=True):
                self.denied(READER.activated_socket, READER.ErrorCode.ACTIVATION)

    def test_service_requires_nnp_and_only_reviewed_effective_permitted_bounding_caps(self):
        expected = {"NoNewPrivs": "1", "CapEff": "0000000000080004", "CapPrm": "0000000000080004",
                    "CapBnd": "0000000000080004", "CapInh": "0000000000080004", "CapAmb": "0000000000080004"}

        def status(fields):
            return "\n".join(key + ":\t" + value for key, value in fields.items())

        with patch.object(READER, "_text", return_value=status(expected)):
            READER._service_security()
        for field, value in (("NoNewPrivs", "0"), ("CapEff", "0000000000000000"), ("CapPrm", "ffffffffffffffff"),
                             ("CapBnd", "ffffffffffffffff"), ("CapAmb", "0000000000000001"), ("CapInh", "bad-value")):
            with self.subTest(field=field), patch.object(READER, "_text", return_value=status({**expected, field: value})):
                self.denied(READER._service_security, READER.ErrorCode.IDENTITY)

    def test_protected_parent_rejects_symlink_user_owner_and_write_bits(self):
        for mode, uid in ((stat.S_IFDIR | 0o775, 0), (stat.S_IFDIR | 0o755, 1001), (stat.S_IFLNK | 0o777, 0)):
            with self.subTest(mode=mode, uid=uid), patch.object(Path, "lstat", return_value=SimpleNamespace(st_mode=mode, st_uid=uid)):
                self.denied(lambda: READER._protected_node(Path("/run"), directory=True), READER.ErrorCode.SOCKET)

    def test_socket_rejects_wrong_owner_group_type_and_mode(self):
        for mode, uid, gid in ((stat.S_IFSOCK | 0o660, 1001, 1001), (stat.S_IFSOCK | 0o660, 0, 0),
                               (stat.S_IFSOCK | 0o666, 0, 1001), (stat.S_IFREG | 0o660, 0, 1001)):
            node = SimpleNamespace(st_mode=mode, st_uid=uid, st_gid=gid)
            with self.subTest(mode=mode, uid=uid, gid=gid), patch.object(READER, "_protected_parents"), patch.object(Path, "lstat", return_value=node):
                self.denied(READER._socket_identity, READER.ErrorCode.SOCKET)

    def test_source_supports_python310_and_has_no_command_execution(self):
        tree = ast.parse(SOURCE.read_text(encoding="utf-8"), feature_version=(3, 10))
        imported = {alias.name for node in ast.walk(tree) if isinstance(node, ast.Import) for alias in node.names}
        self.assertFalse(imported & {"subprocess", "urllib", "requests", "importlib"})
        self.assertNotIn("os.system", SOURCE.read_text(encoding="utf-8"))

    def test_native_diagnostics_keep_closed_phase_and_error_without_private_content(self):
        class PolicyBlocked(RuntimeError):
            pass

        cases = [("exchange", READER.ReaderBlocked(READER.ErrorCode.CALLER_CHANGED), "caller-identity-changed"),
                 ("policy", PolicyBlocked("concurrent-host-owner-or-ambiguous-process"), "concurrent-host-owner-or-ambiguous-process"),
                 ("policy", PolicyBlocked("SECRET-PROCESS-PATH"), "unexpected-policy-failure"),
                 ("connect", PermissionError(errno.EACCES, "SECRET-PROCESS-PATH"), "EACCES"),
                 ("target", StopIteration(), "target-disappeared"),
                 ("result", ValueError("SECRET-PROCESS-PATH"), "unexpected-exception")]
        for phase, error, code in cases:
            with self.subTest(phase=phase, code=code):
                result = native_failure(phase, error, PolicyBlocked)
                self.assertEqual({"failure": {"phase": phase, "code": code}}, result)
                self.assertNotIn("SECRET", json.dumps(result))


@unittest.skipUnless(sys.platform == "linux" and os.environ.get("FIREGUARD_TEST_ROOT_PROCESS_READER") == "1",
                     "requires explicit Linux root VM fixture; not the Windows hermetic suite")
class NativeReaderTests(unittest.TestCase):
    def test_root_socket_reads_nondumpable_peer_and_keeps_existing_owner_policy(self):
        import ctypes
        import signal

        self.assertEqual(0, os.geteuid(), "run this opt-in fixture with sudo on the Linux VM")
        recovery_spec = importlib.util.spec_from_file_location("reader_native_recovery", ROOT / "bin/fireguard-deployment-recovery.py")
        recovery = importlib.util.module_from_spec(recovery_spec)
        recovery_spec.loader.exec_module(recovery)
        with tempfile.TemporaryDirectory(prefix="fireguard-reader-native-", dir="/run") as folder:
            os.chmod(folder, 0o755)
            ready_read, ready_write = os.pipe()
            target = os.fork()
            if target == 0:
                try:
                    os.close(ready_read)
                    os.setgroups([1001])
                    os.setgid(1001)
                    os.setuid(1001)
                    os.chdir("/")
                    libc = ctypes.CDLL(None, use_errno=True)
                    if libc.prctl(15, b"fg-peer", 0, 0, 0) != 0 or libc.prctl(4, 0, 0, 0, 0) != 0:
                        os._exit(2)
                    os.write(ready_write, b"R")
                    while True:
                        signal.pause()
                except BaseException:
                    os._exit(3)
            self.addCleanup(lambda: self.stop_child(target))
            os.close(ready_write)
            self.assertEqual(b"R", os.read(ready_read, 1))
            os.close(ready_read)
            path = str(Path(folder) / "socket")
            with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as listener:
                listener.bind(path)
                os.chown(path, 0, 1001)
                os.chmod(path, 0o660)
                listener.listen(1)
                listener.settimeout(30)
                result_read, result_write = os.pipe()
                caller = os.fork()
                if caller == 0:
                    phase = "credentials"
                    try:
                        listener.close()
                        os.close(result_read)
                        os.setgroups([1001])
                        os.setgid(1001)
                        os.setuid(1001)
                        phase = "direct-read"
                        denied = False
                        try:
                            os.readlink("/proc/" + str(target) + "/cwd")
                        except OSError as error:
                            denied = error.errno in (errno.EPERM, errno.EACCES)
                        phase = "identity"
                        identity = READER._identity(Path("/proc"), os.getpid())
                        with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as connection:
                            phase = "connect"
                            connection.connect(path)
                            phase = "exchange"
                            records = READER._exchange(connection, identity, READER.source_digest())
                        phase = "target"
                        peer = next(record for record in records if record["pid"] == target)
                        phase = "policy"
                        recovery.check_processes(records, os.getpid(), 1001, group_gid=1001)
                        phase = "hostile-policy"
                        hostile = copy.deepcopy(records)
                        next(record for record in hostile if record["pid"] == target)["comm"] = "python3"
                        refused = False
                        try:
                            recovery.check_processes(hostile, os.getpid(), 1001, group_gid=1001)
                        except recovery.RecoveryBlocked:
                            refused = True
                        phase = "result"
                        os.write(result_write, json.dumps({"directDenied": denied, "metadataRead": peer["cwd"] == "/",
                                                           "ownerStillDenied": refused}).encode())
                        os._exit(0)
                    except BaseException as error:
                        os.write(result_write, json.dumps(native_failure(phase, error, recovery.RecoveryBlocked)).encode())
                        os._exit(4)
                self.addCleanup(lambda: self.stop_child(caller))
                os.close(result_write)
                with listener.accept()[0] as connection:
                    self.assertIsNone(READER._handle(connection))
                result = os.read(result_read, 4096)
                os.close(result_read)
                _, status = os.waitpid(caller, 0)
                self.assertEqual(0, os.waitstatus_to_exitcode(status), result.decode("ascii"))
                self.assertEqual({"directDenied": True, "metadataRead": True, "ownerStillDenied": True}, json.loads(result))

    @staticmethod
    def stop_child(pid):
        import signal

        try:
            found, _ = os.waitpid(pid, os.WNOHANG)
        except ChildProcessError:
            return
        if found:
            return
        try:
            os.kill(pid, signal.SIGKILL)
        except ProcessLookupError:
            pass
        os.waitpid(pid, 0)


if __name__ == "__main__":
    unittest.main()
