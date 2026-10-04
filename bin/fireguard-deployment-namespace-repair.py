#!/usr/bin/env python3
"""Manually protect three reviewed namespace directories; dry-run unless --apply.

Only /srv, /srv/apps and /srv/apps/fireguard may change, to root:root 0755.
The original empty lock and every other directory are read-only. Operations are
sequential, not atomic: an interrupted repair is reported and never rolled back.
"""

import argparse
from contextlib import ExitStack
import importlib.util
import json
import os
from pathlib import Path
import stat


PATHS = ("/", "/srv", "/srv/apps", "/srv/apps/fireguard",
         "/srv/apps/fireguard/development", "/srv/apps/fireguard/development/back")
TARGETS = PATHS[1:4]
LOCK = PATHS[-1] + "/.fireguard-operation.lock"
FIELDS = ("device", "inode", "uid", "gid", "mode", "mtimeNs", "ctimeNs")


class RepairBlocked(RuntimeError):
    """A fixed public diagnostic, without exception text or filesystem contents."""


def require(condition, diagnostic):
    if not condition:
        raise RepairBlocked(diagnostic)


def metadata(value):
    return {"device": value.st_dev, "inode": value.st_ino,
            "uid": value.st_uid, "gid": value.st_gid, "mode": stat.S_IMODE(value.st_mode),
            "mtimeNs": value.st_mtime_ns, "ctimeNs": value.st_ctime_ns,
            "isDirectory": stat.S_ISDIR(value.st_mode), "isSymlink": stat.S_ISLNK(value.st_mode)}


def source_identity(value):
    return (value.st_dev, value.st_ino, value.st_uid, value.st_gid, value.st_mode,
            value.st_size, value.st_mtime_ns, value.st_ctime_ns)


def reviewed_constants():
    """Import the sibling public helper's actual source, without cached bytecode."""
    source = Path(os.path.abspath(__file__)).with_name("fireguard-deployment-recovery.py")
    require(os.path.realpath(source, strict=True) == str(source), "helper-source-not-canonical")
    before = os.lstat(source)
    require(stat.S_ISREG(before.st_mode) and not stat.S_ISLNK(before.st_mode), "helper-source-not-regular")
    descriptor = os.open(source, os.O_RDONLY | os.O_NOFOLLOW | os.O_CLOEXEC)
    with os.fdopen(descriptor, "rb") as stream:
        require(source_identity(os.fstat(stream.fileno())) == source_identity(before), "helper-source-identity-changed")
        contents = stream.read(1024 * 1024 + 1)
        require(len(contents) <= 1024 * 1024, "helper-source-too-large")
        require(source_identity(os.fstat(stream.fileno())) == source_identity(before) and
                source_identity(os.lstat(source)) == source_identity(before),
                "helper-source-identity-changed")
    spec = importlib.util.spec_from_file_location("_reviewed_deployment_repair_constants", source)
    require(spec is not None, "helper-source-unavailable")
    module = importlib.util.module_from_spec(spec)
    exec(compile(contents, str(source), "exec"), module.__dict__)
    namespace = [dict(node) for node in module.REVIEWED_NAMESPACE]
    lock = dict(module.REVIEWED_LEGACY_LOCK)
    require(len(namespace) == len(PATHS) and
            [node["depth"] for node in namespace] == list(range(len(PATHS))),
            "reviewed-namespace-shape-invalid")
    return {path: namespace[len(PATHS) - 1 - index] for index, path in enumerate(PATHS)}, lock


def state(path, observed, reviewed):
    require(observed["isDirectory"] and not observed["isSymlink"], "namespace-not-directory")
    require(all(observed[key] == reviewed[key] for key in ("device", "inode", "mtimeNs")),
            "namespace-identity-changed")
    if all(observed[key] == reviewed[key] for key in FIELDS):
        result = "historical"
    elif path in TARGETS and (observed["uid"], observed["gid"], observed["mode"]) == (0, 0, 0o755):
        result = "protected"
    elif path == TARGETS[-1] and (observed["uid"], observed["gid"], observed["mode"]) == (1000, 1000, 0o755):
        result = "chmod-completed-ownership-pending"
    else:
        raise RepairBlocked("namespace-state-not-reviewed")
    return result


