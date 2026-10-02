#!/usr/bin/env python3
"""Strict restart replay acceptance on an isolated hub with a persistent test volume.

No event is published after restart: receiving the pre-restart sentinel is the gate.
Only synthetic authorization is used, with no production key/env file access.
"""
import base64
import hashlib
import hmac
import json
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

IMAGE = "dunglas/mercure:v1.0.2@sha256:e5d8f06a60f2feb5e91f7ba2046d9184f6339b3498b5d2f5adaeb5bcb080bfc7"


def docker(*args, timeout=180):
    return subprocess.check_output(["docker", *args], stderr=subprocess.STDOUT, timeout=timeout).decode().strip()


def token(key, grants):
    def encode(value):
        return base64.urlsafe_b64encode(value).rstrip(b"=")
    header = encode(json.dumps({"alg": "HS256", "typ": "JWT"}).encode())
    payload = encode(json.dumps({"exp": int(time.time()) + 300, "mercure": grants}).encode())
    unsigned = header + b"." + payload
    return (unsigned + b"." + encode(hmac.new(key.encode(), unsigned, hashlib.sha256).digest())).decode()


def ready(url):
    for _ in range(60):
        try:
            with urllib.request.urlopen(url + "/healthz", timeout=2) as response:
                if response.status == 200:
                    return
        except (urllib.error.URLError, TimeoutError):
            time.sleep(1)
    raise AssertionError("Isolated hub did not become ready")


def event(response):
    values = {}
    for _ in range(100):
        line = response.readline().decode().strip()
        if not line and "data" in values:
            return values
        if line.startswith(("data:", "id:")):
            key, value = line.split(":", 1)
            values[key] = value.strip()
    raise AssertionError("No replay sentinel received")


def main():
    # Fail before creating resources if the local daemon is unavailable.
    docker("info", "--format", "{{.ServerVersion}}", timeout=10)
    identity = "fireguard-replay-" + uuid.uuid4().hex[:12]
    volume = identity + "-data"
    key = uuid.uuid4().hex + uuid.uuid4().hex
    topic = "https://acceptance.invalid/" + identity
    publisher = token(key, {"publish": [topic]})
    subscriber = token(key, {"subscribe": [topic]})
    created = False
    try:
        docker("volume", "create", volume)
        created = True
        docker("run", "-d", "--name", identity, "-p", "127.0.0.1::80", "-v", volume + ":/data",
               "-e", "SERVER_NAME=:80", "-e", "MERCURE_PUBLISHER_JWT_KEY=" + key,
               "-e", "MERCURE_SUBSCRIBER_JWT_KEY=" + key,
               "-e", "MERCURE_EXTRA_DIRECTIVES=protocol_version_compatibility 8\ntransport bolt {\n path /data/mercure.db\n size 10000\n cleanup_frequency 1\n}",
               "-e", "CADDY_SERVER_EXTRA_DIRECTIVES=respond /healthz 200", IMAGE)
        port = docker("port", identity, "80/tcp").rsplit(":", 1)[1]
        origin = "http://127.0.0.1:" + port
        endpoint = origin + "/.well-known/mercure"
        ready(origin)
        for event_id, payload in [("anchor", "anchor"), ("sentinel", "pre-restart-sentinel")]:
            body = urllib.parse.urlencode({"topic": topic, "id": identity + "-" + event_id, "data": payload, "private": "on"}).encode()
            request = urllib.request.Request(endpoint, data=body, headers={"Authorization": "Bearer " + publisher})
            with urllib.request.urlopen(request, timeout=5) as response:
                assert response.status == 200
        # Scope denial proves the signing key alone does not authorize another topic.
        forbidden = urllib.request.Request(endpoint, data=urllib.parse.urlencode({"topic": topic + "/forbidden", "data": "must-not-publish", "private": "on"}).encode(), headers={"Authorization": "Bearer " + publisher})
        try:
            urllib.request.urlopen(forbidden, timeout=5)
            raise AssertionError("Out-of-scope publication was accepted")
        except urllib.error.HTTPError as error:
            assert error.code == 403
        docker("restart", identity)
        ready(origin)
        url = endpoint + "?" + urllib.parse.urlencode({"topic": topic})
        request = urllib.request.Request(url, headers={"Authorization": "Bearer " + subscriber,
                                                      "Last-Event-ID": identity + "-anchor", "Accept": "text/event-stream"})
        with urllib.request.urlopen(request, timeout=8) as response:
            observed = event(response)
        assert observed == {"id": identity + "-sentinel", "data": "pre-restart-sentinel"}, observed
        try:
            urllib.request.urlopen(url, timeout=5)
            raise AssertionError("Anonymous subscription was accepted")
        except urllib.error.HTTPError as error:
            assert error.code in (401, 403)
        print("PASS: private pre-restart event replayed exactly; anonymous and out-of-scope access denied")
    finally:
        try:
            if created:
                docker("rm", "-f", identity, timeout=10)
        finally:
            docker("volume", "rm", volume, timeout=10)


if __name__ == "__main__":
    main()
