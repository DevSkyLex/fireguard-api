"""Run with python -B ansible/tests/test_deployment_maintenance.py (no live services)."""

import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import subprocess
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
# Historical Compose fixtures are exact snapshots of this reviewed pre-GeoIP revision.
PRE_GEOIP_REVISION = "5078f2331755fa706c0a25d9150530275b7c8af2"
SPEC = importlib.util.spec_from_file_location(
    "geoip_validation", ROOT / "ansible/filter_plugins/geoip_validation.py"
)
FILTER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(FILTER)


def shell_path(path):
    value = Path(path).as_posix()
    if os.name == "nt" and len(value) > 1 and value[1] == ":":
        return "/" + value[0].lower() + value[2:]
    return value


def posix_shell():
    if os.name == "nt":
        candidate = Path("C:/Program Files/Git/usr/bin/sh.exe")
        if candidate.is_file():
            return str(candidate)
    return shutil.which("sh")


def setUpModule():
    required = {
        "POSIX sh": posix_shell(),
        "Docker Compose CLI": shutil.which("docker"),
        "PHP CLI": shutil.which("php"),
        "Composer vendor/autoload.php": (ROOT / "vendor/autoload.php").is_file(),
    }
    missing = [name for name, available in required.items() if not available]
    if missing:
        raise RuntimeError("Deployment test prerequisites are missing: " + ", ".join(missing))


class ProxyValidationTest(unittest.TestCase):
    def test_accepts_only_explicit_addresses_and_cidrs(self):
        for value in ["", "   ", "192.0.2.1", "192.0.2.10/24", "2001:db8::1",
                      "2001:db8::/32", '192.0.2.1,"2001:db8::/48"']:
            with self.subTest(value=value):
                self.assertTrue(FILTER.trusted_proxies_valid(value))

    def test_rejects_aliases_alone_mixed_and_quoted(self):
        for alias in ["REMOTE_ADDR", "private_ranges", "PRIVATE_SUBNETS"]:
            for value in [alias, f'"{alias}"', f"'{alias}'", f"192.0.2.1,{alias}",
                          f'{alias},192.0.2.1', f'192.0.2.1, "{alias}"']:
                with self.subTest(value=value):
                    self.assertFalse(FILTER.trusted_proxies_valid(value))

    def test_nonblank_proxy_csv_must_not_contain_whitespace(self):
        for value in [" 192.0.2.1", "192.0.2.1 ", "192.0.2.1, 192.0.2.2",
                      "192.0.2.1,\t192.0.2.2", '"192.0.2.1", "2001:db8::/48"',
                      '"192.0.2.1 "', "192.0.2.1,\u00a0192.0.2.2"]:
            with self.subTest(value=value):
                self.assertFalse(FILTER.trusted_proxies_valid(value))

    def test_accepted_lists_match_real_php_csv_and_symfony_iputils(self):
        values = ["192.0.2.1", "192.0.2.10/24", "2001:db8::1", "2001:db8::/32",
                  '192.0.2.1,"2001:db8::/48"', '"192.0.2.1","192.0.2.0/24"',
                  "192.0.2.1, 192.0.2.2", '"192.0.2.1 "', "256.0.0.1",
                  "192.0.2.1/33", "2001:db8::/129", "traefik"]
        probe = r'''
require $argv[1] . '/vendor/autoload.php';
$values = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$results = [];
foreach ($values as $value) {
  $tokens = str_getcsv($value, ',', '"', '');
  $valid = true;
  foreach ($tokens as $token) {
    $address = explode('/', $token, 2)[0];
    $valid = $valid && Symfony\Component\HttpFoundation\IpUtils::checkIp($address, $token);
  }
  $results[] = ['tokens' => $tokens, 'valid' => $valid];
}
echo json_encode($results, JSON_THROW_ON_ERROR);
'''
        result = subprocess.run(
            [shutil.which("php"), "-r", probe, str(ROOT)], input=json.dumps(values),
            text=True, capture_output=True, timeout=10, check=False,
        )
        self.assertEqual(0, result.returncode, result.stderr)
        runtime = json.loads(result.stdout)
        for value, actual in zip(values, runtime, strict=True):
            with self.subTest(value=value):
                self.assertEqual(FILTER.trusted_proxies_valid(value), actual["valid"])
        # This is the exact regression: PHP preserves the second field's space.
        self.assertEqual(["192.0.2.1", " 192.0.2.2"], runtime[6]["tokens"])

    def test_rejects_empty_elements_names_invalid_networks_and_csv(self):
        for value in [",", "192.0.2.1,", ",192.0.2.1", "192.0.2.1,,2001:db8::1",
                      '192.0.2.1,""', "traefik", "256.0.0.1", "192.0.2.1/33",
                      "2001:db8::/129", "192.0.2.1/255.255.255.0", "fe80::1%eth0",
                      '"192.0.2.1', '"192.0.2.1" trailing', "192.0.2.1\n2001:db8::1",
                      None, False, ["192.0.2.1"]]:
            with self.subTest(value=value):
                self.assertFalse(FILTER.trusted_proxies_valid(value))

    def test_effective_app_value_wins_over_other_services_in_both_env_modes(self):
        for managed in [True, False]:
            for effective, expected in [("PRIVATE_SUBNETS", False), ("192.0.2.1", True)]:
                with self.subTest(managed=managed, effective=effective):
                    resolved = json.loads(json.dumps({"services": {
                        "app": {"environment": {"TRUSTED_PROXIES": effective}},
                        "assistant_worker": {"environment": {"TRUSTED_PROXIES": ""}},
                    }}))
                    self.assertEqual(expected, FILTER.FilterModule().filters()[
                        "fireguard_trusted_proxies_valid"
                    ](resolved["services"]["app"]["environment"]["TRUSTED_PROXIES"]))


class MaintenanceExecutionTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory(prefix="fireguard-maintenance-")
        self.addCleanup(self.temporary.cleanup)
        self.directory = Path(self.temporary.name)
        self.app = self.directory / "application's directory"
        self.app.mkdir()
        self.bin = self.directory / "bin"
        self.bin.mkdir()
        self.calls = self.directory / "docker-calls"
        self.events = self.directory / "logger-calls"
        self.script = self.app / "fireguard-maintenance.sh"
        self.template = (ROOT / "ansible/templates/maintenance.sh.j2").read_text()
        self.render()
        (self.app / "compose.yaml").write_text("services:\n  app:\n  geoip_maintenance:\n")
        docker = self.bin / "docker"
        docker.write_text("""#!/bin/sh
printf '%s\\n' "$*" >> "$FAKE_DOCKER_CALLS"
case "$*" in
  *'config --services'*)
    if [ "${FAKE_CONFIG_FAILURE:-0}" != 0 ]; then
      echo 'PRIVATE_CONFIGURATION_MUST_NOT_LEAK' >&2
      exit "$FAKE_CONFIG_FAILURE"
    fi
    printf 'app\\n'
    case "$*" in
      *'--profile tools config --services'*)
        if grep -q '^  geoip_maintenance:' compose.yaml; then printf 'geoip_maintenance\\n'; fi
        ;;
    esac
    ;;
  *'app:geoip:update --check'*)
    if [ "${FAKE_CHECK_FAILURE:-0}" != 0 ]; then
      echo 'PRIVATE_DELIVERY_MUST_NOT_LEAK' >&2
      exit "$FAKE_CHECK_FAILURE"
    fi
    ;;
  *'app:geoip:update'*) exit "${FAKE_UPDATE_FAILURE:-0}" ;;
  *'app:geoip:purge-session-locations'*) exit "${FAKE_PURGE_FAILURE:-0}" ;;
  *'app:cleanup:auth-data'*) exit "${FAKE_AUTH_FAILURE:-0}" ;;
  *) exit 99 ;;
esac
""", newline="\n")
        docker.chmod(0o755)
        logger = self.bin / "logger"
        logger.write_text("""#!/bin/sh
printf '%s\\n' "$*" >> "$FAKE_LOGGER_CALLS"
exit "${FAKE_LOGGER_FAILURE:-0}"
""", newline="\n")
        logger.chmod(0o755)
        self.environment = dict(os.environ)
        self.environment["PATH"] = ":".join([
            shell_path(self.bin), shell_path(Path(posix_shell()).parent), "/usr/bin", "/bin",
        ])
        self.environment["FAKE_DOCKER_CALLS"] = shell_path(self.calls)
        self.environment["FAKE_LOGGER_CALLS"] = shell_path(self.events)

    def render(self, development=False):
        command = "docker compose -f compose.yaml" + (" -f compose.dev.yaml" if development else "")
        rendered = self.template.replace("{{ fireguard_compose_command }}", command).replace(
            "{{ fireguard_app_dir | quote }}", shlex.quote(shell_path(self.app))
        )
        self.assertNotIn("{{", rendered)
        self.script.write_text(rendered, newline="\n")

    def run_task(self, task, **settings):
        return subprocess.run(
            [posix_shell(), shell_path(self.script), task], env=dict(self.environment, **settings),
            text=True, capture_output=True, timeout=10, check=False,
        )

    def docker_calls(self):
        return self.calls.read_text().splitlines() if self.calls.exists() else []

    def test_update_and_check_are_sequential_and_both_respect_disabled_collection(self):
        result = self.run_task("geoip-update")
        self.assertEqual(0, result.returncode, result.stderr)
        calls = self.docker_calls()
        self.assertEqual(3, len(calls))
        self.assertTrue(calls[0].endswith("--profile tools config --services"))
        self.assertIn("app:geoip:update --if-enabled --env=prod", calls[1])
        self.assertIn("app:geoip:update --check --if-enabled --env=prod", calls[2])

    def test_update_failure_skips_check_and_preserves_failure_exit(self):
        result = self.run_task("geoip-update", FAKE_UPDATE_FAILURE="42")
        self.assertEqual(42, result.returncode)
        self.assertEqual(2, len(self.docker_calls()))
        self.assertEqual("GeoIP update failed\n", result.stderr)
        self.assertIn("GeoIP update failed", self.events.read_text())

    def test_check_failure_is_sanitized_and_survives_a_logger_failure(self):
        result = self.run_task("geoip-update", FAKE_CHECK_FAILURE="43", FAKE_LOGGER_FAILURE="8")
        self.assertEqual(43, result.returncode)
        self.assertEqual("GeoIP freshness check failed\n", result.stderr)
        self.assertNotIn("PRIVATE_DELIVERY", result.stdout + result.stderr + self.events.read_text())

    def test_bad_compose_configuration_fails_instead_of_skipping(self):
        result = self.run_task("geoip-purge", FAKE_CONFIG_FAILURE="7")
        self.assertEqual(7, result.returncode)
        self.assertEqual(1, len(self.docker_calls()))
        self.assertEqual("Maintenance Compose configuration failed\n", result.stderr)
        self.assertNotIn("PRIVATE_CONFIGURATION", result.stdout + result.stderr + self.events.read_text())

    def test_historical_compose_skips_only_geoip_and_keeps_auth_retention(self):
        (self.app / "compose.yaml").write_text("services:\n  app:\n")
        for task in ["geoip-update", "geoip-purge"]:
            self.assertEqual(0, self.run_task(task).returncode)
        self.assertEqual(0, self.run_task("auth-retention").returncode)
        self.assertEqual(3, len(self.docker_calls()))
        self.assertTrue(self.docker_calls()[-1].endswith(
            "run --rm --no-deps --entrypoint php app -d memory_limit=1G bin/console app:cleanup:auth-data --env=prod"
        ))
        self.assertFalse(self.events.exists())

    def test_purge_failure_does_not_block_independent_auth_retention(self):
        self.assertEqual(12, self.run_task("geoip-purge", FAKE_PURGE_FAILURE="12").returncode)
        self.assertNotIn("--if-enabled", self.docker_calls()[-1])
        self.assertEqual(0, self.run_task("auth-retention", FAKE_PURGE_FAILURE="12").returncode)
        self.assertIn("app:cleanup:auth-data", self.docker_calls()[-1])

    def test_auth_failure_is_reported_without_geoip_probe(self):
        result = self.run_task("auth-retention", FAKE_AUTH_FAILURE="13")
        self.assertEqual(13, result.returncode)
        self.assertEqual(1, len(self.docker_calls()))
        self.assertEqual("Authentication retention task failed\n", result.stderr)

    def test_development_preserves_both_compose_files(self):
        self.render(development=True)
        self.assertEqual(0, self.run_task("geoip-purge").returncode)
        for command in self.docker_calls():
            self.assertTrue(command.startswith("compose -f compose.yaml -f compose.dev.yaml "))