class Namespace:
    """Pinned, non-following descriptors with path attachment checks at each step."""

    def __init__(self, stack, reviewed, lock):
        self.reviewed = reviewed
        self.lock = lock
        self.nodes = {}
        flags = os.O_RDONLY | os.O_DIRECTORY | os.O_NOFOLLOW | os.O_CLOEXEC
        parent = None
        for path in (*PATHS, LOCK):
            name = "/" if parent is None else os.path.basename(path)
            descriptor = os.open(name, flags, dir_fd=parent)
            stack.callback(os.close, descriptor)
            self.nodes[path] = (descriptor, parent, name)
            parent = descriptor

    def attached_metadata(self, path):
        descriptor, parent, name = self.nodes[path]
        require(os.path.realpath(path, strict=True) == path, "namespace-not-canonical")
        absolute = metadata(os.lstat(path))
        relative = metadata(os.stat(name, dir_fd=parent, follow_symlinks=False))
        pinned = metadata(os.fstat(descriptor))
        require(absolute == relative == pinned, "namespace-attachment-changed")
        return absolute

    def verify(self, *, final=False):
        observed = {}
        for path in PATHS:
            observed[path] = self.attached_metadata(path)
            current = state(path, observed[path], self.reviewed[path])
            require(not final or path not in TARGETS or current == "protected", "namespace-repair-incomplete")
        lock = self.attached_metadata(LOCK)
        require(lock["isDirectory"] and not lock["isSymlink"] and
                all(lock[key] == self.lock[key] for key in FIELDS), "original-lock-identity-changed")
        with os.scandir(self.nodes[LOCK][0]) as entries:
            require(next(entries, None) is None, "original-lock-not-empty")
        require(self.attached_metadata(LOCK) == lock, "original-lock-identity-changed")
        return observed

    def report_after(self, report):
        for target in report["targets"]:
            try:
                value = self.attached_metadata(target["path"])
                state(target["path"], value, self.reviewed[target["path"]])
                target["afterLstat"] = value
            except (RepairBlocked, OSError):
                target["afterLstat"] = None


def repair(*, apply=False):
    report = {"result": "blocked", "diagnostic": "metadata-unavailable-or-operation-failed",
              "targets": [{"path": path, "desired": {"uid": 0, "gid": 0, "mode": 0o755},
                           "state": None, "beforeLstat": None, "afterLstat": None} for path in TARGETS],
              "steps": []}
    attempted = False
    namespace = None
    with ExitStack() as stack:
        try:
            require(all(hasattr(os, name) for name in
                        ("O_DIRECTORY", "O_NOFOLLOW", "O_CLOEXEC", "getuid", "geteuid", "fchmod", "fchown")),
                    "posix-descriptor-operations-required")
            require(not apply or os.getuid() == os.geteuid() == 0, "apply-requires-root")
            reviewed, lock = reviewed_constants()
            namespace = Namespace(stack, reviewed, lock)
            initial = namespace.verify()
            for target in report["targets"]:
                target["beforeLstat"] = initial[target["path"]]
                target["state"] = state(target["path"], initial[target["path"]], reviewed[target["path"]])
            if apply:
                for path in TARGETS:
                    current = namespace.verify()[path]
                    # Remove group write before assigning group 0, including after a partial run.
                    if current["mode"] != 0o755:
                        attempted = True
                        os.fchmod(namespace.nodes[path][0], 0o755)
                        report["steps"].append({"path": path, "operation": "fchmod-0755", "result": "completed"})
                        namespace.verify()
                    current = namespace.verify()[path]
                    if (current["uid"], current["gid"]) != (0, 0):
                        attempted = True
                        os.fchown(namespace.nodes[path][0], 0, 0)
                        report["steps"].append({"path": path, "operation": "fchown-root-root", "result": "completed"})
                        namespace.verify()
            final = namespace.verify(final=apply)
            for target in report["targets"]:
                target["afterLstat"] = final[target["path"]]
            report["result"] = "complete" if apply else "dry-run"
            report["diagnostic"] = "namespace-protected-original-lock-unchanged" if apply else "reviewed-proposal-no-mutation"
        except RepairBlocked as error:
            report["diagnostic"] = str(error)
        except (OSError, ValueError, TypeError, KeyError, AttributeError, ImportError, SyntaxError):
            pass
        finally:
            if report["result"] == "blocked" and attempted:
                report["result"] = "partial"
            if namespace is not None and report["result"] in ("blocked", "partial"):
                namespace.report_after(report)
    return report


def main(argv=None):
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--apply", action="store_true", help="explicitly apply the reviewed metadata changes as root")
    args = parser.parse_args(argv)
    result = repair(apply=args.apply)
    print(json.dumps(result, sort_keys=True))
    return 0 if result["result"] in ("dry-run", "complete") else 78


if __name__ == "__main__":
    raise SystemExit(main())
