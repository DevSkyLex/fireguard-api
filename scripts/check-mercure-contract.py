#!/usr/bin/env python3
"""Exercise the configured hub with synthetic credentials on disposable Docker resources.

Run from any directory with Docker and PyYAML installed. No Compose invocation or
environment file is used: only the two committed YAML documents are read.
"""

from __future__ import annotations

import base64
import argparse
import hashlib
import hmac
import json
from pathlib import Path
import re
import shlex
import subprocess
import sys
import time
import uuid

import yaml


ROOT = Path(__file__).resolve().parents[1]
KEY = "fireguard-mercure-compat-check-synthetic-key-not-for-deployment"
TOPIC = "/users/compat-check-user/notifications"
HUB = "http://localhost/.well-known/mercure"
LABEL = "fireguard.compat-check"


def require(condition: bool, message: str) -> None:
    if not condition:
        raise RuntimeError(message)


def jwt(subscribe: list[str], publish: list[str], *, key: str = KEY, expired: bool = False) -> str:
    """Match Symfony's legacy HS256 mercure grants, without iss/aud claims."""
    claims: dict = {"mercure": {"subscribe": subscribe, "publish": publish}}
    if subscribe:
        claims["exp"] = int(time.time()) + (-60 if expired else 900)

    def encode(value: dict | bytes) -> str:
        data = value if isinstance(value, bytes) else json.dumps(value, separators=(",", ":")).encode()
        return base64.urlsafe_b64encode(data).rstrip(b"=").decode()

    body = f'{encode({"typ": "JWT", "alg": "HS256"})}.{encode(claims)}'
    return f"{body}.{encode(hmac.new(key.encode(), body.encode(), hashlib.sha256).digest())}"