class DeploymentOrderingTest(unittest.TestCase):
    def test_scheduler_and_configuration_fail_before_any_service_interruption(self):
        playbook = (ROOT / "ansible/deploy.yml").read_text()
        first_interruption = playbook.index("- name: Stop the legacy production Compose project")
        for name in [
            "Require an installed crontab executable",
            "Require a running cron scheduler before changing services",
            "Resolve and validate the effective application proxy configuration",
            "Install the stable host maintenance helper",
            "Install daily GeoIP update and freshness check",
            "Install daily revoked-session location cleanup",
            "Install daily authentication retention",
        ]:
            with self.subTest(name=name):
                self.assertLess(playbook.index("- name: " + name), first_interruption)
        self.assertLess(first_interruption, playbook.index("- name: Stop application service"))

    def test_effective_configuration_validation_covers_both_env_modes_privately(self):
        playbook = (ROOT / "ansible/deploy.yml").read_text()
        block = playbook.split("- name: Resolve and validate the effective application proxy configuration", 1)[1]
        block = block.split("- name: Require an explicit effective application proxy list", 1)[0]
        self.assertNotIn("when: fireguard_managed_env", block)
        self.assertEqual(2, block.count("no_log: true"))
        self.assertIn("config --format json", block)
        self.assertIn(".services.app.environment.TRUSTED_PROXIES", block)


