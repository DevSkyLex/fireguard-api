"""Validate deployment task topology and real resolved Compose contracts without secrets."""
from pathlib import Path
import importlib.util
import json
import re
import shlex
import shutil
import subprocess
import tempfile
import unittest
from unittest.mock import patch

from jinja2 import Environment
import yaml
import test_deployment_maintenance as maintenance

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("webhook_cutover", ROOT / "ansible/webhook-cutover.py")
CUTOVER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CUTOVER)


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
        self.assertNotIn("when", self.entries[lock], "Every deployment must acquire the ordinary operation lock")
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
        self.assertEqual(7, len(task["loop"]))
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
        self.assertEqual([0] * len(commands), executed["statuses"])
        self.assertEqual(self.task("Initialize every persisted Messenger transport before starting consumers")["loop"], executed["calls"])
        self.assertIn("Too many arguments", executed["invalid_error"])

    def test_actual_compose_receivers_cover_schedulers_and_isolate_slow_transports(self):
        expected = {"main_outbox", "auth_outbox", "async", "webhook", "assistant"}
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
            self.assertEqual("async_worker", seen["auth_outbox"])
            self.assertEqual({"async_worker", "webhook_worker", "assistant_worker", "scheduler_worker", "app"}, self.writers)

    def test_backup_activation_defaults_off_and_validates_before_interruption(self):
        self.assertIn("default('false'", self.play["vars"]["fireguard_offsite_backups_enabled"])
        self.assertLess(self.names.index("Validate explicitly activated backup prerequisites before changing services"),
                        self.names.index("Drain the installed auth Webhook runtime before any legacy project shutdown"))
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
        for name in ["Recover pending Webhook reservations before restarting producers",
                     "Start application and verify internal health", "Verify public surfaces before releasing the operation lock"]:
            rescue = self.task(name)["rescue"]
            stops = [task for task in rescue if "ansible.builtin.command" in task and " stop " in task["ansible.builtin.command"]]
            self.assertEqual(1, len(stops))
            self.assertIn("{{ fireguard_writer_services }}", stops[0]["ansible.builtin.command"])
            self.assertTrue(any("ansible.builtin.fail" in task for task in rescue))
            self.assertFalse(any("ansible.builtin.file" in task for task in rescue))
        self.assertEqual("Release the operation lock only after verified rollout", self.names[-1])

    def test_auth_cutover_precedes_legacy_shutdown_and_checks_effective_volume_before_migration(self):
        first = self.names.index("Drain the installed auth Webhook runtime before any legacy project shutdown")
        effective = self.names.index("Verify the effective auth Webhook queue before snapshots or schema changes")
        self.assertLess(first, self.names.index("Stop the legacy production Compose project before renaming it"))
        self.assertLess(self.names.index("Start dependency services"), effective)
        self.assertLess(effective, self.names.index("Stop every managed writer before the consistent deployment snapshot"))
        self.assertLess(effective, self.names.index("Run auth migrations"))
        initial = self.entries[first]["ansible.builtin.command"]["argv"]
        verified = self.entries[effective]["ansible.builtin.command"]["argv"]
        self.assertIn("--defer-bootstrap", initial)
        self.assertNotIn("--defer-bootstrap", verified)
        self.assertEqual(self.task("Stop the legacy production Compose project before renaming it")["when"],
                         self.entries[first]["when"])
        self.assertTrue(any("fireguard_initial_webhook_cutover.skipped" in token for token in verified))
        self.assertNotIn("when", self.entries[effective], "Ordinary and bootstrap deployments must verify auth")
        for index in (first, effective):
            self.assertNotIn("failed_when", self.entries[index])
            self.assertNotIn("ignore_errors", self.entries[index])

    def test_recovery_success_is_required_before_any_new_producer_starts(self):
        sweep = self.names.index("Require a newly successful Webhook recovery sweep")
        self.assertLess(self.names.index("Initialize every persisted Messenger transport before starting consumers"), sweep)
        self.assertLess(self.names.index("Start only the scheduler before restoring HTTP and queue producers"), sweep)
        self.assertLess(sweep, self.names.index("Start application service"))
        command = self.entries[sweep]["ansible.builtin.command"]
        self.assertIn("RecoverWebhookDeliveriesCommand.timestamp", command)
        self.assertIn("$timestamp > (int) $argv[1]", command)
        self.assertEqual(36, self.entries[sweep]["retries"])
        self.assertEqual(5, self.entries[sweep]["delay"])
        self.assertTrue(self.entries[sweep]["no_log"])


