"""Run with python -B ansible/tests/test_idempotency_validation.py; no live services."""

import base64
import importlib.util
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location(
    "idempotency_validation", ROOT / "ansible/filter_plugins/idempotency_validation.py"
)
FILTER = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(FILTER)


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


if __name__ == "__main__":
    unittest.main()
