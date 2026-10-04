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


@dataclass(frozen=True)
class IsolationResult:
    """A bounded result, safe to include in the recovery diagnostic."""

    allowed: bool
    code: IsolationCode


class _Denied(Exception):
    def __init__(self, code: IsolationCode):
        super().__init__(code.value)
        self.code = code


_HEX_ID = re.compile(r"[a-f0-9]{64}\Z")
_IMAGE = re.compile(r"sha256:[a-f0-9]{64}\Z")
_BOOT_ID = re.compile(r"[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\Z")
_STARTED_AT = re.compile(r"(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2})(?:\.(\d{1,9}))?Z\Z")
_VOLUME_NAME = re.compile(r"[a-zA-Z0-9][a-zA-Z0-9_.-]*\Z")
_API_VERSION = re.compile(r"1\.([1-9]\d*)\Z")
_CAPABILITIES = ("capEff", "capPrm", "capInh", "capAmb", "capBnd")


def _require(condition: bool, code: IsolationCode = IsolationCode.INVALID_SNAPSHOT) -> None:
    if not condition:
        raise _Denied(code)


def _dict(value: Any) -> dict:
    _require(type(value) is dict)
    return value


def _list(value: Any) -> list:
    _require(type(value) is list)
    return value


def _integer(value: Any, minimum: int = 0) -> int:
    _require(type(value) is int and value >= minimum)
    return value


def _identity(value: Any) -> int:
    _require(_integer(value) < 1 << 32)
    return value


def _path(value: Any) -> str:
    _require(type(value) is str and value.startswith("/") and "\x00" not in value)
    _require(posixpath.normpath(value) == value and not value.startswith("//"))
    return value


def _overlaps(first: str, second: str) -> bool:
    return (first == second or first.startswith(second.rstrip("/") + "/")
            or second.startswith(first.rstrip("/") + "/"))


def _started_nanoseconds(value: Any) -> int:
    _require(type(value) is str)
    match = _STARTED_AT.fullmatch(value)
    _require(match is not None)
    date = datetime.strptime(match[1], "%Y-%m-%dT%H:%M:%S").replace(tzinfo=timezone.utc)
    fraction = (match[2] or "").ljust(9, "0")
    return int(date.timestamp()) * 1_000_000_000 + int(fraction)


def _policy(value: Any) -> dict:
    policy = _dict(value)
    _require(_identity(policy["authorityUid"]) == 1000)
    groups = _list(policy["authorityGids"])
    _require(bool(groups) and len(set(groups)) == len(groups))
    for group in groups:
        _identity(group)
    if "currentPid" in policy or "currentUid" in policy:
        _integer(policy["currentPid"], 1)
        _require(type(policy["currentUid"]) is int and policy["currentUid"] == 1001)
    for key in ("protectedPaths", "developmentStoragePaths"):
        paths = _list(policy[key])
        _require(bool(paths))
        for path in paths:
            _path(path)
    mounts = _list(policy["allowedVolumeMounts"])
    seen = set()
    for mount in mounts:
        mount = _dict(mount)
        _require(type(mount["Name"]) is str and _VOLUME_NAME.fullmatch(mount["Name"]) is not None)
        _path(mount["Destination"])
        _require(type(mount["RW"]) is bool)
        identity = (mount["Name"], mount["Destination"], mount["RW"])
        _require(identity not in seen)
        seen.add(identity)
    return policy


def _task(value: Any, process: dict) -> dict:
    task = _dict(value)
    _integer(task["tid"], 1)
    _require(task["tgid"] == process["pid"] and type(task["tgid"]) is int)
    _require(task["ppid"] == process["ppid"] and type(task["ppid"]) is int)
    _integer(task["startTicks"])
    _require(task["startTicks"] >= process["startTicks"])
    for key in ("uids", "gids"):
        values = _list(task[key])
        _require(len(values) == 4)
        for number in values:
            _identity(number)
    groups = _list(task["groups"])
    for group in groups:
        _identity(group)
    _require(len(set(groups)) == len(groups))
    for key in _CAPABILITIES:
        _require(_integer(task[key]) < 1 << 64)
    _require(type(task["noNewPrivs"]) is int and task["noNewPrivs"] in (0, 1))
    for key, host_id in (("nsPid", task["tid"]), ("nsTgid", process["pid"])):
        values = _list(task[key])
        _require(bool(values) and values[0] == host_id)
        for number in values:
            _integer(number, 1)
    owner = task["containerId"]
    _require(owner is None or (type(owner) is str and _HEX_ID.fullmatch(owner) is not None))
    _path(task["cgroupPath"])
    _require(task["cgroupsCoherent"] is True, IsolationCode.INCOMPLETE_INVENTORY)
    return task