class ComposeCompatibilityTest(unittest.TestCase):
    def resolved(self, historical=False, development=False, proxies=""):
        def source(name):
            if not historical:
                return (ROOT / name).read_text()
            return (ROOT / "ansible/tests/fixtures" / ("pre-geoip-" + name)).read_text()

        base = source("compose.prod.yaml")
        override = source("compose.dev.yaml") if development else ""
        values = {name: "synthetic" for name in re.findall(r"\$\{([A-Z][A-Z0-9_]*)", base + override)}
        values.update({
            "FIREGUARD_IMAGE": "example.invalid/fireguard:synthetic",
            "FIREGUARD_FIXTURES_IMAGE": "example.invalid/fixtures:synthetic",
            "FRANKENPHP_LOOP_MAX": "500", "FRANKENPHP_CONFIG": "",
            "GEOIP_ENABLED": "false", "GEOIP_MAX_AGE_DAYS": "45",
            "TRUSTED_PROXIES": proxies,
        })
        with tempfile.TemporaryDirectory(prefix="fireguard-compose-synthetic-") as directory:
            task_dir = Path(directory)
            # All content is synthetic. Never use the workspace's secret env file.
            (task_dir / "config.json").write_text("{}")
            (task_dir / "synthetic.env").write_text("".join(f"{key}='{value}'\n" for key, value in values.items()))
            (task_dir / "compose.yaml").write_text(
                base.replace("- .env", "- synthetic.env").replace("[.env]", "[synthetic.env]")
            )
            command = ["docker", "compose", "--env-file", "synthetic.env", "-f", "compose.yaml"]
            if development:
                (task_dir / "compose.dev.yaml").write_text(override.replace("- .env", "- synthetic.env"))
                command += ["-f", "compose.dev.yaml"]
            result = subprocess.run(
                command + ["--profile", "tools", "config", "--format", "json"], cwd=task_dir,
                env=dict(os.environ, **values, DOCKER_CONFIG=str(task_dir)),
                text=True, capture_output=True, timeout=30, check=False,
            )
            self.assertEqual(0, result.returncode, result.stderr)
            return json.loads(result.stdout)

    def test_current_compose_resolves_proxy_value_for_production_and_development(self):
        for development in [False, True]:
            for proxies, expected in [("", True), ("192.0.2.1,2001:db8::/48", True),
                                      ("192.0.2.1,PRIVATE_SUBNETS", False)]:
                with self.subTest(development=development, proxies=proxies):
                    config = self.resolved(development=development, proxies=proxies)
                    self.assertTrue("geoip_maintenance" in config["services"], "Tools profile must expose GeoIP")
                    effective = config["services"]["app"]["environment"]["TRUSTED_PROXIES"]
                    self.assertEqual(proxies, effective)
                    self.assertEqual(expected, FILTER.trusted_proxies_valid(effective))

    def test_pre_geoip_compose_retains_app_for_auth_cleanup_without_geoip_service(self):
        for development in [False, True]:
            with self.subTest(development=development):
                config = self.resolved(historical=True, development=development)
                self.assertTrue("app" in config["services"], "Historical configuration must retain app")
                self.assertFalse("geoip_maintenance" in config["services"], "Historical revision predates GeoIP")


if __name__ == "__main__":
    unittest.main()
