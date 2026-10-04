#!/usr/bin/env python3
"""Provisional pure proof for authority peers isolated by trusted Docker/kernel metadata.

This module neither collects evidence nor releases a deployment lock. A trusted caller
must provide two complete, independently collected snapshots immediately around its
decision, canonical physical paths, and the expected named-volume mount policy. The
explicit proof mode trusts the existing rootful Docker/runc/kernel boundary, rather
than pretending inaccessible namespace inode metadata or empty UsernsMode proves it.
Process snapshots include every thread, including threads whose credentials differ from their
leader. Empty Docker UsernsMode is deliberately not evidence of a private user namespace.

Only fixed diagnostic enums leave the validator. Docker option values, paths, process
metadata and container identifiers are never included in its result or exceptions.
"""

from dataclasses import dataclass
from datetime import datetime, timezone
from enum import Enum
from functools import wraps
import posixpath
import re
from typing import Any


class IsolationCode(str, Enum):
    """Public denial reasons; none contains a value supplied by the caller."""

    ALLOWED = "isolated"
    INVALID_SNAPSHOT = "invalid-snapshot"
    INCOMPLETE_INVENTORY = "incomplete-inventory"
    IDENTITY_CHANGED = "identity-changed"
    UNKNOWN_AUTHORITY_PEER = "unknown-authority-peer"
    AMBIGUOUS_OWNERSHIP = "ambiguous-ownership"
    INVALID_ANCESTRY = "invalid-ancestry"
    UNSAFE_CONTAINER = "unsafe-container"
    UNSAFE_PROCESS = "unsafe-process"
    UNSAFE_MOUNT = "unsafe-mount"
    UNSAFE_VOLUME = "unsafe-volume"


class IsolationPhase(str, Enum):
    NONE = "none"
    POLICY = "policy"
    BEFORE = "before"
    AFTER = "after"
    PROVE_BEFORE = "prove-before"
    PROVE_AFTER = "prove-after"
    COMPARE = "compare"


class IsolationSection(str, Enum):
    NONE = "none"
    POLICY = "policy"
    SNAPSHOT = "snapshot"
    PROCESS = "process"
    TASK = "task"
    CONTAINER = "container"
    VOLUME = "volume"
    ANCESTRY = "ancestry"
    MOUNT = "mount"
    COHORT = "cohort"


