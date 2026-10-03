"""Validate deployment task topology and real resolved Compose contracts without secrets."""
from pathlib import Path
import json
import re
import shlex
import shutil
import subprocess
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
        self.assertLess(self.names.index("Run auth migrations"), setup)
        self.assertLess(self.names.index("Run main migrations"), setup)
        self.assertLess(setup, self.names.index("Start application service"))
        task = self.entries[setup]
        persisted = yaml.safe_load((ROOT / "config/packages/messenger.yaml").read_text())["framework"]["messenger"]["transports"]
        self.assertEqual(set(persisted) - {"sync"}, set(task["loop"]))
        self.assertEqual(6, len(task["loop"]))
        for name, command in zip(task["loop"], self.transport_setup_commands()):
            with self.subTest(transport=name):
                self.assertEqual([
                    "docker", "compose", "-f", "compose.prod.yaml", "run", "--rm", "--no-deps",
                    "--entrypoint", "php", "app", "-d", "memory_limit=1G", "bin/console",
                    "messenger:setup-transports", name, "--env=prod", "--no-interaction",
                ], command)
        self.assertNotIn("when", task, "Fresh installs must not skip transport creation")
        self.assertNotIn("ignore_errors", task, "A transport setup failure must stop deployment")
        self.assertNotIn("failed_when", task, "A transport setup failure must stop deployment")
        self.assertIn("{{ fireguard_writer_services }}", self.task("Start application service")["ansible.builtin.command"])

    def transport_setup_commands(self):
        task = self.task("Initialize every persisted Messenger transport before starting consumers")
        template = Environment().from_string(task["ansible.builtin.command"])
        return [shlex.split(template.render(fireguard_compose_command="docker compose -f compose.prod.yaml", item=name))
                for name in task["loop"]]

    def test_rendered_transport_setup_commands_execute_with_installed_symfony(self):
        php = shutil.which("php")
        self.assertIsNotNone(php, "The Symfony command regression requires PHP CLI")
        self.assertTrue((ROOT / "vendor/autoload.php").is_file(), "The Symfony command regression requires Composer dependencies")
        commands = [command[command.index("bin/console") + 1:] for command in self.transport_setup_commands()]
        probe = r'''
require $argv[1] . '/vendor/autoload.php';
$commands = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$names = array_column($commands, 1);
$locator = new Symfony\Component\DependencyInjection\Container();
$calls = new ArrayObject();
foreach ($names as $name) {
  $locator->set($name, new class($name, $calls) implements Symfony\Component\Messenger\Transport\SetupableTransportInterface {
    public function __construct(private string $name, private ArrayObject $calls) {}
    public function setup(): void { $this->calls->append($this->name); }
  });
}
$application = new Symfony\Component\Console\Application();
$application->setAutoExit(false);
$application->setCatchExceptions(false);
$application->getDefinition()->addOption(new Symfony\Component\Console\Input\InputOption('env', null, Symfony\Component\Console\Input\InputOption::VALUE_REQUIRED));
$application->add(new Symfony\Component\Messenger\Command\SetupTransportsCommand($locator, $names));
$statuses = [];
foreach ($commands as $tokens) {
  $statuses[] = $application->run(new Symfony\Component\Console\Input\ArgvInput(['console', ...$tokens]), new Symfony\Component\Console\Output\BufferedOutput());
}
$invalidError = null;
try {
  $application->run(new Symfony\Component\Console\Input\ArgvInput(['console', 'messenger:setup-transports', ...$names, '--env=prod', '--no-interaction']), new Symfony\Component\Console\Output\BufferedOutput());
} catch (Symfony\Component\Console\Exception\RuntimeException $exception) {
  $invalidError = $exception->getMessage();
}
echo json_encode(['statuses' => $statuses, 'calls' => $calls->getArrayCopy(), 'invalid_error' => $invalidError], JSON_THROW_ON_ERROR);
'''
        result = subprocess.run([php, "-r", probe, str(ROOT)], input=json.dumps(commands),
                                text=True, capture_output=True, timeout=10)
        self.assertEqual(0, result.returncode, result.stderr)
        executed = json.loads(result.stdout)
        self.assertEqual([0] * 6, executed["statuses"])
        self.assertEqual(self.task("Initialize every persisted Messenger transport before starting consumers")["loop"], executed["calls"])
        self.assertIn("Too many arguments", executed["invalid_error"])

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
