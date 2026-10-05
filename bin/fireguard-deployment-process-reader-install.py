#!/usr/bin/env python3
"""Install the fixed read-only process reader; dry-run unless explicitly --apply.

Only three root-owned files and, if absent, /usr/local/libexec are created.
Activation starts the socket temporarily; it never enables boot activation.
Existing differing files are never replaced. A partial installation is retained
and reported for explicit review, without deletion or automatic rollback.
"""

import argparse
from contextlib import ExitStack
import hashlib
import json
import os
from pathlib import PurePosixPath
import stat
import subprocess


NAME = "fireguard-deployment-process-reader"
INTERPRETER = "/usr/bin/python3"
TARGETS = (f"/usr/local/libexec/{NAME}.py",
           f"/etc/systemd/system/{NAME}.service", f"/etc/systemd/system/{NAME}.socket")
OPTIONAL_DIRECTORY = "/usr/local/libexec"
MAX_BYTES = 1024 * 1024
COMMANDS = (("verify-units", ("/usr/bin/systemd-analyze", "verify", *TARGETS[1:])),
            ("reload-systemd", ("/usr/bin/systemctl", "daemon-reload")),
            ("start-temporary-socket", ("/usr/bin/systemctl", "start", f"{NAME}.socket")))


class InstallBlocked(RuntimeError):
    """A closed diagnostic with no raw exception, private path or file content."""


def require(condition, diagnostic):
    if not condition:
        raise InstallBlocked(diagnostic)


def identity(value):
    return (value.st_dev, value.st_ino, value.st_uid, value.st_gid, value.st_mode,
            value.st_size, value.st_mtime_ns, value.st_ctime_ns)


def directory_identity(value):
    # Creating a child changes its parent's times and size, not its attachment.
    return identity(value)[:5]


def protected(value, *, directory=False, exact_mode=None):
    kind = stat.S_ISDIR if directory else stat.S_ISREG
    return (kind(value.st_mode) and value.st_uid == value.st_gid == 0 and
            not stat.S_IMODE(value.st_mode) & 0o7022 and
            (exact_mode is None or stat.S_IMODE(value.st_mode) == exact_mode))


def effective_properties_match(contents, fragment):
    """Accept only the reviewed unit fragment with no effective drop-in overrides."""
    try:
        require(len(contents) <= 8192, "effective-unit-configuration-unverified")
        lines = contents.decode("utf-8").splitlines()
        require(len(lines) == 2, "effective-unit-configuration-unverified")
        fields = {}
        for line in lines:
            key, value = line.split("=", 1)
            require(key in ("FragmentPath", "DropInPaths") and key not in fields,
                    "effective-unit-configuration-unverified")
            fields[key] = value
        return fields == {"FragmentPath": fragment, "DropInPaths": ""}
    except (InstallBlocked, UnicodeError, ValueError):
        return False


class Host:
    """Native fixed-path operations; the injectable adapter is only for tests."""

    def __init__(self):
        self.source_directory = os.path.dirname(os.path.abspath(__file__))

    def require_supported_root(self):
        require(all(hasattr(os, name) for name in
                    ("O_DIRECTORY", "O_NOFOLLOW", "O_CLOEXEC", "getuid", "geteuid", "fchown", "fchmod")),
                "posix-descriptor-operations-required")
        require(os.getuid() == os.geteuid() == 0, "installer-requires-root")

    def open_directory(self, name, parent):
        return os.open(name, os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC, dir_fd=parent)

    def open_file(self, name, parent, *, create=False):
        flags = os.O_WRONLY | os.O_CREAT | os.O_EXCL if create else os.O_RDONLY
        return os.open(name, flags | os.O_NOFOLLOW | os.O_CLOEXEC, 0o600, dir_fd=parent)

    close = staticmethod(os.close)
    lstat = staticmethod(os.lstat)
    fstat = staticmethod(os.fstat)
    realpath = staticmethod(os.path.realpath)
    read = staticmethod(os.read)
    write = staticmethod(os.write)
    fsync = staticmethod(os.fsync)
    fchmod = staticmethod(os.fchmod) if hasattr(os, "fchmod") else None
    fchown = staticmethod(os.fchown) if hasattr(os, "fchown") else None

    @staticmethod
    def relative_stat(name, parent):
        return os.stat(name, dir_fd=parent, follow_symlinks=False)

    @staticmethod
    def mkdir(name, parent):
        os.mkdir(name, 0o755, dir_fd=parent)

    @staticmethod
    def run(command):
        return subprocess.run(command, stdin=subprocess.DEVNULL, stdout=subprocess.DEVNULL,
                              stderr=subprocess.DEVNULL, timeout=30, check=False,
                              env={"PATH": "/usr/sbin:/usr/bin:/sbin:/bin", "LANG": "C", "LC_ALL": "C"}).returncode

    @staticmethod
    def effective_units_match():
        for fragment in TARGETS[1:]:
            command = ("/usr/bin/systemctl", "show", "--property=FragmentPath", "--property=DropInPaths",
                       PurePosixPath(fragment).name)
            result = subprocess.run(command, stdin=subprocess.DEVNULL, stdout=subprocess.PIPE,
                                    stderr=subprocess.DEVNULL, timeout=30, check=False,
                                    env={"PATH": "/usr/sbin:/usr/bin:/sbin:/bin", "LANG": "C", "LC_ALL": "C"})
            if result.returncode != 0 or not effective_properties_match(result.stdout, fragment):
                return False
        return True