class IsolationPredicate(str, Enum):
    """Closed predicates only: no failed key, value or exception text is exported."""

    NONE = "none"
    OBJECT = "object-shape"
    ARRAY = "array-shape"
    INTEGER = "integer-shape"
    IDENTITY = "identity-uint32"
    PATH = "canonical-path"
    MISSING_FIELD = "missing-field"
    FIELD_TYPE = "field-type"
    FIELD_VALUE = "field-value"
    ITERATION = "iteration-shape"
    DEPTH = "metadata-depth"
    VALIDATION = "validation"
    POLICY_UID = "policy-authority-uid"
    POLICY_GIDS = "policy-authority-gids"
    POLICY_PID = "policy-current-pid"
    POLICY_CURRENT_UID = "policy-current-uid"
    POLICY_PATHS = "policy-paths"
    POLICY_MOUNT_NAME = "policy-mount-name"
    POLICY_MOUNT_DESTINATION = "policy-mount-destination"
    POLICY_MOUNT_RW = "policy-mount-rw"
    POLICY_MOUNT_DUPLICATE = "policy-mount-duplicate"
    TASK_TID = "task-tid"
    TASK_TGID = "task-tgid"
    TASK_PPID = "task-ppid"
    TASK_START = "task-start-ticks"
    TASK_START_ORDER = "task-start-order"
    TASK_UIDS = "task-uids"
    TASK_GIDS = "task-gids"
    TASK_GROUPS = "task-groups"
    TASK_GROUPS_DUPLICATE = "task-groups-duplicate"
    TASK_CAPABILITIES = "task-capabilities"
    TASK_NNP = "task-no-new-privs"
    TASK_NS_PID = "task-nspid-shape"
    TASK_NS_TGID = "task-nstgid-shape"
    TASK_NS_PID_FIRST = "task-nspid-first"
    TASK_NS_TGID_FIRST = "task-nstgid-first"
    TASK_NS_PID_POSITIVE = "task-nspid-positive"
    TASK_NS_TGID_POSITIVE = "task-nstgid-positive"
    TASK_CONTAINER = "task-container-id"
    TASK_CGROUP_PATH = "task-cgroup-path"
    TASK_CGROUP_COHERENT = "task-cgroups-coherent"
    PROCESS_PID = "process-pid"
    PROCESS_PPID = "process-ppid"
    PROCESS_START = "process-start-ticks"
    PROCESS_IDENTITY = "process-identity"
    PROCESS_COMPLETE = "process-tasks-complete"
    PROCESS_TASKS = "process-tasks"
    PROCESS_THREAD_DUPLICATE = "process-thread-duplicate"
    PROCESS_LEADER = "process-leader-start"
    PROCESS_INIT = "process-host-init"
    CONTAINER_ID = "container-id"
    CONTAINER_DUPLICATE = "container-duplicate"
    CONTAINER_IMAGE = "container-image"
    CONTAINER_ROOTFS = "container-rootfs"
    CONTAINER_STATE = "container-state-shape"
    CONTAINER_RUNNING = "container-running"
    CONTAINER_ROOT_PID = "container-root-pid"
    CONTAINER_STARTED_AT = "container-started-at"
    CONTAINER_CONFIG = "container-host-config"
    CONTAINER_MOUNTS = "container-mounts-shape"
    CONTAINER_RUNTIME = "container-runtime-pid-userns"
    CONTAINER_PRIVILEGED = "container-privileged"
    CONTAINER_CGROUP = "container-cgroup-parent"
    CONTAINER_NAMESPACE = "container-network-ipc-uts"
    CONTAINER_RESOURCES = "container-resource-flags"
    CONTAINER_SECURITY = "container-security-opt"
    VOLUME_NAME = "volume-name"
    VOLUME_DUPLICATE = "volume-duplicate"
    VOLUME_DRIVER_SCOPE = "volume-driver-scope"
    VOLUME_OPTIONS = "volume-options-shape"
    VOLUME_MOUNTPOINT = "volume-mountpoint"
    SNAPSHOT_COMPLETE = "snapshot-complete"
    SNAPSHOT_MODE = "snapshot-proof-mode"
    SNAPSHOT_CGROUP = "snapshot-cgroup-driver"
    SNAPSHOT_API = "snapshot-docker-api"
    SNAPSHOT_BOOT_ID = "snapshot-boot-id"
    SNAPSHOT_BOOT_TIME = "snapshot-boot-time"
    SNAPSHOT_CLOCK = "snapshot-clock-ticks"
    SNAPSHOT_INIT_NAMESPACE = "snapshot-init-namespace"
    SNAPSHOT_NAMESPACE_PROOF = "snapshot-namespace-proof"
    SNAPSHOT_TASK_NAMESPACE = "snapshot-task-namespace"
    MOUNT_COMPLETE = "mount-declarations-complete"
    MOUNT_DECLARATION = "mount-declaration-default"
    MOUNT_IDENTITY = "mount-identity"
    MOUNT_DUPLICATE = "mount-duplicate"
    MOUNT_TYPE = "mount-type-driver"
    MOUNT_VOLUME = "mount-volume-reference"
    MOUNT_POLICY = "mount-policy"
    MOUNT_VOLUME_SECURITY = "mount-volume-security"
    MOUNT_OVERLAP = "mount-protected-overlap"
    MOUNT_COHERENCE = "mount-declaration-coherence"
    PROCESS_SECURITY = "process-security"
    PROCESS_ROOT_NAMESPACE = "process-root-namespace"
    PROCESS_CREDENTIALS = "process-isolated-credentials"
    PROCESS_NNP_CAPS = "process-nnp-capabilities"
    PROCESS_NAMESPACE = "process-isolated-namespace"
    PROCESS_CGROUP = "process-isolated-cgroup"
    PROCESS_NAMESPACE_PROOF = "process-namespace-proof"
    ANCESTRY_IDENTITY = "ancestry-identity"
    ANCESTRY_START = "ancestry-start-order"
    OWN_ANCESTRY = "own-ancestry-credentials"
    AUTHORITY_OWNER = "authority-owner"
    AUTHORITY_OWNER_UNIQUE = "authority-owner-unique"
    NAMESPACE_UNIQUE = "namespace-thread-unique"
    COHORT_STABLE = "cohort-stable"


@dataclass(frozen=True)
class IsolationResult:
    """A bounded result, safe to include in the recovery diagnostic."""

    allowed: bool
    code: IsolationCode
    predicate: IsolationPredicate = IsolationPredicate.NONE
    section: IsolationSection = IsolationSection.NONE
    phase: IsolationPhase = IsolationPhase.NONE
    authority_peer: bool | None = None

    def diagnostic(self) -> dict:
        """Only members of these exact enums can reach the public projection."""
        return {"predicate": self.predicate.value if type(self.predicate) is IsolationPredicate else IsolationPredicate.NONE.value,
                "section": self.section.value if type(self.section) is IsolationSection else IsolationSection.NONE.value,
                "phase": self.phase.value if type(self.phase) is IsolationPhase else IsolationPhase.NONE.value,
                "authorityPeer": self.authority_peer if type(self.authority_peer) is bool else None}


class _Denied(Exception):
    def __init__(self, code: IsolationCode, predicate: IsolationPredicate = IsolationPredicate.VALIDATION):
        super().__init__(code.value)
        self.code = code
        self.predicate = predicate
        self.section = IsolationSection.NONE
        self.authority_peer = None


def _known_task_authority(value: Any, policy: dict | None) -> bool | None:
    if policy is None or type(value) is not dict:
        return None
    credentials = [value.get(key) for key in ("uids", "gids", "groups")]
    if any(type(values) is not list for values in credentials):
        return None
    if any(len(values) != 4 for values in credentials[:2]):
        return None
    if any(type(number) is not int or not 0 <= number < 1 << 32
           for values in credentials for number in values):
        return None
    return policy["authorityUid"] in credentials[0] or bool(
        set(policy["authorityGids"]).intersection(credentials[1] + credentials[2]))


