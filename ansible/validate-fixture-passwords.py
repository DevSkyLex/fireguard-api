"""Reject unsafe development fixture credentials before deployment changes state."""

import json
import os
import sys


def fail(message: str) -> None:
    sys.exit(message)


try:
    passwords = json.loads(os.environ.get("FIXTURE_USER_PASSWORDS_JSON", ""))
except json.JSONDecodeError:
    fail("DEVELOPMENT_FIXTURE_USER_PASSWORDS_JSON must be valid JSON.")

required = {"admin", "test", "demo", "staff", "dev_client"}
if not isinstance(passwords, dict) or set(passwords) != required:
    fail("DEVELOPMENT_FIXTURE_USER_PASSWORDS_JSON must define exactly the five fixture account groups.")

if any(
    not isinstance(value, str)
    or not 16 <= len(value.encode("utf-8")) <= 72
    or not value.strip()
    or "\0" in value
    for value in passwords.values()
):
    fail("Every development fixture password must contain 16 to 72 bytes without NUL or blank values.")

if len(set(passwords.values())) != len(required):
    fail("Development fixture passwords must be distinct between account groups.")
