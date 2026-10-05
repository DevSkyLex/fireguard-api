#!/usr/bin/env python3
"""Fixed, read-only process metadata service for the reviewed deployment recovery.

The root service accepts only its systemd-activated Unix socket. SO_PEERCRED, not
request data, identifies the deployment caller. Only status/stat metadata and
cwd/fd symlink targets are read; target files, arguments and environments are
never opened. Returned paths stay on the private socket, never in diagnostics.
"""

from enum import Enum
import errno
import hashlib
import json
import os
from pathlib import Path
import re
import secrets
import socket
import stat
import struct
import sys
import time


SOCKET_PATH = "/run/fireguard-deployment-process-reader.sock"
INSTALLED_PATH = "/usr/local/libexec/fireguard-deployment-process-reader.py"
DEPLOYMENT_UID = 1001
VERSION = 1
MAX_PROCESSES = 32768
MAX_GROUPS = 65536
MAX_FDS = 65536
MAX_TOTAL_FDS = 262144
MAX_REQUEST = 1024
MAX_RESPONSE = 16 * 1024 * 1024
COLLECTION_SECONDS = 20
SOCKET_SECONDS = 30
READER_CAPABILITIES = (1 << 19) | (1 << 2)  # SYS_PTRACE and DAC_READ_SEARCH only.
HEX = re.compile(r"[a-f0-9]{64}\Z")
RECORD_KEYS = {"pid", "ppid", "uid", "uids", "gids", "groups", "kernelThread", "comm", "cwd", "fds", "startTime"}
_SOURCE_DIGEST = None


class ErrorCode(str, Enum):
    IDENTITY = "reader-identity-unverified"
    SOCKET = "socket-identity-unverified"
    ACTIVATION = "socket-activation-unverified"
    PEER = "peer-identity-unverified"
    REQUEST = "request-unverified"
    METADATA = "metadata-unavailable"
    RACE = "metadata-raced"
    CHANGED = "identity-changed"
    CALLER_CHANGED = "caller-identity-changed"
    ANCESTOR_CHANGED = "ancestor-identity-changed"
    AUTHORITY_CHANGED = "authority-identity-changed"
    FOREIGN_CHANGED = "foreign-credentials-changed"
    INVENTORY = "inventory-changed"
    LIMIT = "bounded-limit"
    RESPONSE = "response-unverified"
    UNAVAILABLE = "reader-unavailable"


class ReaderBlocked(RuntimeError):
    """Only an enumerated diagnostic is allowed outside the private protocol."""

    def __init__(self, code):
        self.code = ErrorCode(code)
        super().__init__(self.code.value)


def _require(condition, code):
    if not condition:
        raise ReaderBlocked(code)


def _number(value, *, positive=False):
    return type(value) is int and (value > 0 if positive else value >= 0) and value <= 2**64 - 1


def _text(path, limit):
    try:
        with path.open("r", encoding="utf-8") as source:
            value = source.read(limit + 1)
        _require(len(value) <= limit, ErrorCode.LIMIT)
        return value
    except (OSError, UnicodeError):
        raise ReaderBlocked(ErrorCode.METADATA) from None


def _gone(path):
    try:
        path.lstat()
        return False
    except OSError as error:
        _require(error.errno in (errno.ENOENT, errno.ESRCH), ErrorCode.METADATA)
        return True


def _stat_identity(path, pid):
    value = _text(path / "stat", 16384)
    boundary = value.rfind(")")
    _require(boundary > 0 and value.split(" ", 1)[0] == str(pid), ErrorCode.METADATA)
    fields = value[boundary + 1:].split()
    _require(len(fields) >= 20 and fields[1].isdecimal() and fields[19].isdecimal(), ErrorCode.METADATA)
    return int(fields[1]), int(fields[19])