def _processes(value: Any) -> dict[int, dict]:
    processes = {}
    thread_ids = set()
    for process in _list(value):
        process = _dict(process)
        pid = _integer(process["pid"], 1)
        _integer(process["ppid"])
        _integer(process["startTicks"])
        _require(pid not in processes and process["ppid"] != pid)
        _require(process["tasksComplete"] is True, IsolationCode.INCOMPLETE_INVENTORY)
        tasks = _list(process["tasks"])
        _require(bool(tasks), IsolationCode.INCOMPLETE_INVENTORY)
        leader = None
        for value in tasks:
            task = _task(value, process)
            _require(task["tid"] not in thread_ids)
            thread_ids.add(task["tid"])
            if task["tid"] == pid:
                leader = task
        _require(leader is not None and leader["startTicks"] == process["startTicks"])
        processes[pid] = process
    _require(1 in processes and processes[1]["ppid"] == 0, IsolationCode.INCOMPLETE_INVENTORY)
    return processes


def _containers(value: Any) -> dict[str, dict]:
    containers = {}
    roots = set()
    for container in _list(value):
        container = _dict(container)
        identifier = container["Id"]
        _require(type(identifier) is str and _HEX_ID.fullmatch(identifier) is not None)
        _require(identifier not in containers, IsolationCode.AMBIGUOUS_OWNERSHIP)
        _require(type(container["Image"]) is str and _IMAGE.fullmatch(container["Image"]) is not None)
        _require(_dict(container["RootFS"])["Type"] == "layers", IsolationCode.UNSAFE_CONTAINER)
        state = _dict(container["State"])
        for key in ("Running", "Restarting", "Paused"):
            _require(type(state[key]) is bool)
        pid = _integer(state["Pid"])
        _require(pid > 1 and pid not in roots, IsolationCode.AMBIGUOUS_OWNERSHIP)
        _started_nanoseconds(state["StartedAt"])
        _dict(container["HostConfig"])
        _list(container["Mounts"])
        roots.add(pid)
        containers[identifier] = container
    return containers


def _volumes(value: Any) -> dict[str, dict]:
    volumes = {}
    for volume in _list(value):
        volume = _dict(volume)
        name = volume["Name"]
        _require(type(name) is str and _VOLUME_NAME.fullmatch(name) is not None)
        _require(name not in volumes)
        _require(type(volume["Driver"]) is str and type(volume["Scope"]) is str)
        _require(type(volume["OptionsEmpty"]) is bool)
        _path(volume["Mountpoint"])
        volumes[name] = volume
    return volumes


def _snapshot(value: Any) -> dict:
    snapshot = _dict(value)
    _require(snapshot["complete"] is True, IsolationCode.INCOMPLETE_INVENTORY)
    _require(snapshot["proofMode"] == "trusted-docker-runc-private-pid")
    _require(snapshot["cgroupDriver"] in ("systemd", "cgroupfs"))
    _require(type(snapshot["dockerApiVersion"]) is str)
    api_version = _API_VERSION.fullmatch(snapshot["dockerApiVersion"])
    _require(api_version is not None and int(api_version[1]) >= 45)
    _require(type(snapshot["bootId"]) is str and _BOOT_ID.fullmatch(snapshot["bootId"]) is not None)
    for key in ("bootTimeSeconds", "clockTicks"):
        _integer(snapshot[key], 1)
    processes = _processes(snapshot["processes"])
    host_leader = next(task for task in processes[1]["tasks"] if task["tid"] == 1)
    _require(host_leader["nsPid"] == [1] and host_leader["nsTgid"] == [1]
             and host_leader["containerId"] is None)
    metadata = {key: snapshot[key] for key in ("proofMode", "dockerApiVersion", "cgroupDriver", "bootId", "bootTimeSeconds", "clockTicks")}
    namespace_proof = snapshot.get("namespaceProof")
    if "namespaceProof" in snapshot:
        namespace_proof = _dict(namespace_proof)
        for key in ("hostPidNamespace", "hostMountNamespace"):
            _integer(namespace_proof[key], 1)
        _require(host_leader["pidNamespace"] == namespace_proof["hostPidNamespace"]
                 and host_leader["mountNamespace"] == namespace_proof["hostMountNamespace"])
        metadata["namespaceProof"] = namespace_proof
    for process in processes.values():
        for task in process["tasks"]:
            for key in ("pidNamespace", "mountNamespace"):
                if namespace_proof is not None:
                    _integer(task[key], 1)
                else:
                    _require(key not in task)
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


