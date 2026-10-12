"""Run with python -B ansible/tests/test_idempotency_validation.py; no live services."""

import base64
import importlib.util
import json
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest

from jinja2 import Environment
import yaml

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location(
    "idempotency_validation", ROOT / "ansible/filter_plugins/idempotency_validation.py"
)
FILTER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(FILTER)

RUNTIME_BLOCK = "Verify the managed development runtime HTTP idempotency policy"
RUNTIME_COMMAND = "Read only the running application's HTTP idempotency policy"
RUNTIME_ASSERT = "Require the expected development runtime HTTP idempotency policy"
PRIVATE_TEST_MARKER = "PRIVATE_RUNTIME_PROBE_MUST_NOT_LEAK"


def tasks(entries):
    for entry in entries:
        yield entry
        yield from tasks(entry.get("block", []))


FAKE_AUTOLOAD = r"""<?php
namespace App;

function state(): array {
  return json_decode(file_get_contents('probe-state.json'), true, 512, JSON_THROW_ON_ERROR);
}

function observe(string $stage): void {
  file_put_contents('probe-trace.txt', $stage . "\n", FILE_APPEND);
  if ((state()['failure'] ?? '') === $stage) {
    throw new \RuntimeException('PRIVATE_RUNTIME_PROBE_MUST_NOT_LEAK');
  }
}

observe('autoload');

final class Kernel {
  public function __construct(string $environment, bool $debug) {
    if ($environment !== 'prod' || $debug !== false) {
      throw new \RuntimeException('PRIVATE_RUNTIME_PROBE_MUST_NOT_LEAK');
    }
    observe('construct');
  }

  public function boot(): void {
    observe('boot');
  }

  public function getContainer(): object {
    observe('container');
    return new class {
      public function getParameter(string $name): mixed {
        if ($name !== 'http_idempotency.required') {
          throw new \RuntimeException('PRIVATE_RUNTIME_PROBE_MUST_NOT_LEAK');
        }
        observe('parameter');
        return state()['actual'];
      }
    };
  }

  public function shutdown(): void {
    observe('shutdown');
  }
}
"""


def encoded(size):
    """Predictable public test bytes; never deployment key material."""
    return base64.b64encode(b"T" * size).decode("ascii")


def configuration():
    return {
        "HTTP_IDEMPOTENCY_HMAC_KEY": encoded(32),
        "HTTP_IDEMPOTENCY_ENCRYPTION_KEYS": json.dumps({"v1": encoded(32), "v2": encoded(32)}),
        "HTTP_IDEMPOTENCY_ENCRYPTION_ACTIVE_VERSION": "v2",
    }