def _identity(proc, pid):
    """Bracket credentials with starttime so reused numeric PIDs are rejected."""
    path = proc / str(pid)
    before = _stat_identity(path, pid)
    fields = {}
    for line in _text(path / "status", 512 * 1024).splitlines():
        key, separator, value = line.partition(":")
        if separator and key in {"Name", "PPid", "Uid", "Gid", "Groups", "Kthread"}:
            _require(key not in fields, ErrorCode.METADATA)
            fields[key] = value.strip()
    _require({"Name", "PPid", "Uid", "Gid", "Groups"} <= fields.keys(), ErrorCode.METADATA)
    try:
        credentials = {key: [int(value) for value in fields[source].split()]
                       for key, source in (("uids", "Uid"), ("gids", "Gid"), ("groups", "Groups"))}
        ppid = int(fields["PPid"])
    except ValueError:
        raise ReaderBlocked(ErrorCode.METADATA) from None
    _require(len(credentials["uids"]) == len(credentials["gids"]) == 4
             and len(credentials["groups"]) <= MAX_GROUPS
             and all(_number(value) for values in credentials.values() for value in values)
             and _number(ppid), ErrorCode.METADATA)
    _require(fields.get("Kthread", "0") in {"0", "1"}
             and 0 < len(fields["Name"]) <= 256 and "\x00" not in fields["Name"], ErrorCode.METADATA)
    after = _stat_identity(path, pid)
    _require(before == after and before[0] == ppid, ErrorCode.CHANGED)
    return {"pid": pid, "ppid": ppid, "uid": credentials["uids"][0], **credentials,
            "kernelThread": fields.get("Kthread", "0") == "1", "comm": fields["Name"],
            "cwd": "", "fds": [], "startTime": before[1]}


def _inventory(proc):
    try:
        identifiers = []
        for path in proc.iterdir():
            if path.name.isdecimal():
                identifiers.append(int(path.name))
                _require(len(identifiers) <= MAX_PROCESSES, ErrorCode.LIMIT)
    except OSError:
        raise ReaderBlocked(ErrorCode.METADATA) from None
    _require(bool(identifiers) and len(set(identifiers)) == len(identifiers)
             and all(_number(pid, positive=True) for pid in identifiers), ErrorCode.METADATA)
    return sorted(identifiers)


def _ancestors(records, pid):
    by_pid = {record["pid"]: record for record in records}
    ancestors = set()
    while pid:
        _require(pid in by_pid and pid not in ancestors, ErrorCode.CHANGED)
        ancestors.add(pid)
        pid = by_pid[pid]["ppid"]
    return ancestors


def _credentials(record):
    return {key: value for key, value in record.items() if key not in {"cwd", "fds"}}


def _refresh_identity(record, current, ancestors, reserve, caller_pid):
    """Foreign exec/reparenting has no owner-policy meaning; identity still must match."""
    identity = {"pid", "startTime", "uid", "uids", "gids", "groups", "kernelThread"}
    authority = any(value in record[key] for value in (1000, DEPLOYMENT_UID) for key in ("uids", "gids", "groups"))
    code = (ErrorCode.CALLER_CHANGED if record["pid"] == caller_pid else
            ErrorCode.ANCESTOR_CHANGED if record["pid"] in ancestors else
            ErrorCode.AUTHORITY_CHANGED if authority else ErrorCode.FOREIGN_CHANGED)
    _require(all(record[key] == current[key] for key in identity), code)
    protected = record["pid"] in ancestors or authority
    if protected:
        _require(_credentials(record) == _credentials(current), code)
        return
    for key in ("ppid", "comm"):
        if record[key] != current[key]:
            reserve({key: current[key]})
            record[key] = current[key]


def _link(path):
    value = os.readlink(path)
    _require(type(value) is str and 0 < len(value) <= 16384 and "\x00" not in value, ErrorCode.METADATA)
    return value