def _section(section: IsolationSection, exception_predicate: IsolationPredicate | None = None):
    """Attach only a fixed section and typed credentials context to an existing denial."""
    def decorate(function):
        @wraps(function)
        def checked(*args, **kwargs):
            try:
                return function(*args, **kwargs)
            except _Denied as denied:
                if denied.section is IsolationSection.NONE:
                    denied.section = section
                    if section is IsolationSection.TASK:
                        denied.authority_peer = _known_task_authority(args[0], args[2] if len(args) > 2 else None)
                raise
            except (KeyError, TypeError, ValueError, OverflowError, StopIteration, RecursionError) as error:
                predicate = exception_predicate or next(predicate for error_type, predicate in {
                    KeyError: IsolationPredicate.MISSING_FIELD, TypeError: IsolationPredicate.FIELD_TYPE,
                    ValueError: IsolationPredicate.FIELD_VALUE, OverflowError: IsolationPredicate.FIELD_VALUE,
                    StopIteration: IsolationPredicate.ITERATION, RecursionError: IsolationPredicate.DEPTH,
                }.items() if isinstance(error, error_type))
                denied = _Denied(IsolationCode.INVALID_SNAPSHOT, predicate)
                denied.section = section
                if section is IsolationSection.TASK:
                    denied.authority_peer = _known_task_authority(args[0], args[2] if len(args) > 2 else None)
                raise denied from None
        return checked
    return decorate


_HEX_ID = re.compile(r"[a-f0-9]{64}\Z")
_IMAGE = re.compile(r"sha256:[a-f0-9]{64}\Z")
_BOOT_ID = re.compile(r"[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\Z")
_STARTED_AT = re.compile(r"(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,9}))?Z\Z")
_VOLUME_NAME = re.compile(r"[a-zA-Z0-9][a-zA-Z0-9_.-]*\Z")
_API_VERSION = re.compile(r"1\.([1-9]\d*)\Z")
_CAPABILITIES = ("capEff", "capPrm", "capInh", "capAmb", "capBnd")


def _require(condition: bool, code: IsolationCode = IsolationCode.INVALID_SNAPSHOT,
             predicate: IsolationPredicate = IsolationPredicate.VALIDATION) -> None:
    if not condition:
        raise _Denied(code, predicate)


def _dict(value: Any, predicate: IsolationPredicate = IsolationPredicate.OBJECT) -> dict:
    _require(type(value) is dict, predicate=predicate)
    return value


def _list(value: Any, predicate: IsolationPredicate = IsolationPredicate.ARRAY) -> list:
    _require(type(value) is list, predicate=predicate)
    return value


def _integer(value: Any, minimum: int = 0, predicate: IsolationPredicate = IsolationPredicate.INTEGER) -> int:
    _require(type(value) is int and value >= minimum, predicate=predicate)
    return value


def _identity(value: Any, predicate: IsolationPredicate = IsolationPredicate.IDENTITY) -> int:
    _require(_integer(value, predicate=predicate) < 1 << 32, predicate=predicate)
    return value


def _path(value: Any, predicate: IsolationPredicate = IsolationPredicate.PATH) -> str:
    _require(type(value) is str and value.startswith("/") and "\x00" not in value, predicate=predicate)
    _require(posixpath.normpath(value) == value and not value.startswith("//"), predicate=predicate)
    return value


def _overlaps(first: str, second: str) -> bool:
    return (first == second or first.startswith(second.rstrip("/") + "/")
            or second.startswith(first.rstrip("/") + "/"))


@_section(IsolationSection.CONTAINER, IsolationPredicate.CONTAINER_STARTED_AT)
def _started_nanoseconds(value: Any) -> int:
    _require(type(value) is str, predicate=IsolationPredicate.CONTAINER_STARTED_AT)
    match = _STARTED_AT.fullmatch(value)
    _require(match is not None, predicate=IsolationPredicate.CONTAINER_STARTED_AT)
    date = datetime.strptime(match[1], "%Y-%m-%dT%H:%M:%S").replace(tzinfo=timezone.utc)
    fraction = (match[2] or "").ljust(9, "0")
    return int(date.timestamp()) * 1_000_000_000 + int(fraction)


@_section(IsolationSection.POLICY)
def _policy(value: Any) -> dict:
    policy = _dict(value)
    _require(_identity(policy["authorityUid"], IsolationPredicate.POLICY_UID) == 1000,
             predicate=IsolationPredicate.POLICY_UID)
    groups = _list(policy["authorityGids"], IsolationPredicate.POLICY_GIDS)
    _require(bool(groups) and len(set(groups)) == len(groups), predicate=IsolationPredicate.POLICY_GIDS)
    for group in groups:
        _identity(group, IsolationPredicate.POLICY_GIDS)
    if "currentPid" in policy or "currentUid" in policy:
        _integer(policy["currentPid"], 1, IsolationPredicate.POLICY_PID)
        _require(type(policy["currentUid"]) is int and policy["currentUid"] == 1001,
                 predicate=IsolationPredicate.POLICY_CURRENT_UID)
    for key in ("protectedPaths", "developmentStoragePaths"):
        paths = _list(policy[key], IsolationPredicate.POLICY_PATHS)
        _require(bool(paths), predicate=IsolationPredicate.POLICY_PATHS)
        for path in paths:
            _path(path, IsolationPredicate.POLICY_PATHS)
    mounts = _list(policy["allowedVolumeMounts"])
    seen = set()
    for mount in mounts:
        mount = _dict(mount)
        _require(type(mount["Name"]) is str and _VOLUME_NAME.fullmatch(mount["Name"]) is not None,
                 predicate=IsolationPredicate.POLICY_MOUNT_NAME)
        _path(mount["Destination"], IsolationPredicate.POLICY_MOUNT_DESTINATION)
        _require(type(mount["RW"]) is bool, predicate=IsolationPredicate.POLICY_MOUNT_RW)
        identity = (mount["Name"], mount["Destination"], mount["RW"])
        _require(identity not in seen, predicate=IsolationPredicate.POLICY_MOUNT_DUPLICATE)
        seen.add(identity)
    return policy


