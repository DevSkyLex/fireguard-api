"""Hermetic isolation proofs: no host /proc, Docker, secrets, network or lock writes."""

import ast
import copy
from dataclasses import asdict
from datetime import datetime, timezone
import importlib.util
import json
from pathlib import Path
import sys
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[2]
HELPER = ROOT / "bin/fireguard-deployment-isolation.py"
SPEC = importlib.util.spec_from_file_location("deployment_isolation", HELPER)
ISOLATION = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = ISOLATION
SPEC.loader.exec_module(ISOLATION)
CODE = ISOLATION.IsolationCode
CONTAINER = "a" * 64
OTHER = "b" * 64
BOOT_TIME = 1_790_980_000
DEFAULT_DOCKER_BOUNDING = 0xA80425FB


def task(tid, tgid, ppid, start, *, owner=None, uid=0, inner=None, inner_group=None):
    return {"tid": tid, "tgid": tgid, "ppid": ppid, "startTicks": start,
            "uids": [uid] * 4, "gids": [uid] * 4, "groups": [uid],
            "capEff": 0, "capPrm": 0, "capInh": 0, "capAmb": 0,
            "capBnd": DEFAULT_DOCKER_BOUNDING, "noNewPrivs": 1,
            "nsPid": [tid] if inner is None else [tid, inner],
            "nsTgid": [tgid] if inner is None else [tgid, inner if inner_group is None else inner_group],
            "containerId": owner, "cgroupsCoherent": True,
            "cgroupPath": "/system.slice/docker-" + owner + ".scope" if owner else "/"}


def process(pid, ppid, start, *, inner=None, owner=None, uid=0):
    return {"pid": pid, "ppid": ppid, "startTicks": start, "tasksComplete": True,
            "tasks": [task(pid, pid, ppid, start, owner=owner, uid=uid, inner=inner)]}


def volume(name):
    return {"Name": name, "Driver": "local", "Scope": "local", "OptionsEmpty": True,
            "Mountpoint": "/var/lib/docker/volumes/" + name + "/_data"}


def container(identifier=CONTAINER, pid=100):
    started = datetime.fromtimestamp(BOOT_TIME + 100, timezone.utc).isoformat().replace("+00:00", "Z")
    return {"Id": identifier, "Image": "sha256:" + "c" * 64, "RootFS": {"Type": "layers"},
            "State": {"Pid": pid, "StartedAt": started, "Running": True, "Restarting": False, "Paused": False},
            "HostConfig": {"PidMode": "", "UsernsMode": "", "Runtime": "runc", "Privileged": False,
                           "CgroupParent": "",
                           "NetworkMode": "fireguard-production", "IpcMode": "private", "UTSMode": "",
                           "CapAddEmpty": True, "DevicesEmpty": True, "DeviceRequestsEmpty": True,
                           "DeviceCgroupRulesEmpty": True, "VolumesFromEmpty": True, "TmpfsEmpty": True,
                           "MountDeclarationsComplete": True,
                           "MountDeclarations": [
                               {"Type": "volume", "Name": "prod_app_var", "Destination": "/var/www/html/var",
                                "RW": True, "OptionsDefault": True},
                               {"Type": "volume", "Name": "prod_jwt_keys", "Destination": "/var/www/html/config/jwt",
                                "RW": False, "OptionsDefault": True}],
                           "SecurityOpt": ["no-new-privileges:true"]},
            "Mounts": [{"Type": "volume", "Name": "prod_app_var", "Driver": "local",
                        "Source": volume("prod_app_var")["Mountpoint"], "Destination": "/var/www/html/var", "RW": True},
                       {"Type": "volume", "Name": "prod_jwt_keys", "Driver": "local",
                        "Source": volume("prod_jwt_keys")["Mountpoint"], "Destination": "/var/www/html/config/jwt", "RW": False}]}


def proof():
    policy = {"authorityUid": 1000, "authorityGids": [1000],
              "protectedPaths": ["/srv/apps/fireguard"],
              "developmentStoragePaths": ["/var/lib/docker/volumes/dev_app_var/_data"],
              "allowedVolumeMounts": [{"Name": "prod_app_var", "Destination": "/var/www/html/var", "RW": True},
                                      {"Name": "prod_jwt_keys", "Destination": "/var/www/html/config/jwt", "RW": False}]}
    init = process(100, 2, 10000, owner=CONTAINER, uid=1000, inner=1)
    child = process(110, 100, 10005, owner=CONTAINER, uid=1000, inner=7)
    child["tasks"].append(task(111, 110, 100, 10006, owner=CONTAINER, uid=1000, inner=8, inner_group=7))
    snapshot = {"complete": True, "proofMode": "trusted-docker-runc-private-pid",
                "dockerApiVersion": "1.48",
                "cgroupDriver": "systemd",
                "bootId": "12345678-1234-1234-1234-123456789abc",
                "bootTimeSeconds": BOOT_TIME, "clockTicks": 100,
                "processes": [process(1, 0, 1), process(2, 1, 20), init, child],
                "containers": [container()], "volumes": [volume("prod_app_var"), volume("prod_jwt_keys")]}
    return policy, snapshot