def _ancestors(pid: int, processes: dict[int, dict]) -> list[int]:
    ancestors = []
    while pid:
        _require(pid in processes and pid not in ancestors, IsolationCode.INVALID_ANCESTRY)
        process = processes[pid]
        if ancestors:
            _require(process["startTicks"] <= processes[ancestors[-1]]["startTicks"],
                     IsolationCode.INVALID_ANCESTRY)
        ancestors.append(pid)
        pid = process["ppid"]
    return ancestors


def _container_security(container: dict) -> None:
    state = container["State"]
    _require(state["Running"] and not state["Restarting"] and not state["Paused"],
             IsolationCode.UNSAFE_CONTAINER)
    config = container["HostConfig"]
    _require(config["Runtime"] == "runc" and type(config["PidMode"]) is str and config["PidMode"] == ""
             and type(config["UsernsMode"]) is str and config["UsernsMode"] == "",
             IsolationCode.UNSAFE_CONTAINER)
    _require(config["Privileged"] is False, IsolationCode.UNSAFE_CONTAINER)
    _require(config["CgroupParent"] == "", IsolationCode.UNSAFE_CONTAINER)
    _require(type(config["NetworkMode"]) is str and bool(config["NetworkMode"])
             and config["NetworkMode"] != "host" and not config["NetworkMode"].startswith("container:")
             and config["IpcMode"] in ("", "private") and config["UTSMode"] == "",
             IsolationCode.UNSAFE_CONTAINER)
    # The trusted, conforming Engine API projection supplies typed empty flags;
    # no driver/device/bind option content is needed in this pure proof.
    for key in ("CapAddEmpty", "DevicesEmpty", "DeviceRequestsEmpty", "DeviceCgroupRulesEmpty", "TmpfsEmpty", "VolumesFromEmpty"):
        _require(config[key] is True, IsolationCode.UNSAFE_CONTAINER)
    options = _list(config["SecurityOpt"])
    _require(bool(options) and all(option in ("no-new-privileges", "no-new-privileges:true")
                                  for option in options), IsolationCode.UNSAFE_CONTAINER)


def _container_mounts(container: dict, volumes: dict, policy: dict) -> None:
    config = container["HostConfig"]
    _require(config["MountDeclarationsComplete"] is True, IsolationCode.INCOMPLETE_INVENTORY)
    declarations = []
    for declaration in _list(config["MountDeclarations"]):
        declaration = _dict(declaration)
        _require(declaration["Type"] == "volume" and declaration["OptionsDefault"] is True,
                 IsolationCode.UNSAFE_MOUNT)
        _require(type(declaration["Name"]) is str and type(declaration["RW"]) is bool)
        declarations.append((declaration["Name"], _path(declaration["Destination"]), declaration["RW"]))
    _require(len(set(declarations)) == len(declarations), IsolationCode.UNSAFE_MOUNT)
    destinations = set()
    actual = []
    for mount in container["Mounts"]:
        mount = _dict(mount)
        _require(mount["Type"] == "volume" and mount["Driver"] == "local", IsolationCode.UNSAFE_MOUNT)
        name = mount["Name"]
        _require(type(name) is str and name in volumes, IsolationCode.UNSAFE_VOLUME)
        destination = _path(mount["Destination"])
        source = _path(mount["Source"])
        _require(type(mount["RW"]) is bool and destination not in destinations, IsolationCode.UNSAFE_MOUNT)
        _require(any(mount["Name"] == expected["Name"] and destination == expected["Destination"]
                     and mount["RW"] is expected["RW"] for expected in policy["allowedVolumeMounts"]),
                 IsolationCode.UNSAFE_MOUNT)
        volume = volumes[name]
        _require(volume["Driver"] == "local" and volume["Scope"] == "local"
                 and volume["OptionsEmpty"] is True and volume["Mountpoint"] == source,
                 IsolationCode.UNSAFE_VOLUME)
        protected = policy["protectedPaths"] + policy["developmentStoragePaths"]
        _require(not any(_overlaps(source, path) for path in protected), IsolationCode.UNSAFE_VOLUME)
        destinations.add(destination)
        actual.append((name, destination, mount["RW"]))
    _require(set(actual) == set(declarations), IsolationCode.UNSAFE_MOUNT)


