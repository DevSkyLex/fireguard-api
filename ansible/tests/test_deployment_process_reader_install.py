"""Hermetic installer tests; native root/systemd activation is explicitly opt-in."""

import ast
import copy
import hashlib
import importlib.util
import io
import json
import os
from pathlib import Path, PurePosixPath
import stat
import subprocess
import tempfile
from types import SimpleNamespace
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT / "bin/fireguard-deployment-process-reader-install.py"
SPEC = importlib.util.spec_from_file_location("deployment_process_reader_install", SOURCE)
INSTALL = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(INSTALL)


class FakeHost:
    """Pinned descriptors reference nodes independently of mutable path attachments."""

    def __init__(self, *, libexec=False):
        self.source_directory = "/cache"
        self.paths, self.descriptors, self.opens, self.closed = {}, {}, [], []
        self.mutations, self.commands = [], []
        self.effective_queries = []
        self.effective_properties = {path: f"FragmentPath={path}\nDropInPaths=\n".encode() for path in INSTALL.TARGETS[1:]}
        self.uid, self.umask, self.write_limit = 0, 0o022, None
        self.fail_write, self.fail_command, self.on_read, self.on_create, self.on_command = None, None, None, None, None
        self.noncanonical = None
        for path in ("/", "/cache", "/usr", "/usr/local", "/usr/bin", "/etc", "/etc/systemd", "/etc/systemd/system"):
            self.add(path, stat.S_IFDIR | 0o755)
        if libexec:
            self.add(INSTALL.OPTIONAL_DIRECTORY, stat.S_IFDIR | 0o755)
        self.add(f"/cache/{INSTALL.NAME}-install.py", stat.S_IFREG | 0o644, b"reviewed installer")
        for target in INSTALL.TARGETS:
            self.add(f"/cache/{PurePosixPath(target).name}", stat.S_IFREG | 0o644,
                     f"reviewed {PurePosixPath(target).name}".encode())
        self.add("/usr/bin/python3.12", stat.S_IFREG | 0o755, b"interpreter")
        self.add(INSTALL.INTERPRETER, stat.S_IFLNK | 0o777, link="/usr/bin/python3.12")

    def add(self, path, mode, contents=b"", *, link=None):
        self.paths[path] = {"st_dev": 1, "st_ino": 10 + len(self.paths), "st_uid": 0,
                            "st_gid": 0, "st_mode": mode, "st_mtime_ns": 1, "st_ctime_ns": 1,
                            "contents": contents, "link": link}
        return self.paths[path]

    @staticmethod
    def snapshot(node):
        fields = {key: value for key, value in node.items() if key.startswith("st_")}
        fields["st_size"] = len(node["contents"]) if stat.S_ISREG(node["st_mode"]) else 4096
        return SimpleNamespace(**fields)

    def node(self, path):
        if path not in self.paths:
            raise FileNotFoundError("private missing-file description")
        return self.paths[path]

    def require_supported_root(self):
        INSTALL.require(self.uid == 0, "installer-requires-root")

    def lstat(self, path):
        return self.snapshot(self.node(path))

    def realpath(self, path, *, strict=False):
        node = self.node(path)
        return "/foreign/private" if path == self.noncanonical else node["link"] or path

    def relative_path(self, name, parent):
        if parent is None:
            return name
        parent_path = self.descriptors[parent][1]
        return str(PurePosixPath(parent_path) / name)

    def relative_stat(self, name, parent):
        return self.lstat(self.relative_path(name, parent))

    def open_node(self, path, *, create=False, directory=False):
        if create:
            if self.on_create is not None:
                self.on_create(path)
            if path in self.paths:
                raise FileExistsError("private conflicting-file description")
            self.add(path, stat.S_IFREG | (0o600 & ~self.umask))
            self.mutations.append(("create", path))
        node = self.node(path)
        if stat.S_ISLNK(node["st_mode"]):
            raise OSError("private symlink description")
        if directory and not stat.S_ISDIR(node["st_mode"]):
            raise NotADirectoryError("private non-directory description")
        descriptor = 20 + len(self.opens)
        self.opens.append((path, create, directory))
        self.descriptors[descriptor] = [node, path, 0]
        return descriptor

    def open_directory(self, name, parent):
        return self.open_node(self.relative_path(name, parent), directory=True)

    def open_file(self, name, parent, *, create=False):
        return self.open_node(self.relative_path(name, parent), create=create)

    def close(self, descriptor):
        self.closed.append(descriptor)
        del self.descriptors[descriptor]

    def fstat(self, descriptor):
        return self.snapshot(self.descriptors[descriptor][0])

    def read(self, descriptor, count):
        node, path, offset = self.descriptors[descriptor]
        contents = node["contents"][offset:offset + count]
        self.descriptors[descriptor][2] += len(contents)
        if self.on_read is not None:
            self.on_read(path, node)
        return contents

    def write(self, descriptor, contents):
        node, path, _ = self.descriptors[descriptor]
        if path == self.fail_write:
            raise OSError("private write-error description")
        written = len(contents) if self.write_limit is None else min(self.write_limit, len(contents))
        node["contents"] += contents[:written]
        node["st_mtime_ns"] += 1
        node["st_ctime_ns"] += 1
        self.mutations.append(("write", path))
        return written

    def fchown(self, descriptor, uid, gid):
        node, path, _ = self.descriptors[descriptor]
        node.update(st_uid=uid, st_gid=gid, st_ctime_ns=node["st_ctime_ns"] + 1)
        self.mutations.append(("chown", path))

    def fchmod(self, descriptor, mode):
        node, path, _ = self.descriptors[descriptor]
        node.update(st_mode=stat.S_IFMT(node["st_mode"]) | mode, st_ctime_ns=node["st_ctime_ns"] + 1)
        self.mutations.append(("chmod", path))

    def fsync(self, descriptor):
        self.mutations.append(("fsync", self.descriptors[descriptor][1]))

    def mkdir(self, name, parent):
        path = self.relative_path(name, parent)
        if path in self.paths:
            raise FileExistsError("private conflicting-directory description")
        self.add(path, stat.S_IFDIR | (0o755 & ~self.umask))
        self.mutations.append(("mkdir", path))

    def run(self, command):
        self.commands.append(command)
        if self.on_command is not None:
            self.on_command(command)
        return 1 if len(self.commands) == self.fail_command else 0

    def effective_units_match(self):
        for path in INSTALL.TARGETS[1:]:
            self.effective_queries.append((PurePosixPath(path).name, path, len(self.commands)))
            if not INSTALL.effective_properties_match(self.effective_properties[path], path):
                return False
        return True

    def put_matching_targets(self):
        if INSTALL.OPTIONAL_DIRECTORY not in self.paths:
            self.add(INSTALL.OPTIONAL_DIRECTORY, stat.S_IFDIR | 0o755)
        for target in INSTALL.TARGETS:
            contents = self.paths[f"/cache/{PurePosixPath(target).name}"]["contents"]
            self.add(target, stat.S_IFREG | 0o644, contents)