@_section(IsolationSection.TASK)
def _task(value: Any, process: dict, policy: dict | None = None) -> dict:
    task = _dict(value)
    _integer(task["tid"], 1, IsolationPredicate.TASK_TID)
    _require(task["tgid"] == process["pid"] and type(task["tgid"]) is int,
             predicate=IsolationPredicate.TASK_TGID)
    _require(task["ppid"] == process["ppid"] and type(task["ppid"]) is int,
             predicate=IsolationPredicate.TASK_PPID)
    _integer(task["startTicks"], predicate=IsolationPredicate.TASK_START)
    _require(task["startTicks"] >= process["startTicks"], predicate=IsolationPredicate.TASK_START_ORDER)
    for key in ("uids", "gids"):
        predicate = IsolationPredicate.TASK_UIDS if key == "uids" else IsolationPredicate.TASK_GIDS
        values = _list(task[key], predicate)
        _require(len(values) == 4, predicate=predicate)
        for number in values:
            _identity(number, predicate)
    groups = _list(task["groups"], IsolationPredicate.TASK_GROUPS)
    for group in groups:
        _identity(group, IsolationPredicate.TASK_GROUPS)
    _require(len(set(groups)) == len(groups), predicate=IsolationPredicate.TASK_GROUPS_DUPLICATE)
    for key in _CAPABILITIES:
        _require(_integer(task[key], predicate=IsolationPredicate.TASK_CAPABILITIES) < 1 << 64,
                 predicate=IsolationPredicate.TASK_CAPABILITIES)
    _require(type(task["noNewPrivs"]) is int and task["noNewPrivs"] in (0, 1),
             predicate=IsolationPredicate.TASK_NNP)
    for key, host_id in (("nsPid", task["tid"]), ("nsTgid", process["pid"])):
        pid_namespace = key == "nsPid"
        values = _list(task[key], IsolationPredicate.TASK_NS_PID if pid_namespace else IsolationPredicate.TASK_NS_TGID)
        _require(bool(values) and values[0] == host_id,
                 predicate=IsolationPredicate.TASK_NS_PID_FIRST if pid_namespace else IsolationPredicate.TASK_NS_TGID_FIRST)
        for number in values:
            _integer(number, 1, IsolationPredicate.TASK_NS_PID_POSITIVE if pid_namespace else IsolationPredicate.TASK_NS_TGID_POSITIVE)
    owner = task["containerId"]
    _require(owner is None or (type(owner) is str and _HEX_ID.fullmatch(owner) is not None),
             predicate=IsolationPredicate.TASK_CONTAINER)
    _path(task["cgroupPath"], IsolationPredicate.TASK_CGROUP_PATH)
    _require(task["cgroupsCoherent"] is True, IsolationCode.INCOMPLETE_INVENTORY,
             IsolationPredicate.TASK_CGROUP_COHERENT)
    return task


@_section(IsolationSection.PROCESS)
def _processes(value: Any, policy: dict | None = None) -> dict[int, dict]:
    processes = {}
    thread_ids = set()
    for process in _list(value):
        process = _dict(process)
        pid = _integer(process["pid"], 1, IsolationPredicate.PROCESS_PID)
        _integer(process["ppid"], predicate=IsolationPredicate.PROCESS_PPID)
        _integer(process["startTicks"], predicate=IsolationPredicate.PROCESS_START)
        _require(pid not in processes and process["ppid"] != pid, predicate=IsolationPredicate.PROCESS_IDENTITY)
        _require(process["tasksComplete"] is True, IsolationCode.INCOMPLETE_INVENTORY, IsolationPredicate.PROCESS_COMPLETE)
        tasks = _list(process["tasks"], IsolationPredicate.PROCESS_TASKS)
        _require(bool(tasks), IsolationCode.INCOMPLETE_INVENTORY, IsolationPredicate.PROCESS_TASKS)
        leader = None
        for value in tasks:
            task = _task(value, process, policy)
            _require(task["tid"] not in thread_ids, predicate=IsolationPredicate.PROCESS_THREAD_DUPLICATE)
            thread_ids.add(task["tid"])
            if task["tid"] == pid:
                leader = task
        _require(leader is not None and leader["startTicks"] == process["startTicks"], predicate=IsolationPredicate.PROCESS_LEADER)
        processes[pid] = process
    _require(1 in processes and processes[1]["ppid"] == 0, IsolationCode.INCOMPLETE_INVENTORY, IsolationPredicate.PROCESS_INIT)
    return processes