class InstalledWebhookCutoverTest(unittest.TestCase):
    def setUp(self):
        self.calls = []
        self.clock = 0
        self.ids = {f"{index:012x}": service for index, service in enumerate(
            ["auth_database", "app", "async_worker", "webhook_worker", "assistant_worker", "scheduler_worker"], 1)}
        self.running = set(self.ids)
        self.counts = [0]
        self.connection = "auth"
        self.table = "present"
        self.public_tables = "0"
        self.killed = set()
        self.invalid_count = False
        self.database_failure = False

    def fake_run(self, argv, **_kwargs):
        self.calls.append(argv)
        if argv[1] == "ps":
            return "\n".join(identifier + " " + service for identifier, service in self.ids.items())
        if argv[1] == "stop":
            self.running.difference_update(argv[4:])
            return ""
        if argv[1] == "inspect":
            identifier = argv[-1]
            return ("true" if identifier in self.running else "false") + " " + (
                "137" if identifier in self.killed else "0") + " false"
        if argv[1] == "exec":
            if argv[-1] == CUTOVER.TRANSPORT_PROBE:
                return self.connection
            if self.database_failure:
                raise CUTOVER.CutoverError("Database command failed; queue state is unknown.")
            if argv[-1] == CUTOVER.TABLE_PROBE:
                return self.table
            if argv[-1] == CUTOVER.FRESH_PROBE:
                return self.public_tables
            if argv[-1] == CUTOVER.COUNT_PROBE:
                if self.invalid_count:
                    return "PRIVATE_INVALID_COUNT"
                count = self.counts[0]
                if len(self.counts) > 1:
                    self.counts.pop(0)
                return str(count)
        self.fail("Unexpected command: " + str(argv))

    def sleep(self, duration):
        self.clock += duration

    def execute(self, **options):
        with patch.object(CUTOVER, "run", self.fake_run), patch.object(CUTOVER.time, "monotonic", lambda: self.clock), \
                patch.object(CUTOVER.time, "sleep", self.sleep):
            return CUTOVER.cutover(Path("/synthetic/app"), ["current", "back"], **options)

    def worker(self):
        return next(identifier for identifier, service in self.ids.items() if service == "webhook_worker")

    def test_first_cutover_drains_all_auth_rows_with_exact_old_worker_then_rechecks_after_stop(self):
        self.counts = [3, 1, 0, 0]
        self.assertEqual("drained", self.execute())
        stops = [command for command in self.calls if command[1] == "stop"]
        self.assertEqual(2, len(stops))
        self.assertNotIn(self.worker(), stops[0])
        self.assertEqual(set(self.ids) - {self.worker(), "000000000001"}, set(stops[0][4:]))
        self.assertEqual([self.worker()], stops[1][4:])
        self.assertEqual("120", stops[1][3])
        probes = [index for index, command in enumerate(self.calls) if command[-1] == CUTOVER.COUNT_PROBE]
        self.assertEqual(4, len(probes))
        self.assertGreater(self.calls.index(stops[1]), probes[2])
        self.assertGreater(probes[3], self.calls.index(stops[1]))
        self.assertNotIn("available_at", CUTOVER.COUNT_PROBE)
        self.assertNotIn("delivered_at", CUTOVER.COUNT_PROBE)
        self.assertFalse(any("compose" in command for command in self.calls))

    def test_following_main_deploy_is_idempotent_when_auth_is_empty(self):
        self.connection = "main"
        self.assertEqual("drained", self.execute())
        self.assertEqual("drained", self.execute(already_drained=True))
        self.assertEqual(1, sum(command[-1] == CUTOVER.TRANSPORT_PROBE for command in self.calls))

    def test_unknown_transport_is_refused_even_when_auth_is_empty(self):
        self.connection = "unsupported"
        with self.assertRaisesRegex(CUTOVER.CutoverError, "transport is unsupported"):
            self.execute()
        self.assertIn(self.worker(), self.running)

    def test_installed_transport_probe_returns_only_the_connection_for_literal_and_env_dsns(self):
        probe = CUTOVER.TRANSPORT_PROBE.replace("require 'vendor/autoload.php';",
                                               "require '" + ROOT.as_posix() + "/vendor/autoload.php';")
        for dsn, configured, expected in [
            ("doctrine://main?queue_name=webhook&auto_setup=false", "", "main"),
            ("%env(MESSENGER_TRANSPORT_DSN)%", "doctrine://auth?private=PRIVATE_SECRET", "auth"),
            ("%env(MESSENGER_TRANSPORT_DSN)%", "redis://PRIVATE_SECRET@synthetic.invalid", "unsupported"),
        ]:
            with self.subTest(expected=expected), tempfile.TemporaryDirectory(prefix="fireguard-cutover-probe-") as directory:
                location = Path(directory)
                (location / "config/packages").mkdir(parents=True)
                (location / "config/packages/messenger.yaml").write_text(
                    yaml.safe_dump({"framework": {"messenger": {"transports": {"webhook": {"dsn": dsn}}}}}))
                result = subprocess.run([shutil.which("php"), "-r", probe], cwd=location,
                                        env=dict(maintenance.os.environ, MESSENGER_TRANSPORT_DSN=configured),
                                        text=True, capture_output=True, timeout=10)
                self.assertEqual(0, result.returncode, result.stderr)
                self.assertEqual(expected, result.stdout)
                self.assertNotIn("PRIVATE_SECRET", result.stdout + result.stderr)

    def test_multiple_webhook_workers_are_refused_even_when_auth_is_empty(self):
        self.ids["000000000007"] = "webhook_worker"
        with self.assertRaisesRegex(CUTOVER.CutoverError, "Multiple"):
            self.execute()
        self.assertFalse(any(command[1] == "stop" for command in self.calls))

    def test_other_compose_service_names_are_not_mistaken_for_invalid_metadata_or_writers(self):
        self.ids["000000000007"] = "stripe-cli"
        self.running.add("000000000007")
        self.assertEqual("drained", self.execute())
        self.assertIn("000000000007", self.running)
        self.assertFalse(any(command[1] == "stop" and "000000000007" in command[4:] for command in self.calls))

    def test_nonempty_auth_queue_without_a_running_auth_worker_is_refused(self):
        for connection in ["main", "unsupported", "stopped", "missing"]:
            with self.subTest(connection=connection):
                self.setUp()
                self.counts = [1]
                self.connection = connection
                worker = self.worker()
                if connection == "stopped":
                    self.running.remove(worker)
                elif connection == "missing":
                    del self.ids[worker]
                with self.assertRaises(CUTOVER.CutoverError):
                    self.execute()
                self.assertFalse(any(command[1] == "stop" and worker in command[4:] for command in self.calls))

    def test_deadline_retains_pending_rows_and_only_old_worker(self):
        self.counts = [1]
        with self.assertRaisesRegex(CUTOVER.CutoverError, "deadline exceeded"):
            self.execute(deadline=10, poll=5)
        self.assertEqual(10, self.clock)
        self.assertEqual({"000000000001", self.worker()}, self.running)
        self.assertEqual([1], self.counts)

    def test_late_retry_during_shutdown_aborts_migration(self):
        self.counts = [0, 1]
        with self.assertRaisesRegex(CUTOVER.CutoverError, "during graceful shutdown"):
            self.execute()

    def test_forced_worker_termination_is_refused(self):
        self.killed.add(self.worker())
        with self.assertRaisesRegex(CUTOVER.CutoverError, "did not stop gracefully"):
            self.execute()

    def test_unknown_database_state_is_never_treated_as_an_empty_queue(self):
        for failure in ["sql", "count", "table"]:
            with self.subTest(failure=failure):
                self.setUp()
                self.database_failure = failure == "sql"
                self.invalid_count = failure == "count"
                self.table = "absent" if failure == "table" else "present"
                with self.assertRaises(CUTOVER.CutoverError) as error:
                    self.execute()
                self.assertNotIn("PRIVATE", str(error.exception))

    def test_fresh_volume_requires_online_auth_and_an_empty_public_schema(self):
        self.ids = {"000000000001": "auth_database"}
        self.table = "absent"
        self.assertEqual("fresh", self.execute())
        self.public_tables = "7"
        with self.assertRaises(CUTOVER.CutoverError):
            self.execute()
        self.ids = {}
        self.assertEqual("bootstrap-deferred", self.execute(defer_bootstrap=True))
        with self.assertRaises(CUTOVER.CutoverError):
            self.execute()


if __name__ == "__main__":
    unittest.main()
