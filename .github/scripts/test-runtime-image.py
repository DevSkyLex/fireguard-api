"""Exercise the runtime guard in disposable containers, without services or volumes."""
import os
import subprocess
import unittest


class RuntimeImageTest(unittest.TestCase):
    def run_probe(self, script, *environment, root=False):
        command = ["docker", "run", "--rm", "--network", "none"]
        if root:
            command.extend(["--user", "0"])
        for value in environment:
            command.extend(["--env", value])
        command.extend(["--interactive", "--entrypoint", "sh", os.environ["RUNTIME_IMAGE"], "-s"])
        # Binary stdin preserves Unix shell newlines when the test host is Windows.
        result = subprocess.run(command, input=script.encode(), capture_output=True, timeout=30)
        return subprocess.CompletedProcess(
            result.args, result.returncode, result.stdout.decode(), result.stderr.decode()
        )

    def test_actual_runtime_is_accepted(self):
        result = self.run_probe("sh /usr/local/bin/check-runtime\n")
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)

    def test_embedded_php_serves_a_request(self):
        result = self.run_probe("""set -eu
mkdir /tmp/runtime-http-probe
printf '<?php echo PHP_VERSION;' > /tmp/runtime-http-probe/index.php
cat > /tmp/runtime-http-probe/Caddyfile <<'CADDY'
{
  admin off
  auto_https off
}
:8080 {
  root * /tmp/runtime-http-probe
  php_server
}
CADDY
frankenphp run --config /tmp/runtime-http-probe/Caddyfile --adapter caddyfile > /tmp/runtime-http-probe/server.log 2>&1 &
server_pid=$!
trap 'kill "$server_pid"; wait "$server_pid" || true' EXIT
attempt=0
until version=$(curl --fail --silent --show-error http://localhost:8080/); do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 10 ]; then cat /tmp/runtime-http-probe/server.log; exit 1; fi
  sleep 1
done
test "$version" = "$(php -r 'echo PHP_VERSION;')"
printf 'Embedded HTTP PHP %s\\n' "$version"
""")
        self.assertEqual(0, result.returncode, result.stdout + result.stderr)

    def test_incompatible_embedded_runtimes_are_rejected(self):
        # Keep the real PHP CLI; replace only FrankenPHP's version response.
        script = """set -eu
mkdir /tmp/runtime-probe
cat > /tmp/runtime-probe/frankenphp <<'SH'
#!/bin/sh
printf '%s\\n' "$FRANKENPHP_VERSION_OUTPUT"
SH
chmod +x /tmp/runtime-probe/frankenphp
PATH="/tmp/runtime-probe:$PATH" sh /usr/local/bin/check-runtime
"""
        for version in [
            "FrankenPHP v1.13.1 PHP 8.4.27 Caddy v2.11.7",
            "FrankenPHP v1.13.20 PHP 8.4.27 Caddy v2.11.7",
            "FrankenPHP v1.13.2 PHP 8.4.26 Caddy v2.11.7",
            "FrankenPHP v1.13.2 PHP 8.5.12 Caddy v2.11.7",
            "FrankenPHP v1.13.2 PHP 8.4.27-dev Caddy v2.11.7",
            "unrecognized version output",
        ]:
            with self.subTest(version=version):
                result = self.run_probe(script, "FRANKENPHP_VERSION_OUTPUT=" + version)
                self.assertEqual(1, result.returncode, result.stdout + result.stderr)
                self.assertIn("FrankenPHP 1.13.2 with PHP 8.4.27", result.stderr)

    def test_other_distributions_are_rejected(self):
        # Only the disposable container's writable layer is changed.
        for identity in ["ID=debian\nVERSION_CODENAME=trixie", "ID=ubuntu\nVERSION_CODENAME=bookworm"]:
            with self.subTest(identity=identity):
                result = self.run_probe(
                    'printf "PRETTY_NAME=probe\\n%s\\n" "$OS_IDENTITY" > /etc/os-release\n'
                    'sh /usr/local/bin/check-runtime\n',
                    "OS_IDENTITY=" + identity,
                    root=True,
                )
                self.assertEqual(1, result.returncode, result.stdout + result.stderr)
                self.assertIn("probe", result.stdout)


if __name__ == "__main__":
    unittest.main()