@_section(IsolationSection.CONTAINER)
def _containers(value: Any) -> dict[str, dict]:
    containers = {}
    roots = set()
    for container in _list(value):
        container = _dict(container)
        identifier = container["Id"]
        _require(type(identifier) is str and _HEX_ID.fullmatch(identifier) is not None, predicate=IsolationPredicate.CONTAINER_ID)
        _require(identifier not in containers, IsolationCode.AMBIGUOUS_OWNERSHIP, IsolationPredicate.CONTAINER_DUPLICATE)
        _require(type(container["Image"]) is str and _IMAGE.fullmatch(container["Image"]) is not None,
                 predicate=IsolationPredicate.CONTAINER_IMAGE)
        _require(_dict(container["RootFS"], IsolationPredicate.CONTAINER_ROOTFS)["Type"] == "layers",
                 IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_ROOTFS)
        state = _dict(container["State"], IsolationPredicate.CONTAINER_STATE)
        for key in ("Running", "Restarting", "Paused"):
            _require(type(state[key]) is bool, predicate=IsolationPredicate.CONTAINER_STATE)
        pid = _integer(state["Pid"], predicate=IsolationPredicate.CONTAINER_ROOT_PID)
        _require(pid > 1 and pid not in roots, IsolationCode.AMBIGUOUS_OWNERSHIP, IsolationPredicate.CONTAINER_ROOT_PID)
        _started_nanoseconds(state["StartedAt"])
        _dict(container["HostConfig"], IsolationPredicate.CONTAINER_CONFIG)
        _list(container["Mounts"], IsolationPredicate.CONTAINER_MOUNTS)
        roots.add(pid)
        containers[identifier] = container
    return containers


@_section(IsolationSection.VOLUME)
def _volumes(value: Any) -> dict[str, dict]:
    volumes = {}
    for volume in _list(value):
        volume = _dict(volume)
        name = volume["Name"]
        _require(type(name) is str and _VOLUME_NAME.fullmatch(name) is not None, predicate=IsolationPredicate.VOLUME_NAME)
        _require(name not in volumes, predicate=IsolationPredicate.VOLUME_DUPLICATE)
        _require(type(volume["Driver"]) is str and type(volume["Scope"]) is str, predicate=IsolationPredicate.VOLUME_DRIVER_SCOPE)
        _require(type(volume["OptionsEmpty"]) is bool, predicate=IsolationPredicate.VOLUME_OPTIONS)
        _path(volume["Mountpoint"], IsolationPredicate.VOLUME_MOUNTPOINT)
        volumes[name] = volume
    return volumes


@_section(IsolationSection.SNAPSHOT)
def _snapshot(value: Any, policy: dict | None = None) -> dict:
    snapshot = _dict(value)
    _require(snapshot["complete"] is True, IsolationCode.INCOMPLETE_INVENTORY, IsolationPredicate.SNAPSHOT_COMPLETE)
    _require(snapshot["proofMode"] == "trusted-docker-runc-private-pid", predicate=IsolationPredicate.SNAPSHOT_MODE)
    _require(snapshot["cgroupDriver"] in ("systemd", "cgroupfs"), predicate=IsolationPredicate.SNAPSHOT_CGROUP)
    _require(type(snapshot["dockerApiVersion"]) is str, predicate=IsolationPredicate.SNAPSHOT_API)
    api_version = _API_VERSION.fullmatch(snapshot["dockerApiVersion"])
    _require(api_version is not None and int(api_version[1]) >= 45, predicate=IsolationPredicate.SNAPSHOT_API)
    _require(type(snapshot["bootId"]) is str and _BOOT_ID.fullmatch(snapshot["bootId"]) is not None,
             predicate=IsolationPredicate.SNAPSHOT_BOOT_ID)
    for key in ("bootTimeSeconds", "clockTicks"):
        _integer(snapshot[key], 1, IsolationPredicate.SNAPSHOT_BOOT_TIME if key == "bootTimeSeconds" else IsolationPredicate.SNAPSHOT_CLOCK)
    processes = _processes(snapshot["processes"], policy)
    host_leader = next(task for task in processes[1]["tasks"] if task["tid"] == 1)
    _require(host_leader["nsPid"] == [1] and host_leader["nsTgid"] == [1]
             and host_leader["containerId"] is None, predicate=IsolationPredicate.SNAPSHOT_INIT_NAMESPACE)
    metadata = {key: snapshot[key] for key in ("proofMode", "dockerApiVersion", "cgroupDriver", "bootId", "bootTimeSeconds", "clockTicks")}
    namespace_proof = snapshot.get("namespaceProof")
    if "namespaceProof" in snapshot:
        namespace_proof = _dict(namespace_proof, IsolationPredicate.SNAPSHOT_NAMESPACE_PROOF)
        for key in ("hostPidNamespace", "hostMountNamespace"):
            _integer(namespace_proof[key], 1, IsolationPredicate.SNAPSHOT_NAMESPACE_PROOF)
        _require(host_leader["pidNamespace"] == namespace_proof["hostPidNamespace"]
                 and host_leader["mountNamespace"] == namespace_proof["hostMountNamespace"], predicate=IsolationPredicate.SNAPSHOT_NAMESPACE_PROOF)
        metadata["namespaceProof"] = namespace_proof
    for process in processes.values():
        for task in process["tasks"]:
            for key in ("pidNamespace", "mountNamespace"):
                if namespace_proof is not None:
                    _integer(task[key], 1, IsolationPredicate.SNAPSHOT_TASK_NAMESPACE)
                else:
                    _require(key not in task, predicate=IsolationPredicate.SNAPSHOT_TASK_NAMESPACE)
    return {"metadata": metadata,
        "processes": processes,
        "containers": _containers(snapshot["containers"]),
        "volumes": _volumes(snapshot["volumes"])}


def _authority(process: dict, policy: dict) -> bool:
    for task in process["tasks"]:
        if policy["authorityUid"] in task["uids"]:
            return True
        if set(policy["authorityGids"]).intersection(task["gids"] + task["groups"]):
            return True
    return False