class ContractCheck:
    def __init__(self) -> None:
        self.run = f"fireguard-mercure-compat-check-{uuid.uuid4().hex[:12]}"
        self.network = f"{self.run}-net"
        self.volume = f"{self.run}-data"
        self.created: list[tuple[str, str]] = []
        self.tokens = {
            "publisher": jwt([], ["*"]),
            "subscriber": jwt([TOPIC], []),
            "wrong_signature": jwt([TOPIC], [], key=KEY + "-wrong"),
            "expired": jwt([TOPIC], [], expired=True),
            "other_user": jwt(["/users/another-user/notifications"], []),
        }

    def redact(self, value: str) -> str:
        for token in self.tokens.values():
            value = value.replace(token, "[REDACTED]")
        return value.replace(KEY, "[SYNTHETIC KEY]")

    def docker(self, *args: str, allowed: tuple[int, ...] = (0,), timeout: int = 60, include_stderr: bool = False) -> str:
        try:
            result = subprocess.run(
                ["docker", *args], capture_output=True, text=True, timeout=timeout, check=False
            )
        except subprocess.TimeoutExpired as error:
            # Do not print the command: an exec argument may contain a synthetic JWT.
            raise RuntimeError(f"Docker {args[0]} exceeded {timeout}s") from error
        require(
            result.returncode in allowed,
            f"Docker {args[0]} failed ({result.returncode}): {self.redact(result.stderr.strip())}",
        )
        return (result.stdout + (result.stderr if include_stderr else "")).strip()

    def configurations(self) -> list[tuple[str, dict]]:
        configurations = []
        for filename in ("compose.yaml", "compose.prod.yaml"):
            with (ROOT / filename).open(encoding="utf-8") as source:
                service = yaml.safe_load(source)["services"]["mercure"]
            require(
                re.fullmatch(r"dunglas/mercure:[^@]+@sha256:[a-f0-9]{64}", service["image"]) is not None,
                f"{filename}: Mercure must use an immutable tagged image digest",
            )
            require(isinstance(service["environment"], dict), f"{filename}: expected environment mapping")
            configurations.append((filename, service))
        require(
            configurations[0][1]["image"] == configurations[1][1]["image"],
            "Development and production must use the same Mercure image",
        )
        return configurations

    def setup(self) -> None:
        self.docker("network", "create", "--internal", "--label", f"{LABEL}={self.run}", self.network)
        self.created.append(("network", self.network))
        self.docker("volume", "create", "--label", f"{LABEL}={self.run}", self.volume)
        self.created.append(("volume", self.volume))

    def start(self, name: str, service: dict) -> str:
        container = f"{self.run}-{name}"
        args = [
            "run", "--detach", "--name", container, "--label", f"{LABEL}={self.run}",
            "--network", self.network, "--tmpfs", "/config",
            "--mount", f"type=volume,source={self.volume},target=/data",
        ]
        for key, raw_value in service["environment"].items():
            if key in ("MERCURE_PUBLISHER_JWT_KEY", "MERCURE_SUBSCRIBER_JWT_KEY"):
                value = KEY
            else:
                value = str(raw_value)
                value = re.sub(r"\$\{MERCURE_CORS_ORIGINS[^}]*\}", "http://localhost:4200", value)
                require("${" not in value, f"Unresolved test configuration variable in {key}")
            args.extend(["--env", f"{key}={value}"])
        healthcheck = service.get("healthcheck")
        if healthcheck:
            require(not healthcheck.get("disable"), "Configured Mercure healthcheck must not be disabled")
            test = healthcheck["test"]
            require(isinstance(test, list) and test[0] in ("CMD", "CMD-SHELL"), "Unsupported healthcheck")
            command = shlex.join(test[1:]) if test[0] == "CMD" else test[1]
            args.extend(["--health-cmd", command])
            for compose_option, docker_option in (
                ("interval", "--health-interval"), ("timeout", "--health-timeout"),
                ("start_period", "--health-start-period"), ("retries", "--health-retries"),
            ):
                if compose_option in healthcheck:
                    args.extend([docker_option, str(healthcheck[compose_option])])
        args.append(service["image"])
        command = service.get("command")
        if command:
            args.extend(shlex.split(command) if isinstance(command, str) else command)
        # Register the unique name first so a failed start is also cleaned up.
        self.created.append(("container", container))
        self.docker(*args, timeout=180)
        for _ in range(100):
            state = json.loads(self.docker("inspect", "--format", "{{json .State}}", container))
            require(state["Running"], f"{name}: hub exited before becoming ready")
            status = self.docker(
                "exec", container, "curl", "-s", "--max-time", "1", "-o", "/dev/null",
                "-w", "%{http_code}", "http://localhost/healthz", allowed=(0, 7, 28),
            )
            if status == "200":
                return container
            time.sleep(0.1)
        raise RuntimeError(f"{name}: /healthz never returned 200")

    def health(self, container: str) -> None:
        configuration = json.loads(self.docker("inspect", "--format", "{{json .Config}}", container))
        test = configuration.get("Healthcheck", {}).get("Test", [])
        require(test and test[0] in ("CMD", "CMD-SHELL"), "No active Docker healthcheck")
        command = test[1:] if test[0] == "CMD" else ["sh", "-c", test[1]]
        self.docker("exec", container, *command)
        if any("2019" in part for part in test):
            for endpoint in ("ready", "live"):
                status = self.docker(
                    "exec", container, "curl", "-sS", "-o", "/dev/null", "-w", "%{http_code}",
                    f"http://127.0.0.1:2019/mercure/health/{endpoint}",
                )
                require(status == "200", f"Native Mercure {endpoint} health returned {status}")
        for _ in range(70):
            status = self.docker("inspect", "--format", "{{.State.Health.Status}}", container)
            if status == "healthy":
                return
            require(status != "unhealthy", "Configured Docker healthcheck marked the hub unhealthy")
            time.sleep(0.5)
        raise RuntimeError("Docker healthcheck never reached healthy")

    def publish(self, container: str, data: str, token: str | None = None) -> tuple[str, str]:
        status = self.docker(
            "exec", container, "curl", "-sS", "--max-time", "5", "-o", "/tmp/publish.body",
            "-w", "%{http_code}", "-H", f"Authorization: Bearer {token or self.tokens['publisher']}",
            "--data-urlencode", f"topic={TOPIC}", "--data-urlencode", f"data={data}",
            "--data", "private=on", HUB,
        )
        body = self.docker("exec", container, "cat", "/tmp/publish.body")
        return status, body

    def history(self, container: str, after: str, token: str | None = None, *, query: bool = False) -> tuple[str, str]:
        credential = token or self.tokens["subscriber"]
        authentication = ["--data-urlencode", f"authorization={credential}"] if query else ["-H", f"Authorization: Bearer {credential}"]
        status = self.docker(
            "exec", container, "curl", "-s", "-N", "--max-time", "2",
            "-o", "/tmp/history.sse", "-w", "%{http_code}", *authentication,
            "-H", f"Last-Event-ID: {after}", "--get", "--data-urlencode", f"topic={TOPIC}", HUB,
            allowed=(0, 28),
        )
        return status, self.docker("exec", container, "cat", "/tmp/history.sse")

    def exercise(self, container: str, label: str, anchor: str) -> None:
        self.docker(
            "exec", "--detach", "--env", f"CHECK_TOKEN={self.tokens['subscriber']}",
            "--env", f"CHECK_TOPIC={TOPIC}", container, "sh", "-c",
            'curl -s -N --max-time 8 -D /tmp/live.headers -o /tmp/live.sse '
            '-H "Authorization: Bearer $CHECK_TOKEN" --get --data-urlencode "topic=$CHECK_TOPIC" '
            'http://localhost/.well-known/mercure',
        )
        for _ in range(50):
            headers = self.docker("exec", container, "cat", "/tmp/live.headers", allowed=(0, 1))
            if "200 OK" in headers:
                break
            time.sleep(0.1)
        else:
            raise RuntimeError(f"{label}: private live subscription never opened")
        payload = f"{label}-private-event"
        status, event_id = self.publish(container, payload)
        require(status == "200" and event_id, f"{label}: private publication failed ({status})")
        for _ in range(50):
            body = self.docker("exec", container, "cat", "/tmp/live.sse")
            if f"data: {payload}" in body:
                break
            time.sleep(0.1)
        else:
            raise RuntimeError(f"{label}: live private update was not delivered")
        for query in (False, True):
            status, body = self.history(container, anchor, query=query)
            require(status == "200" and f"data: {payload}" in body and f"id: {event_id}" in body, f"{label}: replay failed (query auth={query})")
            require(f"id: {anchor}\n" not in body + "\n", f"{label}: replay included Last-Event-ID anchor")
        for kind in ("wrong_signature", "expired"):
            status, _ = self.history(container, anchor, self.tokens[kind])
            require(status == "401", f"{label}: {kind} JWT was not rejected with 401 ({status})")
        status, _ = self.publish(container, "must-not-publish", self.tokens["subscriber"])
        require(status in ("401", "403"), f"{label}: subscribe-only JWT was allowed to publish ({status})")
        status, body = self.history(container, anchor, self.tokens["other_user"])
        require(status in ("200", "403") and "data:" not in body, f"{label}: another user's JWT received private data")
        logs = self.docker("logs", container, include_stderr=True)
        require("authorization=REDACTED" in logs, f"{label}: query authorization redaction not observed")
        require(KEY not in logs, f"{label}: signing key appeared in container logs")
        require(all(token not in logs for token in self.tokens.values()), f"{label}: JWT appeared in container logs")
        print(f"PASS {label}: health, legacy JWT, private delivery/replay, denial paths, redacted logs", flush=True)

    def stop(self, container: str) -> None:
        self.docker("stop", "--time", "30", container)
        code = self.docker("inspect", "--format", "{{.State.ExitCode}}", container)
        require(code == "0", f"Hub did not shut down cleanly (exit {code})")
        self.docker("rm", container)
        self.created.remove(("container", container))

    def cleanup(self) -> None:
        errors = []
        for kind, name in reversed(self.created):
            require(name.startswith(self.run + "-"), "Refusing cleanup of a foreign resource")
            inspect = ("inspect",) if kind == "container" else (kind, "inspect")
            raw = self.docker(*inspect, name, allowed=(0, 1))
            resources = json.loads(raw) if raw else []
            if not resources:
                continue
            resource = resources[0]
            labels = resource["Config"].get("Labels", {}) if kind == "container" else resource.get("Labels", {})
            if labels.get(LABEL) != self.run:
                errors.append(f"Refusing cleanup of unowned {kind} {name}")
                continue
            try:
                args = ("rm", "--force", name) if kind == "container" else (kind, "rm", name)
                self.docker(*args)
            except RuntimeError as error:
                errors.append(str(error))
        require(not errors, "; ".join(errors))

    def run_checks(self, *, check_restart_history: bool) -> None:
        configurations = self.configurations()
        try:
            self.setup()
            first = self.start("development", configurations[0][1])
            self.health(first)
            status, anchor = self.publish(first, "history-anchor")
            require(status == "200" and anchor, "Unable to publish the history anchor")
            self.exercise(first, "development", anchor)
            self.stop(first)
            second = self.start("production", configurations[1][1])
            self.health(second)
            if check_restart_history:
                # This must run BEFORE any new publication: warming up the transport
                # would hide the known Mercure 1.0.0 startup replay defect.
                status, body = self.history(second, anchor)
                require(
                    status == "200" and "data: development-private-event" in body,
                    "Persisted private history unavailable immediately after clean restart, before any new publication (known upstream Mercure 1.0.0 defect)",
                )
                print("PASS persisted history immediately after clean restart", flush=True)
            else:
                print("NOT CHECKED: restart history; use --check-restart-history (known failing diagnostic with Mercure 1.0.0)", flush=True)
            status, anchor = self.publish(second, "production-history-anchor")
            require(status == "200" and anchor, "Unable to publish the production contract anchor")
            self.exercise(second, "production", anchor)
            self.stop(second)
        finally:
            self.cleanup()
        print("PASS Mercure contract; all disposable resources removed", flush=True)


if __name__ == "__main__":
    parser = argparse.ArgumentParser(
        description=__doc__,
        epilog="The default smoke checks live contracts only. Mercure 1.0.0 has a known persisted-history replay defect immediately after restart; the explicit diagnostic must fail until that upstream behavior is fixed.",
    )
    parser.add_argument(
        "--check-restart-history", action="store_true",
        help="also require persisted replay immediately after clean restart, before any new publication (currently fails on Mercure 1.0.0)",
    )
    arguments = parser.parse_args()
    check = ContractCheck()
    try:
        check.run_checks(check_restart_history=arguments.check_restart_history)
    except (RuntimeError, OSError, ValueError, KeyError) as error:
        print(f"FAIL Mercure contract: {check.redact(str(error))}", file=sys.stderr)
        sys.exit(1)
