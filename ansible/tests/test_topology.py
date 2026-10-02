"""Validate deployment task topology and real resolved Compose contracts without secrets."""
from pathlib import Path
import re
import unittest

from jinja2 import Environment
import yaml
import test_deployment_maintenance as maintenance

ROOT = Path(__file__).resolve().parents[2]


def tasks(entries):
    for entry in entries:
        yield entry
        yield from tasks(entry.get("block", []))


class DeploymentTopologyTest(unittest.TestCase):
    def setUp(self):
        self.play = yaml.safe_load((ROOT / "ansible/deploy.yml").read_text())[0]
        self.entries = list(tasks(self.play["tasks"]))
        self.names = [entry.get("name", "") for entry in self.entries]
        self.writers = set(self.play["vars"]["fireguard_writer_services"].split())

    def task(self, name):
        return self.entries[self.names.index(name)]

    def test_all_writers_stop_before_any_snapshot_migration_or_fixture_reset(self):
        lock = self.names.index("Acquire the installation operation lock before changing runtime configuration")
        self.assertLess(lock, self.names.index("Copy base Docker Compose file"))
        self.assertLess(lock, self.names.index("Record deployed image in deployment env file"))
        stop = self.names.index("Stop every managed writer before the consistent deployment snapshot")
        for name in ["Upload the explicitly activated encrypted pre-migration snapshot", "Back up auth database",
                     "Back up main database", "Archive matching application files without deployment secrets",
                     "Run auth migrations", "Run main migrations", "Purge and reload development fixture baseline"]:
            self.assertLess(stop, self.names.index(name), name)
        command = self.entries[stop]["ansible.builtin.command"]
        self.assertIn("{{ fireguard_writer_services }}", command)
        self.assertNotIn("failed_when", self.entries[stop], "A failed writer stop must abort migration")

    def test_fresh_bootstrap_initializes_every_persisted_transport_before_consumers(self):
        setup = self.names.index("Initialize every persisted Messenger transport before starting consumers")
        self.assertLess(self.names.index("Run main migrations"), setup)
        self.assertLess(setup, self.names.index("Start application service"))
        command = self.entries[setup]["ansible.builtin.command"]
        persisted = yaml.safe_load((ROOT / "config/packages/messenger.yaml").read_text())["framework"]["messenger"]["transports"]
        for name in set(persisted) - {"sync"}:
            self.assertRegex(command, r"\b" + re.escape(name) + r"\b")
        self.assertNotIn("when", self.entries[setup], "Fresh installs must not skip transport creation")
        self.assertIn("{{ fireguard_writer_services }}", self.task("Start application service")["ansible.builtin.command"])

    def test_actual_compose_receivers_cover_schedulers_and_isolate_slow_transports(self):
        expected = {"main_outbox", "async", "webhook", "assistant"}
        for path in (ROOT / "src").rglob("*ScheduleProvider.php"):
            expected.update("scheduler_" + name for name in re.findall(r"AsSchedule\(['\"]([^'\"]+)", path.read_text()))
        for development in [False, True]:
            config = maintenance.ComposeCompatibilityTest().resolved(development=development)
            seen = {}
            for name, service in config["services"].items():
                command = service.get("command") or []
                if "messenger:consume" not in command:
                    continue
                receivers = command[command.index("messenger:consume") + 1:]
                receivers = [token for token in receivers if not token.startswith("-")]
                for receiver in receivers:
                    self.assertNotIn(receiver, seen)
                    seen[receiver] = name
                self.assertIn(name, self.writers)
                self.assertIn("bin/worker-health.php", service["healthcheck"]["test"])
                duration = service["stop_grace_period"]
                seconds = sum(float(amount) * {"h": 3600, "m": 60, "s": 1}[unit]
                              for amount, unit in re.findall(r"([0-9.]+)([hms])", str(duration)))
                self.assertGreaterEqual(seconds, 120)
            self.assertEqual(expected, set(seen))
            self.assertEqual("webhook_worker", seen["webhook"])
            self.assertEqual("assistant_worker", seen["assistant"])
            self.assertEqual("async_worker", seen["main_outbox"])
            self.assertEqual({"async_worker", "webhook_worker", "assistant_worker", "scheduler_worker", "app"}, self.writers)

    def test_backup_activation_defaults_off_and_validates_before_interruption(self):
        self.assertIn("default('false'", self.play["vars"]["fireguard_offsite_backups_enabled"])
        self.assertLess(self.names.index("Validate explicitly activated backup prerequisites before changing services"),
                        self.names.index("Stop the legacy production Compose project before renaming it"))
        config = maintenance.ComposeCompatibilityTest().resolved()
        sources = {volume["source"] for volume in config["services"]["backup_files"]["volumes"]}
        self.assertEqual({"app_var"}, sources, "Environment files and JWT keys must not be archived")

    def test_managed_environment_renders_optional_resource_defaults_and_reviewed_overrides(self):
        environment = Environment()
        environment.filters["difference"] = lambda values, omitted: [value for value in values if value not in omitted]
        environment.filters["regex_replace"] = lambda value, pattern, replacement: re.sub(pattern, replacement, value)
        defaults = self.play["vars"]["fireguard_runtime_env_defaults"]
        overrides = {"API_CPU_LIMIT": "0.35", "LOG_MAX_FILES": "7"}
        template = environment.from_string((ROOT / "ansible/templates/production.env.j2").read_text())
        rendered = template.render(fireguard_runtime_env_defaults=defaults, fireguard_declared_env_keys=list(defaults),
                                   fireguard_computed_env_keys=[], fireguard_deployment_timestamp="synthetic", fireguard_image="synthetic",
                                   fireguard_deployment_environment="production", lookup=lambda _kind, key: overrides.get(key, ""))
        self.assertIn("API_CPU_LIMIT='0.35'", rendered)
        self.assertIn("LOG_MAX_FILES='7'", rendered)
        self.assertIn("API_MEMORY_LIMIT='768m'", rendered)
        self.assertIn("WORKER_HEARTBEAT_MAX_AGE='300'", rendered)
        self.assertNotIn("LOG_MAX_SIZE=''", rendered)

    def test_startup_and_public_probe_failures_stop_writers_and_retain_lock(self):
        for name in ["Start application and verify internal health", "Verify public surfaces before releasing the operation lock"]:
            rescue = self.task(name)["rescue"]
            stops = [task for task in rescue if "ansible.builtin.command" in task and " stop " in task["ansible.builtin.command"]]
            self.assertEqual(1, len(stops))
            self.assertIn("{{ fireguard_writer_services }}", stops[0]["ansible.builtin.command"])
            self.assertTrue(any("ansible.builtin.fail" in task for task in rescue))
            self.assertFalse(any("ansible.builtin.file" in task for task in rescue))
        self.assertEqual("Release the operation lock only after verified rollout", self.names[-1])


if __name__ == "__main__":
    unittest.main()