class Installer:
    def __init__(self, host, stack):
        self.host, self.stack = host, stack
        self.directories, self.sources, self.targets = {}, {}, {}
        self.interpreter = None

    def pin_directory(self, path):
        if path in self.directories:
            return self.directories[path][0]
        require(str(PurePosixPath(path)) == path and path.startswith("/"), "directory-not-canonical")
        parent_path = str(PurePosixPath(path).parent)
        parent = None if path == "/" else self.pin_directory(parent_path)
        before = self.host.lstat(path)
        require(protected(before, directory=True), "directory-not-protected")
        require(self.host.realpath(path, strict=True) == path, "directory-not-canonical")
        name = "/" if parent is None else PurePosixPath(path).name
        descriptor = self.host.open_directory(name, parent)
        self.stack.callback(self.host.close, descriptor)
        self.directories[path] = (descriptor, parent, name, directory_identity(before))
        self.verify_directories()
        return descriptor

    def verify_directories(self):
        for path, (descriptor, parent, name, pinned) in self.directories.items():
            absolute = self.host.lstat(path)
            relative = self.host.relative_stat(name, parent)
            require(self.host.realpath(path, strict=True) == path and protected(absolute, directory=True) and
                    directory_identity(absolute) == directory_identity(relative) ==
                    directory_identity(self.host.fstat(descriptor)) == pinned, "directory-attachment-changed")

    def read_file(self, path, *, target=False):
        parent = self.pin_directory(str(PurePosixPath(path).parent))
        name = PurePosixPath(path).name
        before = self.host.lstat(path)
        require(protected(before, exact_mode=0o644 if target else None), "file-not-protected")
        require(self.host.realpath(path, strict=True) == path and
                identity(self.host.relative_stat(name, parent)) == identity(before), "file-attachment-changed")
        descriptor = self.host.open_file(name, parent)
        try:
            require(identity(self.host.fstat(descriptor)) == identity(before), "file-identity-changed")
            contents = bytearray()
            while len(contents) <= MAX_BYTES:
                chunk = self.host.read(descriptor, min(65536, MAX_BYTES + 1 - len(contents)))
                if not chunk:
                    break
                contents.extend(chunk)
            require(len(contents) <= MAX_BYTES, "file-too-large")
            require(identity(self.host.fstat(descriptor)) == identity(self.host.lstat(path)) ==
                    identity(self.host.relative_stat(name, parent)) == identity(before), "file-identity-changed")
            return bytes(contents), identity(before)
        finally:
            self.host.close(descriptor)

    def preflight(self, report):
        source_directory = self.host.source_directory
        self.pin_directory(source_directory)
        self.read_file(f"{source_directory}/{NAME}-install.py")
        self.interpreter = self.interpreter_identity()
        for target, entry in zip(TARGETS, report["targets"]):
            source = f"{source_directory}/{PurePosixPath(target).name}"
            contents, pinned = self.read_file(source)
            require(bool(contents), "source-empty")
            self.sources[source] = (contents, pinned)
            entry["sha256"] = hashlib.sha256(contents).hexdigest()
        for target, entry in zip(TARGETS, report["targets"]):
            parent = str(PurePosixPath(target).parent)
            try:
                self.pin_directory(parent)
            except FileNotFoundError:
                require(parent == OPTIONAL_DIRECTORY, "target-parent-missing")
                self.pin_directory(str(PurePosixPath(parent).parent))
            try:
                self.host.lstat(target)
            except FileNotFoundError:
                entry["state"] = "missing"
                continue
            current, pinned = self.read_file(target, target=True)
            expected = self.sources[f"{source_directory}/{PurePosixPath(target).name}"][0]
            require(current == expected, "existing-target-differs")
            self.targets[target] = pinned
            entry["state"] = "matching"
        self.verify_sources()

    def interpreter_identity(self):
        self.pin_directory(str(PurePosixPath(INTERPRETER).parent))
        alias = self.host.lstat(INTERPRETER)
        require(alias.st_uid == alias.st_gid == 0 and
                (stat.S_ISLNK(alias.st_mode) or protected(alias)), "interpreter-not-protected")
        resolved = self.host.realpath(INTERPRETER, strict=True)
        parent = self.pin_directory(str(PurePosixPath(resolved).parent))
        before = self.host.lstat(resolved)
        require(protected(before) and bool(before.st_mode & 0o111), "interpreter-not-protected")
        descriptor = self.host.open_file(PurePosixPath(resolved).name, parent)
        try:
            require(identity(self.host.fstat(descriptor)) == identity(self.host.lstat(resolved)) ==
                    identity(self.host.relative_stat(PurePosixPath(resolved).name, parent)) == identity(before) and
                    identity(self.host.lstat(INTERPRETER)) == identity(alias) and
                    self.host.realpath(INTERPRETER, strict=True) == resolved, "interpreter-identity-changed")
        finally:
            self.host.close(descriptor)
        return resolved, identity(before), identity(alias)

    def verify_sources(self):
        self.verify_directories()
        require(self.interpreter_identity() == self.interpreter, "interpreter-identity-changed")
        for path, (contents, pinned) in self.sources.items():
            current, observed = self.read_file(path)
            require(current == contents and observed == pinned, "source-identity-changed")

    def verify_targets(self):
        self.verify_sources()
        for target, pinned in self.targets.items():
            contents, observed = self.read_file(target, target=True)
            expected = self.sources[f"{self.host.source_directory}/{PurePosixPath(target).name}"][0]
            require(contents == expected and observed == pinned, "target-identity-changed")

    def create_target(self, target):
        parent = self.pin_directory(str(PurePosixPath(target).parent))
        contents = self.sources[f"{self.host.source_directory}/{PurePosixPath(target).name}"][0]
        descriptor = self.host.open_file(PurePosixPath(target).name, parent, create=True)
        try:
            self.host.fchown(descriptor, 0, 0)
            offset = 0
            while offset < len(contents):
                written = self.host.write(descriptor, contents[offset:])
                require(written > 0, "file-write-incomplete")
                offset += written
            self.host.fchmod(descriptor, 0o644)
            self.host.fsync(descriptor)
        finally:
            self.host.close(descriptor)
        actual, pinned = self.read_file(target, target=True)
        require(actual == contents, "created-target-differs")
        self.targets[target] = pinned