def collect_snapshot(caller_pid, caller_uid, *, proc=Path("/proc")):
    """Internal proc-root adapter is for hermetic tests; production is fixed /proc."""
    _require(caller_uid == DEPLOYMENT_UID and _number(caller_pid, positive=True), ErrorCode.PEER)
    deadline = time.monotonic() + COLLECTION_SECONDS

    def bounded():
        _require(time.monotonic() <= deadline, ErrorCode.LIMIT)

    caller = _identity(proc, caller_pid)
    _require(caller["uids"] == [DEPLOYMENT_UID] * 4, ErrorCode.PEER)
    initial_pids = _inventory(proc)
    records = []
    budget = 1024  # More than the fixed response envelope, caller and punctuation.

    def reserve(value):
        nonlocal budget
        budget += len(json.dumps(value, separators=(",", ":"), ensure_ascii=True).encode("ascii")) + 1
        _require(budget <= MAX_RESPONSE, ErrorCode.LIMIT)

    for pid in initial_pids:
        bounded()
        try:
            record = _identity(proc, pid)
            reserve(record)
            records.append(record)
        except ReaderBlocked as error:
            if error.code == ErrorCode.LIMIT:
                raise
            if not _gone(proc / str(pid)):
                raise
    ancestors = _ancestors(records, caller_pid)
    _require(_credentials(next(record for record in records if record["pid"] == caller_pid))
             == _credentials(caller), ErrorCode.CALLER_CHANGED)
    total_fds = 0

    def inspect(record):
        nonlocal total_fds
        bounded()
        path = proc / str(record["pid"])
        try:
            if record["uid"] == DEPLOYMENT_UID and record["pid"] not in ancestors and not record["kernelThread"]:
                record["cwd"] = _link(path / "cwd")
                reserve(record["cwd"])
                descriptors = []
                for descriptor in (path / "fd").iterdir():
                    _require(descriptor.name.isdecimal(), ErrorCode.METADATA)
                    descriptors.append(descriptor)
                    total_fds += 1
                    _require(len(descriptors) <= MAX_FDS and total_fds <= MAX_TOTAL_FDS, ErrorCode.LIMIT)
                    bounded()
                for descriptor in descriptors:
                    bounded()
                    value = _link(descriptor)
                    reserve(value)
                    record["fds"].append(value)
            _refresh_identity(record, _identity(proc, record["pid"]), ancestors, reserve, caller_pid)
            return record
        except ReaderBlocked as error:
            if error.code == ErrorCode.LIMIT:
                raise
            if not _gone(path):
                raise
        except OSError:
            if not _gone(path):
                raise ReaderBlocked(ErrorCode.RACE) from None
        return None

    selected = {record["pid"]: value for record in records if (value := inspect(record)) is not None}
    known = set(initial_pids)
    # Include newly born processes rather than silently omitting their authority.
    # Four bounded passes accommodate normal host churn; continued churn denies.
    for _ in range(4):
        bounded()
        current = set(_inventory(proc))
        for pid in sorted(current - selected.keys()):
            bounded()
            try:
                record = _identity(proc, pid)
                reserve(record)
                value = inspect(record)
                if value is not None:
                    selected[pid] = value
            except ReaderBlocked as error:
                if error.code == ErrorCode.LIMIT:
                    raise
                if not _gone(proc / str(pid)):
                    raise
        known |= current
        _require(len(known) <= MAX_PROCESSES, ErrorCode.LIMIT)
        for pid, record in list(selected.items()):
            bounded()
            try:
                _refresh_identity(record, _identity(proc, pid), ancestors, reserve, caller_pid)
            except ReaderBlocked as error:
                if error.code == ErrorCode.LIMIT:
                    raise
                if not _gone(proc / str(pid)):
                    raise
                del selected[pid]
        _require(_credentials(_identity(proc, caller_pid)) == _credentials(caller), ErrorCode.CALLER_CHANGED)
        if set(_inventory(proc)) <= selected.keys():
            result = [selected[pid] for pid in sorted(selected)]
            _ancestors(result, caller_pid)
            return caller, result
    raise ReaderBlocked(ErrorCode.INVENTORY)