@_section(IsolationSection.ANCESTRY)
def _ancestors(pid: int, processes: dict[int, dict]) -> list[int]:
    ancestors = []
    while pid:
        _require(pid in processes and pid not in ancestors, IsolationCode.INVALID_ANCESTRY, IsolationPredicate.ANCESTRY_IDENTITY)
        process = processes[pid]
        if ancestors:
            _require(process["startTicks"] <= processes[ancestors[-1]]["startTicks"],
                     IsolationCode.INVALID_ANCESTRY, IsolationPredicate.ANCESTRY_START)
        ancestors.append(pid)
        pid = process["ppid"]
    return ancestors


@_section(IsolationSection.CONTAINER)
def _container_security(container: dict) -> None:
    state = container["State"]
    _require(state["Running"] and not state["Restarting"] and not state["Paused"],
             IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_RUNNING)
    config = container["HostConfig"]
    _require(config["Runtime"] == "runc" and type(config["PidMode"]) is str and config["PidMode"] == ""
             and type(config["UsernsMode"]) is str and config["UsernsMode"] == "",
             IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_RUNTIME)
    _require(config["Privileged"] is False, IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_PRIVILEGED)
    _require(config["CgroupParent"] == "", IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_CGROUP)
    _require(type(config["NetworkMode"]) is str and bool(config["NetworkMode"])
             and config["NetworkMode"] != "host" and not config["NetworkMode"].startswith("container:")
             and config["IpcMode"] in ("", "private") and config["UTSMode"] == "",
             IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_NAMESPACE)
    # The trusted, conforming Engine API projection supplies typed empty flags;
    # no driver/device/bind option content is needed in this pure proof.
    for key in ("CapAddEmpty", "DevicesEmpty", "DeviceRequestsEmpty", "DeviceCgroupRulesEmpty", "TmpfsEmpty", "VolumesFromEmpty"):
        _require(config[key] is True, IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_RESOURCES)
    options = _list(config["SecurityOpt"], IsolationPredicate.CONTAINER_SECURITY)
    _require(bool(options) and all(option in ("no-new-privileges", "no-new-privileges:true")
                                  for option in options), IsolationCode.UNSAFE_CONTAINER, IsolationPredicate.CONTAINER_SECURITY)


@_section(IsolationSection.MOUNT)
def _container_mounts(container: dict, volumes: dict, policy: dict) -> None:
    config = container["HostConfig"]
    _require(config["MountDeclarationsComplete"] is True, IsolationCode.INCOMPLETE_INVENTORY, IsolationPredicate.MOUNT_COMPLETE)
    declarations = []
    for declaration in _list(config["MountDeclarations"]):
        declaration = _dict(declaration)
        _require(declaration["Type"] == "volume" and declaration["OptionsDefault"] is True,
                 IsolationCode.UNSAFE_MOUNT, IsolationPredicate.MOUNT_DECLARATION)
        _require(type(declaration["Name"]) is str and type(declaration["RW"]) is bool, predicate=IsolationPredicate.MOUNT_IDENTITY)
        declarations.append((declaration["Name"], _path(declaration["Destination"]), declaration["RW"]))
    _require(len(set(declarations)) == len(declarations), IsolationCode.UNSAFE_MOUNT, IsolationPredicate.MOUNT_DUPLICATE)
    destinations = set()
    actual = []
    for mount in container["Mounts"]:
        mount = _dict(mount)
        _require(mount["Type"] == "volume" and mount["Driver"] == "local", IsolationCode.UNSAFE_MOUNT, IsolationPredicate.MOUNT_TYPE)
        name = mount["Name"]
        _require(type(name) is str and name in volumes, IsolationCode.UNSAFE_VOLUME, IsolationPredicate.MOUNT_VOLUME)
        destination = _path(mount["Destination"])
        source = _path(mount["Source"])
        _require(type(mount["RW"]) is bool and destination not in destinations, IsolationCode.UNSAFE_MOUNT, IsolationPredicate.MOUNT_IDENTITY)
        _require(any(mount["Name"] == expected["Name"] and destination == expected["Destination"]
                     and mount["RW"] is expected["RW"] for expected in policy["allowedVolumeMounts"]),
                 IsolationCode.UNSAFE_MOUNT, IsolationPredicate.MOUNT_POLICY)
        volume = volumes[name]
        _require(volume["Driver"] == "local" and volume["Scope"] == "local"
                 and volume["OptionsEmpty"] is True and volume["Mountpoint"] == source,
                 IsolationCode.UNSAFE_VOLUME, IsolationPredicate.MOUNT_VOLUME_SECURITY)
        protected = policy["protectedPaths"] + policy["developmentStoragePaths"]
        _require(not any(_overlaps(source, path) for path in protected), IsolationCode.UNSAFE_VOLUME, IsolationPredicate.MOUNT_OVERLAP)
        destinations.add(destination)
        actual.append((name, destination, mount["RW"]))
    _require(set(actual) == set(declarations), IsolationCode.UNSAFE_MOUNT, IsolationPredicate.MOUNT_COHERENCE)