class DeploymentProcessReaderInstallTest(unittest.TestCase):
    def setUp(self):
        self.host = FakeHost()

    def run_install(self, *, apply=False):
        result = INSTALL.install(apply=apply, host=self.host)
        self.assertEqual({}, self.host.descriptors)
        self.assertEqual(len(self.host.opens), len(self.host.closed))
        self.assertNotIn("private", json.dumps(result))
        return result

    def assert_denied(self, diagnostic=None):
        result = self.run_install(apply=True)
        self.assertEqual("blocked", result["result"])
        self.assertEqual([], self.host.mutations)
        self.assertEqual([], self.host.commands)
        self.assertEqual([], self.host.effective_queries)
        if diagnostic:
            self.assertEqual(diagnostic, result["diagnostic"])
        return result

    def test_default_dry_run_reads_fixed_sources_without_any_mutation_or_command(self):
        before = copy.deepcopy(self.host.paths)
        result = self.run_install()
        self.assertEqual("dry-run", result["result"])
        self.assertEqual([], self.host.mutations)
        self.assertEqual([], self.host.commands)
        self.assertEqual([], result["steps"])
        self.assertEqual(before, self.host.paths)
        self.assertEqual(list(INSTALL.TARGETS), [entry["path"] for entry in result["targets"]])
        self.assertTrue(all(entry["state"] == "missing" and len(entry["sha256"]) == 64 for entry in result["targets"]))

    def test_root_required_for_default_and_apply_before_any_reads(self):
        for apply in (False, True):
            with self.subTest(apply=apply):
                self.host.uid = 1001
                result = self.run_install(apply=apply)
                self.assertEqual("installer-requires-root", result["diagnostic"])
                self.assertEqual([], self.host.opens)

    def test_all_source_and_parent_owners_modes_and_links_fail_before_mutation(self):
        paths = ("/", "/cache", "/usr", "/usr/local", "/etc/systemd/system",
                 f"/cache/{INSTALL.NAME}.py", f"/cache/{INSTALL.NAME}.service",
                 f"/cache/{INSTALL.NAME}.socket", f"/cache/{INSTALL.NAME}-install.py")
        for path in paths:
            for field, value in (("st_uid", 1001), ("st_gid", 1001), ("st_mode", stat.S_IFLNK | 0o777)):
                with self.subTest(path=path, field=field):
                    self.host = FakeHost()
                    self.host.paths[path][field] = value
                    self.assert_denied()
            for write in (0o020, 0o002):
                with self.subTest(path=path, write=write):
                    self.host = FakeHost()
                    self.host.paths[path]["st_mode"] |= write
                    self.assert_denied()

    def test_noncanonical_source_directory_is_denied(self):
        self.host.noncanonical = "/cache"
        self.assert_denied("directory-not-canonical")

    def test_interpreter_symlink_only_resolves_to_protected_executable(self):
        for path, field, value in ((INSTALL.INTERPRETER, "st_uid", 1001),
                                   ("/usr/bin/python3.12", "st_mode", stat.S_IFREG | 0o777),
                                   ("/usr/bin/python3.12", "st_mode", stat.S_IFREG | 0o644),
                                   ("/usr/bin", "st_mode", stat.S_IFDIR | 0o775)):
            with self.subTest(path=path, field=field):
                self.host = FakeHost()
                self.host.paths[path][field] = value
                self.assert_denied()

    def test_source_bounded_reads_and_identity_changes_are_denied(self):
        source = f"/cache/{INSTALL.NAME}.py"
        for contents in (b"", b"x" * (INSTALL.MAX_BYTES + 1)):
            with self.subTest(length=len(contents)):
                self.host = FakeHost()
                self.host.paths[source]["contents"] = contents
                self.assert_denied()
        self.host = FakeHost()
        self.host.on_read = lambda path, node: node.update(st_ctime_ns=42) if path == source else None
        self.assert_denied("file-identity-changed")

    def test_every_existing_target_must_match_content_owner_and_exact_mode(self):
        for target in INSTALL.TARGETS:
            for field, value in (("contents", b"different"), ("st_uid", 1001), ("st_gid", 1001),
                                 ("st_mode", stat.S_IFREG | 0o600), ("st_mode", stat.S_IFLNK | 0o777)):
                with self.subTest(target=target, field=field):
                    self.host = FakeHost()
                    self.host.put_matching_targets()
                    self.host.paths[target][field] = value
                    self.assert_denied()

    def test_all_three_targets_are_preflighted_before_creating_first_file(self):
        self.host.put_matching_targets()
        del self.host.paths[INSTALL.TARGETS[0]]
        self.host.paths[INSTALL.TARGETS[-1]]["contents"] = b"different"
        self.assert_denied("existing-target-differs")

    def test_apply_creates_only_fixed_files_and_optional_directory_then_starts_without_enable(self):
        original = copy.deepcopy(self.host.paths)
        result = self.run_install(apply=True)
        self.assertEqual("complete", result["result"])
        self.assertEqual({INSTALL.OPTIONAL_DIRECTORY, *INSTALL.TARGETS}, set(self.host.paths) - set(original))
        for path, node in original.items():
            self.assertEqual(node, self.host.paths[path])
        self.assertEqual([command for _, command in INSTALL.COMMANDS], self.host.commands)
        self.assertEqual([(PurePosixPath(path).name, path, 2) for path in INSTALL.TARGETS[1:]], self.host.effective_queries)
        self.assertFalse(any("enable" in command or "stop" in command for command in self.host.commands))
        for target in INSTALL.TARGETS:
            node = self.host.paths[target]
            self.assertEqual((0, 0, stat.S_IFREG | 0o644), (node["st_uid"], node["st_gid"], node["st_mode"]))
        self.assertEqual(stat.S_IFDIR | 0o755, self.host.paths[INSTALL.OPTIONAL_DIRECTORY]["st_mode"])

    def test_strict_umask_and_short_writes_still_produce_reviewed_files(self):
        self.host.umask, self.host.write_limit = 0o077, 2
        self.assertEqual("complete", self.run_install(apply=True)["result"])

    def test_exact_preexisting_files_are_accepted_without_overwrite_and_retry_is_idempotent(self):
        self.host.put_matching_targets()
        before = copy.deepcopy(self.host.paths)
        result = self.run_install(apply=True)
        self.assertEqual("complete", result["result"])
        self.assertEqual([], self.host.mutations)
        self.assertEqual(before, self.host.paths)
        self.assertTrue(all(entry["state"] == "matching" for entry in result["targets"]))

    def test_exclusive_create_refuses_file_appearing_after_preflight(self):
        def appear(path):
            self.host.add(path, stat.S_IFREG | 0o644, b"unexpected")
        self.host.on_create = appear
        result = self.run_install(apply=True)
        self.assertEqual("partial", result["result"])
        self.assertEqual(b"unexpected", self.host.paths[INSTALL.TARGETS[0]]["contents"])
        self.assertEqual([], self.host.commands)
        self.assertFalse(any(operation == "write" for operation, _ in self.host.mutations))

    def test_failed_write_retains_partial_file_without_rollback_and_blocks_later_commands(self):
        self.host.fail_write = INSTALL.TARGETS[1]
        result = self.run_install(apply=True)
        self.assertEqual("partial", result["result"])
        self.assertIn(INSTALL.TARGETS[0], self.host.paths)
        self.assertIn(INSTALL.TARGETS[1], self.host.paths)
        self.assertNotIn(INSTALL.TARGETS[2], self.host.paths)
        self.assertEqual([], self.host.commands)
        self.assertEqual("attempted", result["steps"][-1]["result"])

    def test_each_systemd_failure_stops_following_commands_and_retains_files(self):
        for failing in (1, 2, 3):
            with self.subTest(failing=failing):
                self.host = FakeHost()
                self.host.fail_command = failing
                result = self.run_install(apply=True)
                self.assertEqual("partial", result["result"])
                self.assertEqual(failing, len(self.host.commands))
                self.assertTrue(all(target in self.host.paths for target in INSTALL.TARGETS))
                self.assertEqual(f"{INSTALL.COMMANDS[failing - 1][0]}-failed", result["diagnostic"])

    def test_effective_overrides_aliases_and_malformed_properties_refuse_socket_start(self):
        for target in INSTALL.TARGETS[1:]:
            outputs = (f"FragmentPath={target}\nDropInPaths=/private/override.conf\n".encode(),
                       b"FragmentPath=/private/alias.service\nDropInPaths=\n",
                       b"FragmentPath=\nDropInPaths=\n", b"DropInPaths=\nDropInPaths=\n",
                       f"FragmentPath={target}\nDropInPaths=\nUnknown=value\n".encode(),
                       b"\xff\n", b"x" * 8193)
            for contents in outputs:
                with self.subTest(target=target, length=len(contents)):
                    self.host = FakeHost()
                    self.host.effective_properties[target] = contents
                    result = self.run_install(apply=True)
                    self.assertEqual("partial", result["result"])
                    self.assertEqual("effective-unit-configuration-unverified", result["diagnostic"])
                    self.assertEqual([command for _, command in INSTALL.COMMANDS[:2]], self.host.commands)
                    self.assertEqual("attempted", result["steps"][-1]["result"])

    def test_effective_property_order_is_immaterial_but_values_are_exact(self):
        for target in INSTALL.TARGETS[1:]:
            contents = f"DropInPaths=\nFragmentPath={target}\n".encode()
            self.assertTrue(INSTALL.effective_properties_match(contents, target))

    def test_directory_or_target_detachment_during_apply_is_fail_closed(self):
        def detach(command):
            self.host.paths[INSTALL.TARGETS[0]]["st_ino"] += 1
        self.host.on_command = detach
        result = self.run_install(apply=True)
        self.assertEqual("partial", result["result"])
        self.assertEqual("target-identity-changed", result["diagnostic"])
        self.assertEqual(1, len(self.host.commands))

    def test_main_has_only_explicit_apply_no_path_or_command_configuration(self):
        for argv, applying in (([], False), (["--apply"], True)):
            with self.subTest(argv=argv), patch.object(INSTALL, "install", return_value={"result": "dry-run"}) as run:
                with patch("sys.stdout", new_callable=io.StringIO) as output:
                    self.assertEqual(0, INSTALL.main(argv))
                run.assert_called_once_with(apply=applying)
                self.assertEqual({"result": "dry-run"}, json.loads(output.getvalue()))
        with patch("sys.stderr", new_callable=io.StringIO), self.assertRaises(SystemExit):
            INSTALL.main(["--source", "/arbitrary"])

    def test_sources_parse_using_python_310_grammar(self):
        for source in (SOURCE, Path(__file__)):
            ast.parse(source.read_text(encoding="utf-8"), feature_version=(3, 10))