def _object(pairs):
    value = {}
    for key, item in pairs:
        _require(key not in value, ErrorCode.RESPONSE)
        value[key] = item
    return value


def _receive(connection, limit, *, deadline=None):
    data = bytearray()
    deadline = time.monotonic() + SOCKET_SECONDS if deadline is None else deadline
    try:
        while True:
            remaining = deadline - time.monotonic()
            _require(remaining > 0, ErrorCode.LIMIT)
            connection.settimeout(remaining)
            chunk = connection.recv(min(8192, limit + 1 - len(data)))
            if not chunk:
                break
            data.extend(chunk)
            _require(len(data) <= limit, ErrorCode.LIMIT)
        value = json.loads(data.decode("utf-8"), object_pairs_hook=_object)
    except (OSError, UnicodeError, ValueError, RecursionError):
        raise ReaderBlocked(ErrorCode.RESPONSE) from None
    _require(type(value) is dict, ErrorCode.RESPONSE)
    return value


def _send(connection, value, limit):
    data = json.dumps(value, separators=(",", ":"), ensure_ascii=True).encode("ascii")
    _require(len(data) <= limit, ErrorCode.LIMIT)
    connection.sendall(data)


def _peer(connection):
    try:
        raw = connection.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, struct.calcsize("3i"))
        pid, uid, gid = struct.unpack("3i", raw)
    except (OSError, AttributeError, struct.error):
        raise ReaderBlocked(ErrorCode.PEER) from None
    _require(_number(pid, positive=True) and _number(uid) and _number(gid), ErrorCode.PEER)
    return pid, uid, gid


def source_digest():
    global _SOURCE_DIGEST
    if _SOURCE_DIGEST is not None:
        return _SOURCE_DIGEST
    try:
        source = Path(__file__).read_bytes()
    except OSError:
        raise ReaderBlocked(ErrorCode.IDENTITY) from None
    _require(0 < len(source) <= 1024 * 1024, ErrorCode.LIMIT)
    _SOURCE_DIGEST = hashlib.sha256(source).hexdigest()
    return _SOURCE_DIGEST


def _handle(connection):
    """A request cannot choose PID, path, command, proc root or scope."""
    nonce = ""
    try:
        connection.settimeout(SOCKET_SECONDS)
        pid, uid, _ = _peer(connection)
        _require(uid == DEPLOYMENT_UID, ErrorCode.PEER)
        request = _receive(connection, MAX_REQUEST)
        _require(set(request) == {"version", "nonce"} and type(request["version"]) is int
                 and request["version"] == VERSION and type(request["nonce"]) is str
                 and HEX.fullmatch(request["nonce"]), ErrorCode.REQUEST)
        nonce = request["nonce"]
        caller, records = collect_snapshot(pid, uid)
        _send(connection, {"version": VERSION, "nonce": nonce, "sourceSha256": source_digest(),
                           "caller": {"pid": pid, "uid": uid, "startTime": caller["startTime"]},
                           "records": records}, MAX_RESPONSE)
        return None
    except ReaderBlocked as error:
        code = error.code
    except (OSError, ValueError, TypeError, KeyError, AttributeError):
        code = ErrorCode.UNAVAILABLE
    try:
        _send(connection, {"version": VERSION, "nonce": nonce, "error": code.value}, MAX_REQUEST)
    except (OSError, ReaderBlocked):
        pass
    return code