@_section(IsolationSection.PROCESS)
def _process_security(process: dict, root: dict, container: dict, metadata: dict) -> None:
    leader = next(task for task in root["tasks"] if task["tid"] == root["pid"])
    _require(leader["nsPid"] == [root["pid"], 1] and leader["nsTgid"] == [root["pid"], 1],
             IsolationCode.UNSAFE_PROCESS, IsolationPredicate.PROCESS_ROOT_NAMESPACE)
    process_leader = next(task for task in process["tasks"] if task["tid"] == process["pid"])
    for task in process["tasks"]:
        _integer(task["startTicks"], 1, IsolationPredicate.TASK_START)
        _require(task["uids"] == [1000] * 4 and task["gids"] == [task["gids"][0]] * 4,
                 IsolationCode.UNSAFE_PROCESS, IsolationPredicate.PROCESS_CREDENTIALS)
        # A homogeneous numeric GID is pinned, rather than restricted to 1000:
        # it grants no host path authority across the fully proved boundary.
        _require(task["noNewPrivs"] == 1 and all(task[key] == 0 for key in _CAPABILITIES[:4]),
                 IsolationCode.UNSAFE_PROCESS, IsolationPredicate.PROCESS_NNP_CAPS)
        # A nonzero default Docker CapBnd cannot grant capabilities under NNP with
        # empty permitted/inheritable/ambient sets; it is deliberately permitted.
        _require(len(task["nsPid"]) == 2 and len(task["nsTgid"]) == 2
                 and task["nsTgid"] == process_leader["nsPid"]
                 and task["containerId"] == container["Id"],
                 IsolationCode.UNSAFE_PROCESS, IsolationPredicate.PROCESS_NAMESPACE)
        cgroup_path = ("/system.slice/docker-" + container["Id"] + ".scope"
                       if metadata["cgroupDriver"] == "systemd" else "/docker/" + container["Id"])
        _require(task["cgroupPath"] == cgroup_path, IsolationCode.UNSAFE_PROCESS, IsolationPredicate.PROCESS_CGROUP)
        if "namespaceProof" in metadata:
            namespace = metadata["namespaceProof"]
            _require(task["pidNamespace"] == leader["pidNamespace"] != namespace["hostPidNamespace"]
                     and task["mountNamespace"] == leader["mountNamespace"] != namespace["hostMountNamespace"],
                     IsolationCode.UNSAFE_PROCESS, IsolationPredicate.PROCESS_NAMESPACE_PROOF)


def _stable_process(process: dict, *, inside_container: bool) -> dict:
    """Pin full peer security, but only identity/ownership for trusted root ancestors."""
    keys = ("tid", "tgid", "ppid", "startTicks", "uids", "gids", "groups", "containerId",
            "cgroupPath", "cgroupsCoherent", "nsPid", "nsTgid")
    if inside_container:
        keys += _CAPABILITIES + ("noNewPrivs",)
    tasks = {}
    for task in process["tasks"]:
        fields = {key: task[key] for key in keys}
        for key in ("pidNamespace", "mountNamespace"):
            if key in task:
                fields[key] = task[key]
        tasks[task["tid"]] = fields
    return {"pid": process["pid"], "ppid": process["ppid"], "startTicks": process["startTicks"], "tasks": tasks}


def _stable_container(container: dict) -> dict:
    """Ignore labels/names/health counters; pin every field actually used by the proof."""
    config_keys = ("Runtime", "PidMode", "UsernsMode", "CgroupParent", "Privileged", "NetworkMode", "IpcMode", "UTSMode",
                   "CapAddEmpty", "DevicesEmpty", "DeviceRequestsEmpty", "DeviceCgroupRulesEmpty",
                   "TmpfsEmpty", "VolumesFromEmpty", "SecurityOpt", "MountDeclarationsComplete")
    config = {key: container["HostConfig"][key] for key in config_keys}
    config["MountDeclarations"] = {mount["Destination"]: mount for mount in container["HostConfig"]["MountDeclarations"]}
    return {"Id": container["Id"], "Image": container["Image"], "RootFS": container["RootFS"]["Type"],
            "State": {key: container["State"][key] for key in ("Pid", "StartedAt", "Running", "Restarting", "Paused")},
            "HostConfig": config, "Mounts": {mount["Destination"]: mount for mount in container["Mounts"]}}


@_section(IsolationSection.ANCESTRY)
def _own_ancestry(processes: dict[int, dict], policy: dict) -> list[int]:
    """Only the collector's actual host PID/UID may exempt its computed ancestry."""
    owned = []
    if "currentPid" in policy:
        owned = _ancestors(policy["currentPid"], processes)
        for pid in owned:
            for task in processes[pid]["tasks"]:
                uid = task["uids"][0]
                _require(uid in (0, 1001) and task["uids"] == [uid] * 4
                         and task["gids"] == [uid] * 4 and task["containerId"] is None
                         and task["nsPid"] == [task["tid"]] and task["nsTgid"] == [pid],
                         IsolationCode.UNSAFE_PROCESS, IsolationPredicate.OWN_ANCESTRY)
                if pid == policy["currentPid"]:
                    _require(uid == 1001 and all(task[key] == 0 for key in _CAPABILITIES[:4]),
                             IsolationCode.UNSAFE_PROCESS, IsolationPredicate.OWN_ANCESTRY)
    return owned