class IdempotencyValidationTest(unittest.TestCase):
    def test_accepts_current_key_and_retained_old_versions(self):
        self.assertTrue(FILTER.http_idempotency_valid(configuration()))
        values = configuration()
        values["HTTP_IDEMPOTENCY_HMAC_KEY"] = encoded(64)
        self.assertTrue(FILTER.http_idempotency_valid(values))

    def test_hmac_requires_strict_base64_and_at_least_32_bytes(self):
        for value in [None, False, "", "*" + encoded(32), encoded(31), encoded(32) + "="]:
            with self.subTest(value_type=type(value).__name__):
                values = configuration()
                values["HTTP_IDEMPOTENCY_HMAC_KEY"] = value
                self.assertFalse(FILTER.http_idempotency_valid(values))

    def test_aes_keys_must_all_be_exactly_32_bytes(self):
        for key in [None, 32, encoded(31), encoded(33), "*" + encoded(32)]:
            values = configuration()
            values["HTTP_IDEMPOTENCY_ENCRYPTION_KEYS"] = json.dumps({"v1": key, "v2": encoded(32)})
            self.assertFalse(FILTER.http_idempotency_valid(values))

    def test_requires_a_json_object_and_an_available_active_version(self):
        for keys in [None, False, "", "{", "{}", "[]", '"text"', "null", "32"]:
            values = configuration()
            values["HTTP_IDEMPOTENCY_ENCRYPTION_KEYS"] = keys
            self.assertFalse(FILTER.http_idempotency_valid(values))
        for active in [None, False, "", "v3", " v2", "v2 "]:
            values = configuration()
            values["HTTP_IDEMPOTENCY_ENCRYPTION_ACTIVE_VERSION"] = active
            self.assertFalse(FILTER.http_idempotency_valid(values))

    def test_version_names_follow_the_runtime_string_key_contract(self):
        for version in ["", "invalid.version", "a" * 33, "1", "0", "-1"]:
            values = configuration()
            values["HTTP_IDEMPOTENCY_ENCRYPTION_KEYS"] = json.dumps({version: encoded(32)})
            values["HTTP_IDEMPOTENCY_ENCRYPTION_ACTIVE_VERSION"] = version
            self.assertFalse(FILTER.http_idempotency_valid(values))
        for version in ["version_1-rotated", "a" * 32, "01"]:
            values = configuration()
            values["HTTP_IDEMPOTENCY_ENCRYPTION_KEYS"] = json.dumps({version: encoded(32)})
            values["HTTP_IDEMPOTENCY_ENCRYPTION_ACTIVE_VERSION"] = version
            self.assertTrue(FILTER.http_idempotency_valid(values))

    def test_php_accepted_missing_padding_and_whitespace_remain_compatible(self):
        values = configuration()
        values["HTTP_IDEMPOTENCY_HMAC_KEY"] = encoded(32).rstrip("=")
        values["HTTP_IDEMPOTENCY_ENCRYPTION_KEYS"] = json.dumps({"v2": "\n" + encoded(32).rstrip("=")})
        self.assertTrue(FILTER.http_idempotency_valid(values))

    def test_missing_non_object_configuration_returns_only_false(self):
        for values in [None, False, "PRIVATE_DELIVERY_MUST_NOT_LEAK", [], {}]:
            self.assertIs(False, FILTER.http_idempotency_valid(values))
        self.assertEqual({"fireguard_http_idempotency_valid"}, set(FILTER.FilterModule().filters()))

    def test_effective_validation_is_private_and_precedes_service_interruption(self):
        playbook = (ROOT / "ansible/deploy.yml").read_text()
        start = playbook.index("- name: Validate effective HTTP idempotency key configuration")
        end = playbook.index("- name: Install the stable host maintenance helper", start)
        block = playbook[start:end]
        self.assertLess(start, playbook.index("- name: Drain the installed auth Webhook runtime"))
        self.assertIn(".services.app.environment", block)
        self.assertIn("fireguard_http_idempotency_valid", block)
        self.assertIn("no_log: true", block)
        self.assertNotIn("when: fireguard_managed_env", block)
        self.assertNotIn("{{", block.split("msg:", 1)[1].split("- name: Require", 1)[0])


class RuntimeIdempotencyPolicyTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.php = shutil.which("php")
        if not cls.php:
            raise RuntimeError("PHP CLI is required for the deployment runtime policy regression tests")
        cls.play = yaml.safe_load((ROOT / "ansible/deploy.yml").read_text())[0]
        cls.entries = list(tasks(cls.play["tasks"]))
        cls.by_name = {entry.get("name", ""): entry for entry in cls.entries}
        cls.command = cls.by_name[RUNTIME_COMMAND]
        cls.probe = cls.command["vars"]["fireguard_http_idempotency_probe"]

    def execute(self, actual, expected, failure=""):
        with tempfile.TemporaryDirectory(prefix="fireguard-runtime-policy-") as directory:
            root = Path(directory)
            (root / "vendor").mkdir()
            (root / "vendor/autoload.php").write_text(FAKE_AUTOLOAD, encoding="utf-8")
            (root / "probe-state.json").write_text(
                json.dumps({"actual": actual, "failure": failure}), encoding="utf-8"
            )
            result = subprocess.run(
                [self.php, "-d", "memory_limit=1G", "-r", self.probe, expected],
                cwd=root, capture_output=True, text=True, check=False, timeout=10,
            )
            trace_path = root / "probe-trace.txt"
            trace = trace_path.read_text().splitlines() if trace_path.exists() else []
        self.assertNotIn(PRIVATE_TEST_MARKER, result.stdout + result.stderr)
        self.assertEqual("", result.stderr)
        return result, trace

    def test_accepts_only_the_exact_loaded_boolean_and_shuts_the_kernel_down(self):
        for actual, expected in [(True, "true"), (False, "false")]:
            with self.subTest(actual=actual):
                result, trace = self.execute(actual, expected)
                self.assertEqual(0, result.returncode)
                self.assertEqual(expected + "\n", result.stdout)
                self.assertEqual(
                    ["autoload", "construct", "boot", "container", "parameter", "shutdown"], trace
                )

    def test_rejects_an_opposite_runtime_boolean_without_returning_its_value(self):
        for actual, expected in [(True, "false"), (False, "true")]:
            with self.subTest(actual=actual):
                result, trace = self.execute(actual, expected)
                self.assertEqual(1, result.returncode)
                self.assertEqual("", result.stdout)
                self.assertEqual("shutdown", trace[-1])

    def test_rejects_non_boolean_container_parameters_without_exposing_them(self):
        for actual in ["true", "false", 0, 1, None, [], {}, PRIVATE_TEST_MARKER]:
            with self.subTest(actual_type=type(actual).__name__):
                result, trace = self.execute(actual, "true")
                self.assertEqual(1, result.returncode)
                self.assertEqual("", result.stdout)
                self.assertEqual("shutdown", trace[-1])

    def test_rejects_an_invalid_expectation_before_loading_any_application_code(self):
        for expected in ["", "1", "0", "TRUE", "False", " true", PRIVATE_TEST_MARKER]:
            with self.subTest(expected=expected):
                result, trace = self.execute(True, expected)
                self.assertEqual(1, result.returncode)
                self.assertEqual("", result.stdout)
                self.assertEqual([], trace)

    def test_application_failures_never_expose_exception_messages_or_backtraces(self):
        for stage in ["autoload", "construct", "boot", "container", "parameter", "shutdown"]:
            with self.subTest(stage=stage):
                result, trace = self.execute(True, "true", failure=stage)
                self.assertEqual(1, result.returncode)
                self.assertEqual("", result.stdout)
                self.assertEqual(stage, trace[-1])

    def test_probe_uses_argv_and_a_private_result_with_a_fixed_public_report(self):
        argv_template = self.command["ansible.builtin.command"]["argv"]
        self.assertIn("fireguard_compose_command.split()", argv_template)
        self.assertIn("'exec', '-T', 'app', 'php', '-d', 'memory_limit=1G', '-r'", argv_template)
        self.assertIn("fireguard_expected_http_idempotency_required", argv_template)
        self.assertTrue(self.command["no_log"])
        self.assertIs(False, self.command["changed_when"])
        self.assertIs(False, self.command["failed_when"])
        self.assertEqual("{{ fireguard_app_dir }}", self.command["args"]["chdir"])
        assertions = self.by_name[RUNTIME_ASSERT]["ansible.builtin.assert"]
        self.assertEqual(
            ["fireguard_runtime_http_idempotency_policy.rc == 0",
             "fireguard_runtime_http_idempotency_policy.stdout == fireguard_expected_http_idempotency_required"],
            assertions["that"],
        )
        for message in [assertions["fail_msg"], assertions["success_msg"]]:
            self.assertNotIn("{{", message)
            self.assertNotIn("stdout", message)
            self.assertNotIn("stderr", message)

    def test_only_managed_development_is_checked_after_health_before_worker_verification(self):
        block = self.by_name[RUNTIME_BLOCK]
        self.assertEqual(
            ["fireguard_deployment_environment == 'development'", "fireguard_managed_env"], block["when"]
        )
        environment = Environment()
        enabled = environment.compile_expression(" and ".join(block["when"]))
        for deployment, managed, expected in [
            ("development", True, True), ("development", False, False),
            ("production", True, False), ("production", False, False),
        ]:
            with self.subTest(deployment=deployment, managed=managed):
                self.assertIs(expected, enabled(
                    fireguard_deployment_environment=deployment, fireguard_managed_env=managed
                ))
        names = [entry.get("name", "") for entry in self.entries]
        self.assertLess(names.index("Wait for internal application health"), names.index(RUNTIME_COMMAND))
        self.assertLess(names.index(RUNTIME_ASSERT), names.index("Wait for every consumer receiver heartbeat"))
        default = self.play["vars"]["fireguard_expected_http_idempotency_required"]
        self.assertEqual("{{ lookup('env', 'HTTP_IDEMPOTENCY_REQUIRED') | default('true', true) }}", default)
        startup = self.by_name["Start application and verify internal health"]
        self.assertIn(RUNTIME_BLOCK, [entry["name"] for entry in startup["block"]])
        self.assertEqual("Stop every managed writer after unsuccessful startup", startup["rescue"][0]["name"])


if __name__ == "__main__":
    unittest.main()