def _validate_response(value, nonce, caller, digest):
    _require(type(value) is dict and type(value.get("version")) is int
             and value["version"] == VERSION and value.get("nonce") == nonce, ErrorCode.RESPONSE)
    if set(value) == {"version", "nonce", "error"}:
        try:
            code = ErrorCode(value["error"])
        except (ValueError, TypeError):
            raise ReaderBlocked(ErrorCode.RESPONSE) from None
        raise ReaderBlocked(code)
    _require(set(value) == {"version", "nonce", "sourceSha256", "caller", "records"}
             and value["sourceSha256"] == digest
             and value["caller"] == {"pid": caller["pid"], "uid": DEPLOYMENT_UID, "startTime": caller["startTime"]},
             ErrorCode.RESPONSE)
    records = value["records"]
    _require(type(records) is list and 0 < len(records) <= MAX_PROCESSES, ErrorCode.RESPONSE)
    seen = set()
    total_fds = 0
    for record in records:
        _require(type(record) is dict and set(record) == RECORD_KEYS, ErrorCode.RESPONSE)
        _require(_number(record["pid"], positive=True) and record["pid"] not in seen
                 and _number(record["ppid"]) and _number(record["uid"])
                 and _number(record["startTime"]), ErrorCode.RESPONSE)
        seen.add(record["pid"])
        _require(all(type(record[key]) is list and len(record[key]) <= MAX_GROUPS
                     and all(_number(item) for item in record[key]) for key in ("uids", "gids", "groups"))
                 and len(record["uids"]) == len(record["gids"]) == 4
                 and record["uid"] == record["uids"][0], ErrorCode.RESPONSE)
        _require(type(record["kernelThread"]) is bool and type(record["comm"]) is str
                 and 0 < len(record["comm"]) <= 256 and "\x00" not in record["comm"]
                 and type(record["cwd"]) is str and len(record["cwd"]) <= 16384
                 and "\x00" not in record["cwd"] and type(record["fds"]) is list
                 and len(record["fds"]) <= MAX_FDS
                 and all(type(item) is str and 0 < len(item) <= 16384 and "\x00" not in item for item in record["fds"]),
                 ErrorCode.RESPONSE)
        total_fds += len(record["fds"])
        _require(total_fds <= MAX_TOTAL_FDS, ErrorCode.LIMIT)
    ancestors = _ancestors(records, caller["pid"])
    _require(_credentials(next(record for record in records if record["pid"] == caller["pid"]))
             == _credentials(caller), ErrorCode.CHANGED)
    for record in records:
        selected = record["uid"] == DEPLOYMENT_UID and record["pid"] not in ancestors and not record["kernelThread"]
        _require(bool(record["cwd"]) if selected else record["cwd"] == "" and record["fds"] == [], ErrorCode.RESPONSE)
    return records


def _protected_node(path, *, directory):
    try:
        node = path.lstat()
    except OSError:
        raise ReaderBlocked(ErrorCode.SOCKET) from None
    _require(not stat.S_ISLNK(node.st_mode) and node.st_uid == 0 and not node.st_mode & 0o022
             and (stat.S_ISDIR(node.st_mode) if directory else stat.S_ISREG(node.st_mode)), ErrorCode.SOCKET)
    return node


def _service_security():
    fields = {}
    selected = {"NoNewPrivs", "CapEff", "CapPrm", "CapBnd", "CapInh", "CapAmb"}
    for line in _text(Path("/proc") / str(os.getpid()) / "status", 512 * 1024).splitlines():
        key, separator, value = line.partition(":")
        if separator and key in selected:
            _require(key not in fields, ErrorCode.IDENTITY)
            fields[key] = value.strip()
    _require(fields.keys() == selected and fields["NoNewPrivs"] == "1", ErrorCode.IDENTITY)
    for key in selected - {"NoNewPrivs"}:
        _require(re.fullmatch(r"[a-fA-F0-9]{1,16}", fields[key]), ErrorCode.IDENTITY)
        value = int(fields[key], 16)
        _require(value == READER_CAPABILITIES if key in {"CapEff", "CapPrm", "CapBnd"}
                 else value & ~READER_CAPABILITIES == 0, ErrorCode.IDENTITY)


def _protected_parents(path):
    _require(path.is_absolute(), ErrorCode.SOCKET)
    for parent in reversed(path.parents):
        _protected_node(parent, directory=True)