class DeploymentIsolationTest(unittest.TestCase):
    def setUp(self):
        self.policy, self.before = proof()

    def validate(self, *, after=None):
        return ISOLATION.validate_isolation(self.policy, self.before,
                                             copy.deepcopy(self.before) if after is None else after)

    def assertDenied(self, code=None, *, after=None):
        result = self.validate(after=after)
        self.assertFalse(result.allowed)
        self.assertIsInstance(result.code, CODE)
        if code is not None:
            self.assertEqual(code, result.code)

    def test_actual_default_capability_bounding_set_and_empty_userns_are_allowed(self):
        self.assertGreater(DEFAULT_DOCKER_BOUNDING, 0)
        self.assertEqual("", self.before["containers"][0]["HostConfig"]["UsernsMode"])
        self.assertEqual(ISOLATION.IsolationResult(True, CODE.ALLOWED), self.validate())

    def test_uid_1000_with_other_homogeneous_gid_requires_the_same_complete_proof(self):
        for gid in (0, 1001, 2000):
            with self.subTest(gid=gid):
                _, snapshot = proof()
                for entry in snapshot["processes"][2:]:
                    for peer in entry["tasks"]:
                        peer["gids"] = [gid] * 4
                        peer["groups"] = [gid]
                self.assertTrue(ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).allowed)
                for key, value in (("capPrm", 1), ("noNewPrivs", 0), ("cgroupPath", "/user.slice/pretend")):
                    with self.subTest(missing_proof=key):
                        invalid = copy.deepcopy(snapshot)
                        invalid["processes"][-1]["tasks"][-1][key] = value
                        self.assertEqual(CODE.UNSAFE_PROCESS,
                                         ISOLATION.validate_isolation(self.policy, invalid, copy.deepcopy(invalid)).code)
                invalid = copy.deepcopy(snapshot)
                invalid["volumes"][0]["OptionsEmpty"] = False
                self.assertEqual(CODE.UNSAFE_VOLUME,
                                 ISOLATION.validate_isolation(self.policy, invalid, copy.deepcopy(invalid)).code)

    def test_other_homogeneous_gid_is_pinned_and_mixed_saved_fs_gids_are_denied(self):
        after = copy.deepcopy(self.before)
        after["processes"][-1]["tasks"][-1]["gids"] = [1001] * 4
        self.assertDenied(CODE.IDENTITY_CHANGED, after=after)
        for index in range(4):
            with self.subTest(index=index):
                _, snapshot = proof()
                peer = snapshot["processes"][-1]["tasks"][-1]
                peer["gids"] = [1001] * 4
                peer["gids"][index] = 1000
                self.assertEqual(CODE.UNSAFE_PROCESS,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_credentials_must_be_valid_linux_uint32_identities(self):
        for field, value in (("uids", [1 << 32] * 4), ("gids", [1 << 40] * 4), ("groups", [1 << 32])):
            with self.subTest(field=field):
                _, snapshot = proof()
                snapshot["processes"][-1]["tasks"][-1][field] = value
                self.assertEqual(CODE.INVALID_SNAPSHOT,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_no_named_volume_web_container_is_allowed_with_complete_mount_proof(self):
        self.before["containers"][0]["Mounts"] = []
        self.before["containers"][0]["HostConfig"]["MountDeclarations"] = []
        self.before["volumes"] = []
        self.policy["allowedVolumeMounts"] = []
        self.assertTrue(self.validate().allowed)

    def test_no_authority_peers_needs_complete_host_inventory(self):
        self.before["processes"] = self.before["processes"][:2]
        self.before["containers"] = []
        self.before["volumes"] = []
        self.assertTrue(self.validate().allowed)
        self.before["processes"] = []
        self.assertDenied(CODE.INCOMPLETE_INVENTORY)

    def test_unrelated_root_process_thread_and_container_churn_is_permitted(self):
        after = copy.deepcopy(self.before)
        unrelated = process(200, 2, 20000, uid=2000, owner=OTHER, inner=1)
        after["processes"].append(unrelated)
        after["containers"].append(container(OTHER, 200))
        after["volumes"].append(volume("unrelated_volume"))
        self.assertTrue(self.validate(after=after).allowed)
        self.before = after
        after = copy.deepcopy(self.before)
        after["processes"][-1]["tasks"].append(task(201, 200, 2, 20001, uid=2000,
                                                      owner=OTHER, inner=9, inner_group=1))
        self.assertTrue(self.validate(after=after).allowed)

    def test_irrelevant_kernel_threads_with_zero_start_ticks_are_valid_metadata(self):
        for entry in self.before["processes"][:2]:
            entry["startTicks"] = 0
            entry["tasks"][0]["startTicks"] = 0
        self.before["processes"].append(process(200, 2, 0))
        self.assertTrue(self.validate().allowed)

    def test_relevant_container_init_start_ticks_are_pinned_even_without_absolute_wall_clock_bound(self):
        after = copy.deepcopy(self.before)
        after["processes"][2]["startTicks"] = 10001
        after["processes"][2]["tasks"][0]["startTicks"] = 10001
        self.assertDenied(CODE.IDENTITY_CHANGED, after=after)

    def test_own_host_ancestry_with_authority_supplementary_group_is_exempt_and_pinned(self):
        own = process(300, 2, 30000, uid=1001)
        own["tasks"][0]["groups"].append(1000)
        self.before["processes"].append(own)
        self.policy.update({"currentPid": 300, "currentUid": 1001})
        self.assertTrue(self.validate().allowed)
        after = copy.deepcopy(self.before)
        after["processes"][-1]["tasks"][0]["groups"].remove(1000)
        self.assertDenied(CODE.IDENTITY_CHANGED, after=after)

    def test_own_ancestry_never_exempts_mixed_saved_fs_credentials_or_other_group_peers(self):
        own = process(300, 2, 30000, uid=1001)
        own["tasks"][0]["groups"].append(1000)
        self.before["processes"].append(own)
        self.policy.update({"currentPid": 300, "currentUid": 1001})
        for field in ("uids", "gids"):
            for index in range(4):
                with self.subTest(field=field, index=index):
                    before = copy.deepcopy(self.before)
                    before["processes"][-1]["tasks"][0][field][index] = 1000
                    result = ISOLATION.validate_isolation(self.policy, before, copy.deepcopy(before))
                    self.assertEqual(CODE.UNSAFE_PROCESS, result.code)
        other = process(301, 2, 30001, uid=1001)
        other["tasks"][0]["groups"].append(1000)
        self.before["processes"].append(other)
        self.assertDenied(CODE.UNKNOWN_AUTHORITY_PEER)

    def test_arbitrary_pid_allowlist_and_wrong_own_identity_never_bypass_unknown_authority(self):
        self.before["processes"].append(process(300, 2, 30000, uid=1000))
        self.policy["exemptPids"] = [300]
        self.assertDenied(CODE.UNKNOWN_AUTHORITY_PEER)
        self.policy.update({"currentPid": 300, "currentUid": 1001})
        self.assertDenied(CODE.UNSAFE_PROCESS)

    def test_cgroup_identity_must_be_exact_and_independent_of_parent_or_name(self):
        for path in ("/user.slice/docker-" + CONTAINER + ".scope", "/docker/" + CONTAINER,
                     "/system.slice/docker-" + OTHER + ".scope", "/system.slice/docker-" + CONTAINER + ".scope/child"):
            with self.subTest(path=path):
                before = copy.deepcopy(self.before)
                before["processes"][-1]["tasks"][-1]["cgroupPath"] = path
                result = ISOLATION.validate_isolation(self.policy, before, copy.deepcopy(before))
                self.assertEqual(CODE.UNSAFE_PROCESS, result.code)
        self.before["processes"][-1]["tasks"][-1]["cgroupsCoherent"] = False
        self.assertDenied(CODE.INCOMPLETE_INVENTORY)

    def test_known_cgroupfs_driver_has_a_different_exact_default_identity_path(self):
        self.before["cgroupDriver"] = "cgroupfs"
        for entry in self.before["processes"]:
            for peer in entry["tasks"]:
                if peer["containerId"]:
                    peer["cgroupPath"] = "/docker/" + peer["containerId"]
        self.assertTrue(self.validate().allowed)
        self.before["cgroupDriver"] = "unknown"
        self.assertDenied(CODE.INVALID_SNAPSHOT)

    def test_unknown_host_authority_process_cannot_be_whitelisted_by_name_or_labels(self):
        peer = process(200, 2, 20000, uid=1000)
        peer.update({"comm": "node", "name": "fireguard-web", "labels": {"trusted": "yes"}})
        self.before["processes"].append(peer)
        self.assertDenied(CODE.UNKNOWN_AUTHORITY_PEER)

    def test_supplementary_group_and_each_uid_gid_quartet_slot_trigger_authority(self):
        for field in ("uids", "gids", "groups"):
            for index in range(4 if field != "groups" else 1):
                with self.subTest(field=field, index=index):
                    _, snapshot = proof()
                    peer = process(200, 2, 20000, uid=2000)
                    peer["tasks"][0][field][index] = 1000
                    snapshot["processes"].append(peer)
                    result = ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot))
                    self.assertEqual(CODE.UNKNOWN_AUTHORITY_PEER, result.code)

    def test_authority_thread_hidden_by_unprivileged_leader_still_blocks(self):
        peer = process(200, 2, 20000, uid=2000)
        peer["tasks"].append(task(201, 200, 2, 20001, uid=1000))
        self.before["processes"].append(peer)
        self.assertDenied(CODE.UNKNOWN_AUTHORITY_PEER)

    def test_unsafe_individual_thread_cannot_hide_behind_safe_process_leader(self):
        for key, value in (("capEff", 1), ("capPrm", 1), ("capInh", 1), ("capAmb", 1),
                           ("noNewPrivs", 0), ("containerId", OTHER), ("nsTgid", [110, 99]),
                           ("nsPid", [111]), ("uids", [1000, 0, 1000, 1000]),
                           ("gids", [1000, 1000, 1000, 0])):
            with self.subTest(key=key):
                _, snapshot = proof()
                snapshot["processes"][-1]["tasks"][-1][key] = value
                result = ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot))
                self.assertEqual(CODE.UNSAFE_PROCESS, result.code)

    def test_incomplete_process_thread_inventory_blocks(self):
        for field in ("complete", "tasksComplete"):
            with self.subTest(field=field):
                _, snapshot = proof()
                target = snapshot if field == "complete" else snapshot["processes"][-1]
                target[field] = False
                result = ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot))
                self.assertEqual(CODE.INCOMPLETE_INVENTORY, result.code)

    def test_malformed_or_missing_metadata_fails_closed_without_exception(self):
        for path in (("containers", 0, "State", "StartedAt"), ("containers", 0, "HostConfig", "MountDeclarationsComplete"),
                     ("containers", 0, "Mounts"), ("volumes", 0, "OptionsEmpty"),
                     ("processes", -1, "tasks", -1, "noNewPrivs"), ("processes", -1, "tasksComplete")):
            with self.subTest(path=path):
                _, snapshot = proof()
                target = snapshot
                for part in path[:-1]:
                    target = target[part]
                del target[path[-1]]
                result = ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot))
                self.assertEqual(CODE.INVALID_SNAPSHOT, result.code)
        for invalid in (None, [], {}, "private secret", 1000, False):
            self.assertEqual(CODE.INVALID_SNAPSHOT, ISOLATION.validate_isolation(self.policy, invalid, invalid).code)

    def test_json_wrong_scalar_types_are_not_coerced_into_proofs(self):
        for path, value in ((("containers", 0, "State", "Running"), "true"),
                            (("containers", 0, "HostConfig", "Privileged"), 0),
                            (("containers", 0, "HostConfig", "CapAddEmpty"), 1),
                            (("volumes", 0, "OptionsEmpty"), "true"),
                            (("processes", -1, "tasks", -1, "capEff"), "0000000000000000"),
                            (("processes", -1, "tasks", -1, "noNewPrivs"), True),
                            (("processes", -1, "tasks", -1, "uids"), [1000, 1000, 1000]),
                            (("clockTicks",), True)):
            with self.subTest(path=path):
                _, snapshot = proof()
                target = snapshot
                for part in path[:-1]:
                    target = target[part]
                target[path[-1]] = value
                self.assertFalse(ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).allowed)

    def test_all_container_host_authority_controls_must_be_explicit_and_safe(self):
        for key, value in (("PidMode", "host"), ("PidMode", "container:" + OTHER),
                           ("UsernsMode", "host"), ("Privileged", True), ("CapAddEmpty", False),
                           ("DevicesEmpty", False), ("DeviceRequestsEmpty", False),
                           ("DeviceCgroupRulesEmpty", False), ("VolumesFromEmpty", False), ("TmpfsEmpty", False),
                           ("Runtime", "custom"), ("NetworkMode", "host"), ("NetworkMode", "container:" + OTHER),
                           ("CgroupParent", "user.slice"),
                           ("IpcMode", "host"), ("UTSMode", "host"),
                           ("SecurityOpt", []), ("SecurityOpt", ["no-new-privileges:false"]),
                           ("SecurityOpt", ["no-new-privileges:true", "seccomp=unconfined"])):
            with self.subTest(key=key, value=value):
                _, snapshot = proof()
                snapshot["containers"][0]["HostConfig"][key] = value
                self.assertEqual(CODE.UNSAFE_CONTAINER,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_container_running_paused_and_restart_state_must_be_typed_and_stable(self):
        for key, value in (("Running", False), ("Paused", True), ("Restarting", True)):
            with self.subTest(key=key):
                _, snapshot = proof()
                snapshot["containers"][0]["State"][key] = value
                self.assertEqual(CODE.UNSAFE_CONTAINER,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_pid_reuse_and_container_restart_are_rejected(self):
        for path, value in ((("processes", -1, "startTicks"), 10007),
                            (("processes", -1, "tasks", -1, "startTicks"), 10007),
                            (("containers", 0, "State", "StartedAt"), "2026-10-04T01:00:00Z"),
                            (("containers", 0, "State", "Pid"), 110),
                            (("containers", 0, "Image"), "sha256:" + "d" * 64),
                            (("bootId",), "87654321-1234-1234-1234-123456789abc")):
            with self.subTest(path=path):
                after = copy.deepcopy(self.before)
                target = after
                for part in path[:-1]:
                    target = target[part]
                target[path[-1]] = value
                if path == ("processes", -1, "startTicks"):
                    target["tasks"][0]["startTicks"] = value
                    target["tasks"][1]["startTicks"] = value + 1
                self.assertDenied(after=after)

    def test_fresh_process_or_thread_added_before_release_requires_new_proof(self):
        after = copy.deepcopy(self.before)
        after["processes"].append(process(200, 2, 20000, uid=1000))
        self.assertDenied(CODE.UNKNOWN_AUTHORITY_PEER, after=after)
        after = copy.deepcopy(self.before)
        after["processes"][-1]["tasks"].append(task(112, 110, 100, 10007, owner=CONTAINER,
                                                   uid=1000, inner=9, inner_group=7))
        self.assertDenied(CODE.IDENTITY_CHANGED, after=after)

    def test_process_ownership_and_security_changes_before_release_are_rejected(self):
        for key, value in (("ppid", 2), ("containerId", OTHER), ("capEff", 1), ("noNewPrivs", 0)):
            with self.subTest(key=key):
                after = copy.deepcopy(self.before)
                if key == "ppid":
                    after["processes"][-1][key] = value
                    for entry in after["processes"][-1]["tasks"]:
                        entry[key] = value
                else:
                    after["processes"][-1]["tasks"][-1][key] = value
                self.assertDenied(after=after)

    def test_docker_start_delay_is_not_an_absolute_kernel_identity_proof(self):
        self.before["processes"][2]["startTicks"] = 9990
        self.before["processes"][2]["tasks"][0]["startTicks"] = 9990
        self.assertTrue(self.validate().allowed, "A subsecond Docker start delay is accepted")
        self.before["processes"][2]["startTicks"] = 9900
        self.before["processes"][2]["tasks"][0]["startTicks"] = 9900
        self.assertTrue(self.validate().allowed)
        self.before["processes"][2]["startTicks"] = 9800
        self.before["processes"][2]["tasks"][0]["startTicks"] = 9800
        self.assertTrue(self.validate().allowed, "Native Docker may record startupTime before creating its task")

    def test_native_init_creation_after_recorded_docker_start_is_allowed(self):
        self.before["processes"][2]["startTicks"] = 10001
        self.before["processes"][2]["tasks"][0]["startTicks"] = 10001
        self.assertTrue(self.validate().allowed)

    def test_container_start_timestamp_must_still_be_valid_docker_metadata(self):
        self.before["containers"][0]["State"]["StartedAt"] = "not-a-timestamp"
        self.assertDenied(CODE.INVALID_SNAPSHOT)

    def test_duplicate_init_pid_and_nested_docker_ownership_are_ambiguous(self):
        self.before["containers"].append(container(OTHER, 100))
        self.assertDenied(CODE.AMBIGUOUS_OWNERSHIP)
        _, self.before = proof()
        self.before["containers"].append(container(OTHER, 110))
        self.assertDenied(CODE.AMBIGUOUS_OWNERSHIP)

    def test_private_namespace_thread_ids_must_have_unique_host_ownership(self):
        self.before["processes"][-1]["tasks"][-1]["nsPid"] = [111, 7]
        self.assertDenied(CODE.AMBIGUOUS_OWNERSHIP)

    def test_incomplete_cyclic_or_reversed_age_ancestry_is_denied(self):
        for parent in (999, 110):
            with self.subTest(parent=parent):
                _, snapshot = proof()
                snapshot["processes"][2]["ppid"] = parent
                snapshot["processes"][2]["tasks"][0]["ppid"] = parent
                self.assertEqual(CODE.INVALID_ANCESTRY,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)
        self.before["processes"][1]["startTicks"] = 10001
        self.before["processes"][1]["tasks"][0]["startTicks"] = 10001
        self.assertDenied(CODE.INVALID_ANCESTRY)

    def test_init_must_actually_be_pid_one_in_private_kernel_namespaces(self):
        for key, value in (("nsPid", [100, 2]), ("nsTgid", [100, 2])):
            with self.subTest(key=key):
                _, snapshot = proof()
                snapshot["processes"][2]["tasks"][0][key] = value
                self.assertEqual(CODE.UNSAFE_PROCESS,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_explicit_docker_proof_mode_cannot_be_replaced_by_userns_or_missing_inode_claim(self):
        self.before["proofMode"] = "private-userns-assumed"
        self.assertDenied(CODE.INVALID_SNAPSHOT)

    def test_effectively_used_api_version_must_cover_required_metadata_fields(self):
        self.before["dockerApiVersion"] = "1.45"
        self.assertTrue(self.validate().allowed)
        for version in ("1.25", "1.40", "1.44", "2.0", "1.045", "server maximum 1.48", None):
            with self.subTest(version=version):
                self.before["dockerApiVersion"] = version
                self.assertDenied(CODE.INVALID_SNAPSHOT)
        del self.before["dockerApiVersion"]
        self.assertDenied(CODE.INVALID_SNAPSHOT)

    def test_optional_namespace_proof_is_corroborated_and_cannot_silently_disagree(self):
        self.before["namespaceProof"] = {"hostPidNamespace": 10, "hostMountNamespace": 20}
        for entry in self.before["processes"]:
            for peer in entry["tasks"]:
                peer["pidNamespace"] = 30 if peer["containerId"] else 10
                peer["mountNamespace"] = 40 if peer["containerId"] else 20
        self.assertTrue(self.validate().allowed)
        self.before["processes"][-1]["tasks"][-1]["mountNamespace"] = 20
        self.assertDenied(CODE.UNSAFE_PROCESS)
        del self.before["namespaceProof"]
        self.assertDenied(CODE.INVALID_SNAPSHOT)

    def test_mount_declarations_cannot_hide_binds_options_or_effective_mismatch(self):
        for key, value in (("Type", "bind"), ("OptionsDefault", False), ("OptionsDefault", 1),
                           ("Name", "unknown"), ("Destination", "/srv/apps"), ("RW", False)):
            with self.subTest(key=key):
                _, snapshot = proof()
                snapshot["containers"][0]["HostConfig"]["MountDeclarations"][0][key] = value
                self.assertEqual(CODE.UNSAFE_MOUNT,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)
        self.before["containers"][0]["HostConfig"]["MountDeclarationsComplete"] = False
        self.assertDenied(CODE.INCOMPLETE_INVENTORY)
        self.before["containers"][0]["HostConfig"]["MountDeclarationsComplete"] = True
        self.before["containers"][0]["HostConfig"]["MountDeclarations"] = []
        self.assertDenied(CODE.UNSAFE_MOUNT)

    def test_missing_or_empty_physical_path_policy_is_not_an_isolation_proof(self):
        for key in ("protectedPaths", "developmentStoragePaths"):
            with self.subTest(key=key):
                policy = copy.deepcopy(self.policy)
                policy[key] = []
                self.assertEqual(CODE.INVALID_SNAPSHOT,
                                 ISOLATION.validate_isolation(policy, self.before, copy.deepcopy(self.before)).code)

    def test_named_volume_driver_scope_and_options_proof_are_required(self):
        for key, value in (("Driver", "plugin"), ("Scope", "global"), ("OptionsEmpty", False),
                           ("Mountpoint", "/var/lib/docker/volumes/other/_data")):
            with self.subTest(key=key):
                _, snapshot = proof()
                snapshot["volumes"][0][key] = value
                self.assertEqual(CODE.UNSAFE_VOLUME,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_local_bind_volume_options_cannot_be_hidden_by_innocent_mount_source(self):
        self.before["volumes"][0]["OptionsEmpty"] = False
        # The caller reports only a nonempty flag, never device/credentials/options.
        self.assertEqual("/var/lib/docker/volumes/prod_app_var/_data",
                         self.before["containers"][0]["Mounts"][0]["Source"])
        self.assertDenied(CODE.UNSAFE_VOLUME)

    def test_host_bind_tmpfs_custom_mount_and_anonymous_volume_are_denied(self):
        for key, value in (("Type", "bind"), ("Type", "tmpfs"), ("Type", "cluster"),
                           ("Driver", "plugin"), ("Name", "unknown"), ("Name", ""),
                           ("Destination", "/var/run/docker.sock"), ("RW", False)):
            with self.subTest(key=key):
                _, snapshot = proof()
                snapshot["containers"][0]["Mounts"][0][key] = value
                self.assertFalse(ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).allowed)

    def test_no_volume_snapshot_or_duplicate_mount_destination_fails_closed(self):
        self.before["volumes"] = []
        self.assertDenied(CODE.UNSAFE_VOLUME)
        _, self.before = proof()
        self.before["containers"][0]["Mounts"].append(copy.deepcopy(self.before["containers"][0]["Mounts"][0]))
        self.assertDenied(CODE.UNSAFE_MOUNT)

    def test_app_ancestors_descendants_and_development_storage_overlap_are_denied(self):
        for source in ("/", "/srv", "/srv/apps", "/srv/apps/fireguard", "/srv/apps/fireguard/data",
                       "/var/lib/docker/volumes", "/var/lib/docker/volumes/dev_app_var/_data",
                       "/var/lib/docker/volumes/dev_app_var/_data/cache"):
            with self.subTest(source=source):
                _, snapshot = proof()
                snapshot["containers"][0]["Mounts"][0]["Source"] = source
                snapshot["volumes"][0]["Mountpoint"] = source
                self.assertEqual(CODE.UNSAFE_VOLUME,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_physical_path_overlap_uses_components_and_requires_canonical_paths(self):
        for source in ("/srv/apps/fireguard-elsewhere", "/var/lib/docker/volumes/dev_app_var/_data-elsewhere"):
            with self.subTest(source=source):
                _, snapshot = proof()
                snapshot["containers"][0]["Mounts"][0]["Source"] = source
                snapshot["volumes"][0]["Mountpoint"] = source
                self.assertTrue(ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).allowed)
        for source in ("relative/path", "/srv/apps/../apps/fireguard", "//srv/apps", "/srv/apps/", "/srv/\x00apps"):
            with self.subTest(source=source):
                _, snapshot = proof()
                snapshot["containers"][0]["Mounts"][0]["Source"] = source
                self.assertEqual(CODE.INVALID_SNAPSHOT,
                                 ISOLATION.validate_isolation(self.policy, snapshot, copy.deepcopy(snapshot)).code)

    def test_volume_mount_or_security_metadata_changes_require_new_proof(self):
        for path, value in ((("volumes", 0, "OptionsEmpty"), False),
                            (("containers", 0, "Mounts", 0, "RW"), False),
                            (("containers", 0, "HostConfig", "Privileged"), True)):
            with self.subTest(path=path):
                after = copy.deepcopy(self.before)
                target = after
                for part in path[:-1]:
                    target = target[part]
                target[path[-1]] = value
                self.assertDenied(after=after)

    def test_diagnostics_have_only_fixed_enum_and_boolean_and_never_echo_inputs(self):
        private = "secret-option-value-must-not-leave-proof"
        self.before["volumes"][0]["OptionsEmpty"] = private
        result = self.validate()
        self.assertEqual({"allowed": False, "code": CODE.INVALID_SNAPSHOT,
                          "predicate": ISOLATION.IsolationPredicate.VOLUME_OPTIONS,
                          "section": ISOLATION.IsolationSection.VOLUME,
                          "phase": ISOLATION.IsolationPhase.BEFORE, "authority_peer": None, "security_profile": None}, asdict(result))
        serialized = json.dumps(asdict(result))
        self.assertNotIn(private, serialized)
        self.assertNotIn("/srv", serialized)
        self.assertNotIn(CONTAINER, serialized)

    def test_invalid_namespace_reports_known_authority_with_raw_duplicate_groups(self):
        for uid, gids, groups, authority in ((1000, [1000] * 4, [1000, 1000], True),
                                            (2000, [2000] * 4, [2000, 2000], False),
                                            (2000, [2000] * 4, [1000, 1000], True)):
            for phase in ("before", "after"):
                with self.subTest(uid=uid, phase=phase):
                    _, before = proof()
                    after = copy.deepcopy(before)
                    peer = (before if phase == "before" else after)["processes"][-1]["tasks"][-1]
                    peer.update(uids=[uid] * 4, gids=gids, groups=groups)
                    peer["nsPid"] = [peer["tid"], 0]
                    result = ISOLATION.validate_isolation(self.policy, before, after)
                    self.assertEqual(CODE.INVALID_SNAPSHOT, result.code)
                    self.assertEqual({"predicate": "task-nspid-positive", "section": "task",
                                      "phase": phase, "authorityPeer": authority}, result.diagnostic())
                    self.assertEqual(groups, peer["groups"])

    def test_kernel_duplicate_groups_are_valid_for_unrelated_and_fully_isolated_tasks(self):
        for relevant in (False, True):
            with self.subTest(relevant=relevant):
                _, before = proof()
                peer = before["processes"][3 if relevant else 0]["tasks"][-1]
                peer["groups"] *= 2
                original = copy.deepcopy(before)
                self.assertEqual([1000, 1000] if relevant else [0, 0], peer["groups"])
                self.assertTrue(ISOLATION.validate_isolation(self.policy, before, copy.deepcopy(before)).allowed)
                self.assertEqual(original, before)

    def test_raw_duplicate_multiplicity_is_pinned_for_the_authority_cohort(self):
        before = copy.deepcopy(self.before)
        before["processes"][-1]["tasks"][-1]["groups"] = [1000, 1000]
        self.assertTrue(ISOLATION.validate_isolation(self.policy, before, copy.deepcopy(before)).allowed)
        after = copy.deepcopy(before)
        after["processes"][-1]["tasks"][-1]["groups"] = [1000]
        result = ISOLATION.validate_isolation(self.policy, before, after)
        self.assertEqual(CODE.IDENTITY_CHANGED, result.code)
        self.assertEqual("cohort-stable", result.diagnostic()["predicate"])
        self.assertEqual([1000, 1000], before["processes"][-1]["tasks"][-1]["groups"])

    def test_duplicate_membership_never_exempts_a_new_host_authority_peer(self):
        before = copy.deepcopy(self.before)
        peer = process(200, 2, 20000, uid=2000)
        peer["tasks"][0]["groups"] = [2000, 2000]
        before["processes"].append(peer)
        self.assertTrue(ISOLATION.validate_isolation(self.policy, before, copy.deepcopy(before)).allowed)
        after = copy.deepcopy(before)
        after["processes"][-1]["tasks"][0]["groups"] = [2000, 1000, 1000]
        result = ISOLATION.validate_isolation(self.policy, before, after)
        self.assertEqual(CODE.UNKNOWN_AUTHORITY_PEER, result.code)
        self.assertEqual("prove-after", result.diagnostic()["phase"])
        uncontained = copy.deepcopy(self.before)
        host_peer = process(200, 2, 20000, uid=1000)
        host_peer["tasks"][0]["groups"] = [1000, 1000]
        uncontained["processes"].append(host_peer)
        self.assertEqual(CODE.UNKNOWN_AUTHORITY_PEER,
                         ISOLATION.validate_isolation(self.policy, uncontained, copy.deepcopy(uncontained)).code)

    def test_malformed_shape_predicates_are_exact_and_phase_specific(self):
        cases = (
            (("dockerApiVersion",), "1.44", "snapshot", "snapshot-docker-api"),
            (("bootId",), "private-canary", "snapshot", "snapshot-boot-id"),
            (("bootTimeSeconds",), 0, "snapshot", "snapshot-boot-time"),
            (("clockTicks",), False, "snapshot", "snapshot-clock-ticks"),
            (("processes", 3, "pid"), 0, "process", "process-pid"),
            (("processes", 3, "ppid"), -1, "process", "process-ppid"),
            (("processes", 3, "startTicks"), "private-canary", "process", "process-start-ticks"),
            (("processes", 3, "tasks", 1, "tid"), 0, "task", "task-tid"),
            (("processes", 3, "tasks", 1, "tgid"), 999, "task", "task-tgid"),
            (("processes", 3, "tasks", 1, "ppid"), 999, "task", "task-ppid"),
            (("processes", 3, "tasks", 1, "startTicks"), -1, "task", "task-start-ticks"),
            (("processes", 3, "tasks", 1, "startTicks"), 1, "task", "task-start-order"),
            (("processes", 3, "tasks", 1, "uids"), [1000], "task", "task-uids"),
            (("processes", 3, "tasks", 1, "gids"), ["private-canary"] * 4, "task", "task-gids"),
            (("processes", 3, "tasks", 1, "groups"), [1 << 32], "task", "task-groups"),
            (("processes", 3, "tasks", 1, "capBnd"), 1 << 64, "task", "task-capabilities"),
            (("processes", 3, "tasks", 1, "noNewPrivs"), True, "task", "task-no-new-privs"),
            (("processes", 3, "tasks", 1, "nsPid"), "private-canary", "task", "task-nspid-shape"),
            (("processes", 3, "tasks", 1, "nsPid"), [999, 8], "task", "task-nspid-first"),
            (("processes", 3, "tasks", 1, "nsPid"), [111, 0], "task", "task-nspid-positive"),
            (("processes", 3, "tasks", 1, "nsTgid"), [110, False], "task", "task-nstgid-positive"),
            (("processes", 3, "tasks", 1, "nsTgid"), [], "task", "task-nstgid-first"),
            (("processes", 3, "tasks", 1, "containerId"), "private-canary", "task", "task-container-id"),
            (("processes", 3, "tasks", 1, "cgroupPath"), "private-canary", "task", "task-cgroup-path"),
            (("containers", 0, "Id"), "private-canary", "container", "container-id"),
            (("containers", 0, "Image"), "private-canary", "container", "container-image"),
            (("containers", 0, "State", "Running"), "private-canary", "container", "container-state-shape"),
            (("containers", 0, "State", "Pid"), False, "container", "container-root-pid"),
            (("containers", 0, "State", "StartedAt"), "2026-02-30T00:00:00Z", "container", "container-started-at"),
            (("volumes", 0, "Mountpoint"), "/private-canary/../canary", "volume", "volume-mountpoint"),
            (("volumes", 0, "Driver"), ["private-canary"], "volume", "volume-driver-scope"),
            (("volumes", 0, "OptionsEmpty"), "private-canary", "volume", "volume-options-shape"),
        )
        for path, value, section, predicate in cases:
            for phase in ("before", "after"):
                with self.subTest(path=path, phase=phase):
                    _, before = proof()
                    after = copy.deepcopy(before)
                    target = before if phase == "before" else after
                    for component in path[:-1]:
                        target = target[component]
                    target[path[-1]] = value
                    result = ISOLATION.validate_isolation(self.policy, before, after)
                    self.assertEqual(CODE.INVALID_SNAPSHOT, result.code)
                    self.assertEqual(predicate, result.diagnostic()["predicate"])
                    self.assertEqual(section, result.diagnostic()["section"])
                    self.assertEqual(phase, result.diagnostic()["phase"])
                    self.assertNotIn("private-canary", json.dumps(asdict(result)))

    def test_incomplete_identity_policy_and_proof_denials_keep_their_original_codes(self):
        cases = (
            (("processes", 3, "tasksComplete"), False, CODE.INCOMPLETE_INVENTORY, "process-tasks-complete", "before"),
            (("processes", 3, "tasks"), [], CODE.INCOMPLETE_INVENTORY, "process-tasks", "before"),
            (("processes", 3, "tasks", 0, "startTicks"), 10006, CODE.INVALID_SNAPSHOT, "process-leader-start", "before"),
            (("containers", 0, "State", "Pid"), 1, CODE.AMBIGUOUS_OWNERSHIP, "container-root-pid", "before"),
            (("containers", 0, "RootFS", "Type"), "private-canary", CODE.UNSAFE_CONTAINER, "container-rootfs", "before"),
            (("containers", 0, "HostConfig", "Privileged"), True, CODE.UNSAFE_CONTAINER, "container-privileged", "prove-before"),
            (("processes", 3, "tasks", 1, "noNewPrivs"), 0, CODE.UNSAFE_PROCESS, "process-nnp-capabilities", "prove-before"),
            (("containers", 0, "Mounts", 0, "Type"), "bind", CODE.UNSAFE_MOUNT, "mount-type-driver", "prove-before"),
            (("volumes", 0, "OptionsEmpty"), False, CODE.UNSAFE_VOLUME, "mount-volume-security", "prove-before"),
        )
        for path, value, code, predicate, phase in cases:
            with self.subTest(path=path):
                _, before = proof()
                target = before
                for component in path[:-1]:
                    target = target[component]
                target[path[-1]] = value
                result = ISOLATION.validate_isolation(self.policy, before, copy.deepcopy(before))
                self.assertEqual(code, result.code)
                self.assertEqual(predicate, result.diagnostic()["predicate"])
                self.assertEqual(phase, result.diagnostic()["phase"])
        policy = copy.deepcopy(self.policy)
        policy["authorityGids"] = [1000, 1000]
        result = ISOLATION.validate_isolation(policy, self.before, self.before)
        self.assertEqual({"predicate": "policy-authority-gids", "section": "policy",
                          "phase": "policy", "authorityPeer": None}, result.diagnostic())
        before = copy.deepcopy(self.before)
        before["processes"][-1]["tasks"][-1].update(tid=100, nsPid=[100, 8])
        result = ISOLATION.validate_isolation(self.policy, before, before)
        self.assertEqual(CODE.INVALID_SNAPSHOT, result.code)
        self.assertEqual("process-thread-duplicate", result.diagnostic()["predicate"])
        after = copy.deepcopy(self.before)
        after["bootId"] = "98765432-1234-1234-1234-123456789abc"
        result = ISOLATION.validate_isolation(self.policy, self.before, after)
        self.assertEqual(CODE.IDENTITY_CHANGED, result.code)
        self.assertEqual("cohort-stable", result.diagnostic()["predicate"])
        self.assertEqual("compare", result.diagnostic()["phase"])

    def test_missing_fields_and_unvalidated_credentials_never_guess_authority_or_echo_keys(self):
        before = copy.deepcopy(self.before)
        peer = before["processes"][-1]["tasks"][-1]
        peer["gids"] = ["secret-canary"] * 4
        result = ISOLATION.validate_isolation(self.policy, before, before)
        self.assertIsNone(result.authority_peer)
        del peer["uids"]
        result = ISOLATION.validate_isolation(self.policy, before, before)
        self.assertEqual({"predicate": "missing-field", "section": "task",
                          "phase": "before", "authorityPeer": None}, result.diagnostic())
        self.assertNotIn("secret-canary", json.dumps(asdict(result)))
        self.assertEqual(ISOLATION.IsolationResult(True, CODE.ALLOWED), self.validate())

    def test_public_projection_rejects_untrusted_enum_and_boolean_values(self):
        class Untrusted:
            value = "secret-canary"
        result = ISOLATION.IsolationResult(False, CODE.INVALID_SNAPSHOT, Untrusted(), Untrusted(), Untrusted(), "secret-canary")
        self.assertEqual({"predicate": "none", "section": "none", "phase": "none", "authorityPeer": None}, result.diagnostic())

    def test_missing_nnp_and_recognized_nnp_with_extra_option_keep_distinct_bounded_denials(self):
        for has_nnp, empty, declared, extra in ((False, True, 0, 0), (True, False, 2, 1)):
            for phase in ("prove-before", "prove-after"):
                with self.subTest(has_nnp=has_nnp, phase=phase):
                    before = copy.deepcopy(self.before)
                    after = copy.deepcopy(before)
                    target = before if phase == "prove-before" else after
                    profile = {"hasRecognizedNnp": has_nnp, "optionsEmpty": empty, "declaredOptionsCount": declared,
                               "extraOptionsCount": extra, "projectClass": "api-production-legacy", "serviceClass": "app"}
                    target["containers"][0]["SecurityProfile"] = profile
                    # The trusted collector collapses both rejected configurations to [].
                    target["containers"][0]["HostConfig"]["SecurityOpt"] = []
                    result = ISOLATION.validate_isolation(self.policy, before, after)
                    self.assertEqual(CODE.UNSAFE_CONTAINER, result.code)
                    self.assertEqual("container-security-opt", result.diagnostic()["predicate"])
                    self.assertEqual(phase, result.diagnostic()["phase"])
                    self.assertEqual(profile, result.diagnostic()["securityProfile"])
                    self.assertNotIn(CONTAINER, json.dumps(asdict(result)))

    def test_security_profile_absent_malformed_or_private_is_nullable_without_changing_denial(self):
        profile = {"hasRecognizedNnp": True, "optionsEmpty": False, "declaredOptionsCount": 2,
                   "extraOptionsCount": 1, "projectClass": "api-production", "serviceClass": "async_worker"}
        cases = [None, "private-canary", {}, profile | {"private-canary": "private-canary"}]
        for key, values in (("hasRecognizedNnp", (1, "private-canary")), ("optionsEmpty", (0, [])),
                            ("declaredOptionsCount", (True, -1, 257, "private-canary")),
                            ("extraOptionsCount", (False, -1, 257)),
                            ("projectClass", ("private-canary", [], 1)), ("serviceClass", ("private-canary", {}))):
            cases.extend(profile | {key: value} for value in values)
        for value in cases:
            with self.subTest(value=value):
                before = copy.deepcopy(self.before)
                before["containers"][0]["HostConfig"]["SecurityOpt"] = []
                before["containers"][0]["SecurityProfile"] = value
                result = ISOLATION.validate_isolation(self.policy, before, copy.deepcopy(before))
                self.assertEqual(CODE.UNSAFE_CONTAINER, result.code)
                self.assertIsNone(result.diagnostic()["securityProfile"])
                self.assertNotIn("private-canary", json.dumps(asdict(result)))
        before = copy.deepcopy(self.before)
        before["containers"][0]["HostConfig"]["SecurityOpt"] = []
        self.assertIsNone(ISOLATION.validate_isolation(self.policy, before, before).diagnostic()["securityProfile"])

    def test_security_profile_does_not_admit_or_pin_a_container(self):
        before = copy.deepcopy(self.before)
        before["containers"][0]["SecurityProfile"] = {"hasRecognizedNnp": False, "optionsEmpty": True,
            "declaredOptionsCount": 0, "extraOptionsCount": 0, "projectClass": "api-production", "serviceClass": "app"}
        after = copy.deepcopy(before)
        after["containers"][0]["SecurityProfile"] = "private-canary"
        self.assertTrue(ISOLATION.validate_isolation(self.policy, before, after).allowed)
        for project in ISOLATION.SecurityProjectClass:
            for service in ISOLATION.SecurityServiceClass:
                with self.subTest(project=project, service=service):
                    invalid = copy.deepcopy(before)
                    invalid["containers"][0]["HostConfig"]["SecurityOpt"] = []
                    invalid["containers"][0]["SecurityProfile"].update(projectClass=project.value, serviceClass=service.value)
                    self.assertEqual(CODE.UNSAFE_CONTAINER,
                                     ISOLATION.validate_isolation(self.policy, invalid, copy.deepcopy(invalid)).code)

    def test_public_security_profile_rejects_non_string_keys_and_impostor_values(self):
        class Impostor:
            value = "private-canary"
            def __eq__(self, other):
                raise AssertionError("Diagnostic must not compare untrusted object values")
        profile = {"hasRecognizedNnp": True, "optionsEmpty": False, "declaredOptionsCount": 256,
                   "extraOptionsCount": 256, "projectClass": "other", "serviceClass": "unknown"}
        result = ISOLATION.IsolationResult(False, CODE.UNSAFE_CONTAINER, ISOLATION.IsolationPredicate.CONTAINER_SECURITY,
                                         security_profile=profile)
        self.assertEqual(profile, result.diagnostic()["securityProfile"])
        for value in (profile | {"projectClass": Impostor()}, profile | {1: "private-canary"}):
            invalid = ISOLATION.IsolationResult(False, CODE.UNSAFE_CONTAINER, ISOLATION.IsolationPredicate.CONTAINER_SECURITY,
                                              security_profile=value)
            self.assertIsNone(invalid.diagnostic()["securityProfile"])
            self.assertNotIn("private-canary", json.dumps(invalid.diagnostic()))

    def test_validator_neither_reads_collects_nor_mutates_evidence(self):
        policy = copy.deepcopy(self.policy)
        snapshot = copy.deepcopy(self.before)
        with patch("builtins.open", side_effect=AssertionError("No filesystem read")), \
             patch("subprocess.run", side_effect=AssertionError("No process spawn")):
            self.assertTrue(self.validate().allowed)
        self.assertEqual(policy, self.policy)
        self.assertEqual(snapshot, self.before)

    def test_source_and_tests_parse_as_python_310(self):
        for path in (HELPER, Path(__file__)):
            ast.parse(path.read_text(encoding="utf-8"), filename=str(path), feature_version=(3, 10))


if __name__ == "__main__":
    unittest.main()