@unittest.skipUnless(os.environ.get("FIREGUARD_TEST_PROCESS_READER_NATIVE") == "1",
                     "native root/systemd installer fixture is explicitly opt-in")
class NativeSystemdReaderTests(unittest.TestCase):
    """Actual installation and UID 1001 socket exchange on an ephemeral CI VM."""

    @staticmethod
    def command(*arguments, timeout=30):
        return subprocess.run(arguments, stdin=subprocess.DEVNULL, capture_output=True,
                              timeout=timeout, check=False,
                              env={"PATH": "/usr/sbin:/usr/bin:/sbin:/bin", "LANG": "C", "LC_ALL": "C"})

    def test_real_root_installation_and_temporary_systemd_activation(self):
        self.assertEqual("posix", os.name)
        self.assertEqual((0, 0, 0, 0), (os.getuid(), os.geteuid(), os.getgid(), os.getegid()))
        self.assertTrue(Path("/run/systemd/system").is_dir(), "native systemd is required")
        socket_path = Path(f"/run/{INSTALL.NAME}.sock")
        for path in (*INSTALL.TARGETS, str(socket_path)):
            with self.assertRaises(FileNotFoundError, msg="fixture refuses preexisting fixed targets"):
                os.lstat(path)
        for suffix in ("service", "socket"):
            state = self.command("/usr/bin/systemctl", "show", "--property=LoadState", "--value", f"{INSTALL.NAME}.{suffix}")
            self.assertEqual(b"not-found", state.stdout.strip(), "fixture refuses preexisting reader units")
        cache = Path(tempfile.mkdtemp(prefix="fireguard-reader-install-test-", dir="/root"))
        cache_identity = INSTALL.directory_identity(cache.lstat())
        copied, created, report = {}, {}, None
        try:
            for name in (f"{INSTALL.NAME}-install.py", *(PurePosixPath(path).name for path in INSTALL.TARGETS)):
                contents = (ROOT / "bin" / name).read_bytes()
                destination = cache / name
                descriptor = os.open(destination, os.O_WRONLY | os.O_CREAT | os.O_EXCL | os.O_NOFOLLOW, 0o600)
                with os.fdopen(descriptor, "wb") as stream:
                    stream.write(contents)
                    stream.flush()
                    os.fchmod(stream.fileno(), 0o644)
                    os.fsync(stream.fileno())
                copied[destination] = (INSTALL.identity(destination.lstat()), hashlib.sha256(contents).hexdigest())
            dry_run = self.command(INSTALL.INTERPRETER, "-I", "-S", "-B", str(cache / f"{INSTALL.NAME}-install.py"))
            self.assertEqual(0, dry_run.returncode, "actual root dry-run must succeed")
            self.assertEqual("dry-run", json.loads(dry_run.stdout)["result"])
            for path in INSTALL.TARGETS:
                with self.assertRaises(FileNotFoundError):
                    os.lstat(path)
            applying = self.command(INSTALL.INTERPRETER, "-I", "-S", "-B",
                                    str(cache / f"{INSTALL.NAME}-install.py"), "--apply", timeout=90)
            report = json.loads(applying.stdout)
            # Only exact, protected fixture files are eligible for later cleanup.
            for path in INSTALL.TARGETS:
                candidate = Path(path)
                if not candidate.is_file() or candidate.is_symlink():
                    continue
                expected = copied[cache / candidate.name][1]
                if INSTALL.protected(candidate.lstat(), exact_mode=0o644) and hashlib.sha256(candidate.read_bytes()).hexdigest() == expected:
                    created[candidate] = (INSTALL.identity(candidate.lstat()), expected)
            self.assertEqual(0, applying.returncode, "actual root apply must succeed")
            self.assertEqual("complete", report["result"])
            self.assertEqual(set(map(Path, INSTALL.TARGETS)), set(created))
            self.assertEqual((0, 1001, 0o660),
                             (socket_path.lstat().st_uid, socket_path.lstat().st_gid, stat.S_IMODE(socket_path.lstat().st_mode)))
            self.assertEqual(0, self.command("/usr/bin/systemctl", "is-active", "--quiet", f"{INSTALL.NAME}.socket").returncode)
            self.assertEqual(b"static", self.command("/usr/bin/systemctl", "is-enabled", f"{INSTALL.NAME}.socket").stdout.strip())
            client = "\n".join((
                "import importlib.util, json, os",
                "os.setgroups([1001]); os.setgid(1001); os.setuid(1001); os.chdir('/')",
                f"spec = importlib.util.spec_from_file_location('recovery', {str(ROOT / 'bin/fireguard-deployment-recovery.py')!r})",
                "module = importlib.util.module_from_spec(spec); spec.loader.exec_module(module)",
                "records = module.privileged_process_records()",
                "print(json.dumps({'recordsPresent': bool(records), 'callerPresent': any(r['pid'] == os.getpid() for r in records)}))",
            ))
            exchange = self.command(INSTALL.INTERPRETER, "-I", "-S", "-B", "-c", client)
            self.assertEqual(0, exchange.returncode, "installed root service must answer the real UID 1001 client")
            self.assertEqual({"recordsPresent": True, "callerPresent": True}, json.loads(exchange.stdout))
            self.assertEqual(0, self.command("/usr/bin/systemctl", "is-active", "--quiet", f"{INSTALL.NAME}.service").returncode)
            service_pid = self.command("/usr/bin/systemctl", "show", "--property=MainPID", "--value", f"{INSTALL.NAME}.service")
            self.assertEqual(0, service_pid.returncode)
            self.assertTrue(service_pid.stdout.strip().isdigit(), "reader MainPID must be numeric")
            pid = int(service_pid.stdout.strip())
            self.assertGreater(pid, 1)
            status = dict(line.split(":", 1) for line in (Path("/proc") / str(pid) / "status").read_text().splitlines() if ":" in line)
            self.assertEqual(["0"] * 4, status["Uid"].split())
            self.assertEqual(["0"] * 4, status["Gid"].split())
            self.assertNotIn("1001", status["Groups"].split())
            self.assertEqual("1", status["NoNewPrivs"].strip())
            for field in ("CapEff", "CapPrm", "CapBnd"):
                self.assertEqual(0x80004, int(status[field].strip(), 16))
        finally:
            if created:
                self.command("/usr/bin/systemctl", "stop", f"{INSTALL.NAME}.socket", f"{INSTALL.NAME}.service")
            for path, (pinned, digest) in created.items():
                self.assertEqual(pinned, INSTALL.identity(path.lstat()), "cleanup refuses a changed fixture file")
                self.assertTrue(INSTALL.protected(path.lstat(), exact_mode=0o644))
                self.assertEqual(digest, hashlib.sha256(path.read_bytes()).hexdigest())
                path.unlink()
            if created:
                self.command("/usr/bin/systemctl", "daemon-reload")
                self.assertFalse(socket_path.exists(), "temporary socket must be removed after reader stop")
            for path, (pinned, digest) in copied.items():
                self.assertEqual(pinned, INSTALL.identity(path.lstat()), "cleanup refuses a changed cache file")
                self.assertEqual(digest, hashlib.sha256(path.read_bytes()).hexdigest())
                path.unlink()
            self.assertEqual(cache_identity, INSTALL.directory_identity(cache.lstat()))
            cache.rmdir()


if __name__ == "__main__":
    unittest.main()