def _socket_identity():
    path = Path(SOCKET_PATH)
    _protected_parents(path)
    try:
        node = path.lstat()
    except OSError:
        raise ReaderBlocked(ErrorCode.SOCKET) from None
    _require(stat.S_ISSOCK(node.st_mode) and node.st_uid == 0 and node.st_gid == DEPLOYMENT_UID
             and stat.S_IMODE(node.st_mode) == 0o660, ErrorCode.SOCKET)
    return node.st_dev, node.st_ino, node.st_ctime_ns


def _exchange(connection, caller, digest):
    deadline = time.monotonic() + SOCKET_SECONDS
    connection.settimeout(SOCKET_SECONDS)
    _, uid, gid = _peer(connection)
    # systemd created the listener: its peer PID may be PID 1 rather than Python.
    _require(uid == gid == 0, ErrorCode.PEER)
    nonce = secrets.token_hex(32)
    _send(connection, {"version": VERSION, "nonce": nonce}, MAX_REQUEST)
    connection.shutdown(socket.SHUT_WR)
    return _validate_response(_receive(connection, MAX_RESPONSE, deadline=deadline), nonce, caller, digest)


def read_snapshot(pid, uid):
    """Fixed endpoint only; caller arguments must be the current process identity."""
    _require(pid == os.getpid() and uid == os.getuid() == os.geteuid() == DEPLOYMENT_UID, ErrorCode.PEER)
    caller = _identity(Path("/proc"), pid)
    _require(caller["uids"] == [DEPLOYMENT_UID] * 4, ErrorCode.PEER)
    before = _socket_identity()
    try:
        with socket.socket(socket.AF_UNIX, socket.SOCK_STREAM) as connection:
            connection.settimeout(SOCKET_SECONDS)
            connection.connect(SOCKET_PATH)
            records = _exchange(connection, caller, source_digest())
    except (OSError, AttributeError):
        raise ReaderBlocked(ErrorCode.UNAVAILABLE) from None
    _require(_socket_identity() == before
             and _credentials(_identity(Path("/proc"), pid)) == _credentials(caller), ErrorCode.CHANGED)
    return records


def activated_socket():
    _require(os.getuid() == os.geteuid() == os.getgid() == os.getegid() == 0
             and DEPLOYMENT_UID not in os.getgroups(), ErrorCode.IDENTITY)
    identity = _identity(Path("/proc"), os.getpid())
    _require(identity["uids"] == identity["gids"] == [0] * 4
             and DEPLOYMENT_UID not in identity["groups"], ErrorCode.IDENTITY)
    _service_security()
    path = Path(__file__)
    _require(str(path) == INSTALLED_PATH, ErrorCode.IDENTITY)
    _protected_parents(path)
    _protected_node(path, directory=False)
    source_digest()  # Attest the code loaded by this service, including after replacement.
    _require(os.environ.get("LISTEN_PID") == str(os.getpid())
             and os.environ.get("LISTEN_FDS") == "1", ErrorCode.ACTIVATION)
    _socket_identity()
    try:
        listener = socket.socket(fileno=os.dup(3))
        _require(listener.family == socket.AF_UNIX and listener.type == socket.SOCK_STREAM
                 and listener.getsockname() == SOCKET_PATH
                 and listener.getsockopt(socket.SOL_SOCKET, socket.SO_ACCEPTCONN) == 1, ErrorCode.ACTIVATION)
        return listener
    except (OSError, ValueError):
        raise ReaderBlocked(ErrorCode.ACTIVATION) from None


def main():
    try:
        _require(len(sys.argv) == 1, ErrorCode.ACTIVATION)
        with activated_socket() as listener:
            while True:
                with listener.accept()[0] as connection:
                    code = _handle(connection)
                if code is not None:
                    print(code.value, file=sys.stderr, flush=True)
    except (ReaderBlocked, OSError) as error:
        code = error.code if isinstance(error, ReaderBlocked) else ErrorCode.UNAVAILABLE
        print(code.value, file=sys.stderr, flush=True)
        return 78
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