def _process_security(process: dict, root: dict, container: dict, metadata: dict) -> None:
    leader = next(task for task in root["tasks"] if task["tid"] == root["pid"])
    _require(leader["nsPid"] == [root["pid"], 1] and leader["nsTgid"] == [root["pid"], 1],
             IsolationCode.UNSAFE_PROCESS)
    process_leader = next(task for task in process["tasks"] if task["tid"] == process["pid"])
    for task in process["tasks"]:
        _integer(task["startTicks"], 1)
        _require(task["uids"] == [1000] * 4 and task["gids"] == [task["gids"][0]] * 4,
                 IsolationCode.UNSAFE_PROCESS)
        # A homogeneous numeric GID is pinned, rather than restricted to 1000:
        # it grants no host path authority across the fully proved boundary.
        _require(task["noNewPrivs"] == 1 and all(task[key] == 0 for key in _CAPABILITIES[:4]),
                 IsolationCode.UNSAFE_PROCESS)
        # A nonzero default Docker CapBnd cannot grant capabilities under NNP with
        # empty permitted/inheritable/ambient sets; it is deliberately permitted.
        _require(len(task["nsPid"]) == 2 and len(task["nsTgid"]) == 2
                 and task["nsTgid"] == process_leader["nsPid"]
                 and task["containerId"] == container["Id"],
                 IsolationCode.UNSAFE_PROCESS)
        cgroup_path = ("/system.slice/docker-" + container["Id"] + ".scope"
                       if metadata["cgroupDriver"] == "systemd" else "/docker/" + container["Id"])
        _require(task["cgroupPath"] == cgroup_path, IsolationCode.UNSAFE_PROCESS)
        if "namespaceProof" in metadata:
            namespace = metadata["namespaceProof"]
            _require(task["pidNamespace"] == leader["pidNamespace"] != namespace["hostPidNamespace"]
                     and task["mountNamespace"] == leader["mountNamespace"] != namespace["hostMountNamespace"],
                     IsolationCode.UNSAFE_PROCESS)


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
                         IsolationCode.UNSAFE_PROCESS)
                if pid == policy["currentPid"]:
                    _require(uid == 1001 and all(task[key] == 0 for key in _CAPABILITIES[:4]),
                             IsolationCode.UNSAFE_PROCESS)
    return owned


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
        _require(bool(owners), IsolationCode.UNKNOWN_AUTHORITY_PEER)
        _require(len(owners) == 1, IsolationCode.AMBIGUOUS_OWNERSHIP)
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
                _require(previous == task["tid"], IsolationCode.AMBIGUOUS_OWNERSHIP)
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
    try:
        trusted_policy = _policy(policy)
        previous = _snapshot(before)
        current = _snapshot(after)
        previous_cohort = _prove(previous, trusted_policy)
        current_cohort = _prove(current, trusted_policy)
        _require(previous_cohort == current_cohort, IsolationCode.IDENTITY_CHANGED)
        result = IsolationResult(True, IsolationCode.ALLOWED)
    except _Denied as denied:
        result = IsolationResult(False, denied.code)
    except (KeyError, TypeError, ValueError, OverflowError, StopIteration, RecursionError):
        result = IsolationResult(False, IsolationCode.INVALID_SNAPSHOT)
    return result