@_section(IsolationSection.COHORT)
def _prove(snapshot: dict, policy: dict) -> dict:
    processes = snapshot["processes"]
    containers = snapshot["containers"]
    roots = {container["State"]["Pid"]: container for container in containers.values()}
    verified = set()
    namespace_tasks = {}
    own = _own_ancestry(processes, policy)
    cohort = {"metadata": snapshot["metadata"], "processes": {}, "containers": {}, "volumes": {},
              "ownAncestry": {pid: _stable_process(processes[pid], inside_container=False) for pid in own}}
    for pid, process in processes.items():
        if pid in own or not _authority(process, policy):
            continue
        ancestry = _ancestors(pid, processes)
        owners = [root for root in ancestry if root in roots]
        _require(bool(owners), IsolationCode.UNKNOWN_AUTHORITY_PEER, IsolationPredicate.AUTHORITY_OWNER)
        _require(len(owners) == 1, IsolationCode.AMBIGUOUS_OWNERSHIP, IsolationPredicate.AUTHORITY_OWNER_UNIQUE)
        root_pid = owners[0]
        container = roots[root_pid]
        root = processes[root_pid]
        if root_pid not in verified:
            _container_security(container)
            _container_mounts(container, snapshot["volumes"], policy)
            verified.add(root_pid)
            cohort["containers"][container["Id"]] = _stable_container(container)
            for mount in container["Mounts"]:
                cohort["volumes"][mount["Name"]] = snapshot["volumes"][mount["Name"]]
        for ancestor in ancestry:
            inside = ancestry.index(ancestor) <= ancestry.index(root_pid)
            cohort["processes"][ancestor] = _stable_process(processes[ancestor], inside_container=inside)
        for ancestor in ancestry[:ancestry.index(root_pid) + 1]:
            candidate = processes[ancestor]
            _process_security(candidate, root, container, snapshot["metadata"])
            for task in candidate["tasks"]:
                identity = (container["Id"], task["nsPid"][1])
                previous = namespace_tasks.get(identity, task["tid"])
                _require(previous == task["tid"], IsolationCode.AMBIGUOUS_OWNERSHIP, IsolationPredicate.NAMESPACE_UNIQUE)
                namespace_tasks[identity] = task["tid"]
    return cohort


def validate_isolation(policy: Any, before: Any, after: Any) -> IsolationResult:
    """Validate complete projected metadata, denying malformed input without echoing it.

    Policy fields: authorityUid (1000), authorityGids (nonempty numeric list),
    protectedPaths/developmentStoragePaths (canonical physical absolute paths),
    allowedVolumeMounts (trusted exact Name/Destination/RW triples).
    Optional currentPid/currentUid must be the trusted collector's own os.getpid()
    and UID 1001. Only its validated, computed host ancestry is exempt; all UID/GID
    quartets must be pure 1001 (or 0 on root ancestors). Supplementary authority
    groups on that exact pinned chain are permitted. Other owner/group peers remain
    blocking, and this never replaces the recovery helper's existing owner guard.
    Snapshots: complete, proofMode='trusted-docker-runc-private-pid',
    dockerApiVersion (actually used, >=1.45), bootId/bootTimeSeconds/clockTicks,
    cgroupDriver (systemd/cgroupfs),
    processes (all leaders and tasks), containers (live Docker inspect
    projections without Env/Cmd/labels), volumes (Docker volume projections).
    HostConfig sensitive collections are projected as strict *Empty booleans;
    MountDeclarationsComplete attests both Mounts and Binds were projected into
    MountDeclarations with Type/Name/Destination/RW/OptionsDefault fields. Named
    volumes must exactly match effective mounts; nondefault options are denied.
    volume OptionsEmpty is true only for null/empty-object Options in a trusted,
    conforming Engine response (the field is required since API 1.25). This mode
    requires successful official Docker queries/templates/decoding at the actual
    negotiated or fixed API version, not merely the server's maximum version.
    The trusted collector rejects nonconforming/malformed original responses and
    never serializes their option keys or values into this proof or its result.
    Optional namespaceProof contains hostPidNamespace/hostMountNamespace inode
    IDs and requires corresponding pidNamespace/mountNamespace in every task;
    inconsistent optional proof is denied rather than silently discarded.
    containerId must be independently attributed by complete coherent cgroup
    metadata: task cgroupsCoherent=true and cgroupPath exactly matches the Docker
    driver's default /system.slice/docker-<full-id>.scope or /docker/<full-id>.
    Default empty HostConfig.CgroupParent is required. No substring match under
    user.slice, name guess or attribution solely by the same PPid chain is valid.
    Both inventories are proved before comparing the relevant authority peers,
    ancestors and used containers/volumes. Unrelated host churn does not invalidate
    a proof. PID reuse, fresh authority peers/threads or a restart does invalidate it.
    StartedAt is pinned, not compared to kernel creation time: Docker captures
    startupTime before creating its task, so no absolute timestamp bound is sound.
    """
    phase = IsolationPhase.POLICY
    try:
        trusted_policy = _policy(policy)
        phase = IsolationPhase.BEFORE
        previous = _snapshot(before, trusted_policy)
        phase = IsolationPhase.AFTER
        current = _snapshot(after, trusted_policy)
        phase = IsolationPhase.PROVE_BEFORE
        previous_cohort = _prove(previous, trusted_policy)
        phase = IsolationPhase.PROVE_AFTER
        current_cohort = _prove(current, trusted_policy)
        phase = IsolationPhase.COMPARE
        _require(previous_cohort == current_cohort, IsolationCode.IDENTITY_CHANGED, IsolationPredicate.COHORT_STABLE)
        result = IsolationResult(True, IsolationCode.ALLOWED)
    except _Denied as denied:
        section = denied.section if denied.section is not IsolationSection.NONE else IsolationSection.COHORT
        result = IsolationResult(False, denied.code, denied.predicate, section, phase, denied.authority_peer)
    except (KeyError, TypeError, ValueError, OverflowError, StopIteration, RecursionError):
        result = IsolationResult(False, IsolationCode.INVALID_SNAPSHOT)
    return result