def install(*, apply=False, host=None):
    host = Host() if host is None else host
    report = {"result": "blocked", "diagnostic": "metadata-unavailable-or-operation-failed",
              "targets": [{"path": path, "state": None, "sha256": None} for path in TARGETS], "steps": []}
    attempted = False
    with ExitStack() as stack:
        try:
            host.require_supported_root()
            installer = Installer(host, stack)
            installer.preflight(report)
            if not apply:
                report.update(result="dry-run", diagnostic="reviewed-proposal-no-mutation")
                return report
            if OPTIONAL_DIRECTORY not in installer.directories:
                installer.verify_sources()
                attempted = True
                step = {"operation": "create-root-directory", "path": OPTIONAL_DIRECTORY, "result": "attempted"}
                report["steps"].append(step)
                parent = installer.pin_directory(str(PurePosixPath(OPTIONAL_DIRECTORY).parent))
                host.mkdir(PurePosixPath(OPTIONAL_DIRECTORY).name, parent)
                descriptor = installer.pin_directory(OPTIONAL_DIRECTORY)
                host.fchown(descriptor, 0, 0)
                host.fchmod(descriptor, 0o755)
                node = installer.directories[OPTIONAL_DIRECTORY]
                changed = directory_identity(host.fstat(descriptor))
                require(changed[:4] == node[3][:4] and changed[4] == stat.S_IFDIR | 0o755,
                        "created-directory-identity-changed")
                installer.directories[OPTIONAL_DIRECTORY] = (*node[:3], changed)
                installer.verify_directories()
                step["result"] = "completed"
            for target, entry in zip(TARGETS, report["targets"]):
                installer.verify_targets()
                if entry["state"] == "matching":
                    continue
                attempted = True
                step = {"operation": "create-exclusive-root-file", "path": target, "result": "attempted"}
                report["steps"].append(step)
                installer.create_target(target)
                entry["state"] = "created"
                step["result"] = "completed"
            for operation, command in COMMANDS:
                installer.verify_targets()
                attempted = attempted or operation != "verify-units"
                step = {"operation": operation, "result": "attempted"}
                report["steps"].append(step)
                require(host.run(command) == 0, f"{operation}-failed")
                installer.verify_targets()
                step["result"] = "completed"
                if operation == "reload-systemd":
                    step = {"operation": "verify-effective-units", "result": "attempted"}
                    report["steps"].append(step)
                    require(host.effective_units_match(), "effective-unit-configuration-unverified")
                    installer.verify_targets()
                    step["result"] = "completed"
            report.update(result="complete", diagnostic="reader-installed-socket-started-temporarily")
        except InstallBlocked as error:
            report["diagnostic"] = str(error)
        except (OSError, ValueError, TypeError, AttributeError, subprocess.SubprocessError):
            pass
        if report["result"] == "blocked" and attempted:
            report["result"] = "partial"
    return report


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true", help="explicitly install files and start the temporary socket as root")
    args = parser.parse_args(argv)
    report = install(apply=args.apply)
    print(json.dumps(report, sort_keys=True))
    return 0 if report["result"] in ("dry-run", "complete") else 78


if __name__ == "__main__":
    raise SystemExit(main())
