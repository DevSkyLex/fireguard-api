"""Offline tests of the actual deployment host-trust shell step."""

from pathlib import Path
import os
import re
import shutil
import subprocess
import tempfile
import textwrap
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/deploy-vps.yml"
DEPLOYMENT = "api"


def shell_path(path):
    value = Path(path).as_posix()
    if os.name == "nt" and len(value) > 1 and value[1] == ":":
        return "/" + value[0].lower() + value[2:]
    return value


def executable(name):
    if os.name == "nt":
        candidate = Path("C:/Program Files/Git/usr/bin") / (name + ".exe")
        if candidate.is_file():
            return str(candidate)
    result = shutil.which(name)
    if not result:
        raise RuntimeError(name + " is required for host-trust regressions")
    return result


class ReviewedSshHostTrustTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temporary = tempfile.TemporaryDirectory(prefix="fireguard ssh trust ")
        cls.directory = Path(cls.temporary.name)
        cls.shell = executable("bash")
        cls.keygen = executable("ssh-keygen")
        cls.key = cls.directory / "fixture-key"
        subprocess.run(
            [cls.keygen, "-q", "-t", "ed25519", "-N", "", "-f", shell_path(cls.key)],
            check=True, capture_output=True,
        )
        cls.public_key = (cls.directory / "fixture-key.pub").read_text().strip()
        workflow = WORKFLOW.read_text()
        step = workflow.split("      - name: Configure reviewed SSH host trust\n", 1)[1]
        step = step.split("      - name: ", 1)[0]
        cls.script = textwrap.dedent(step.split("        run: |\n", 1)[1])

    @classmethod
    def tearDownClass(cls):
        cls.temporary.cleanup()

    def run_step(self, material, host="vps.example.invalid", port="22"):
        with tempfile.TemporaryDirectory(dir=self.directory) as directory:
            temporary = Path(directory)
            environment = os.environ.copy()
            environment.update({
                "VPS_HOST": host,
                "VPS_PORT": port,
                "VPS_SSH_KNOWN_HOSTS": material,
                "RUNNER_TEMP": shell_path(temporary),
                "GITHUB_ENV": shell_path(temporary / "github-env"),
            })
            environment["PATH"] = str(Path(self.keygen).parent) + os.pathsep + environment["PATH"]
            result = subprocess.run(
                [self.shell, "-c", self.script], env=environment,
                capture_output=True, text=True, timeout=10,
            )
            installed = temporary / ("fireguard-" + DEPLOYMENT + "-known_hosts")
            saved = installed.read_text() if installed.exists() else None
            canonical = (temporary / "github-env").read_text() if (temporary / "github-env").exists() else None
            return result, saved, canonical

    def test_approved_hosts_and_ports(self):
        cases = [
            ("vps.example.invalid", "22", "vps.example.invalid"),
            ("192.0.2.10", "22", "192.0.2.10"),
            ("2001:db8::1", "22", "2001:db8::1"),
            ("vps.example.invalid", "2222", "[vps.example.invalid]:2222"),
            ("2001:db8::1", "2222", "[2001:db8::1]:2222"),
            ("vps.example.invalid", "00022", "vps.example.invalid"),
        ]
        for host, port, lookup in cases:
            with self.subTest(host=host, port=port):
                material = lookup + " " + self.public_key
                result, installed, canonical = self.run_step(material, host, port)
                self.assertEqual(0, result.returncode, result.stderr + result.stdout)
                self.assertEqual(material + "\n", installed)
                self.assertEqual("VPS_PORT=" + str(int(port)) + "\n", canonical)

    def test_missing_blank_unrelated_and_wrong_port_are_rejected(self):
        for material in ["", " \n\t", "other.example.invalid " + self.public_key,
                         "[vps.example.invalid]:2222 " + self.public_key]:
            with self.subTest(material=material[:24]):
                result, _, canonical = self.run_step(material)
                self.assertNotEqual(0, result.returncode)
                self.assertIsNone(canonical)

    def test_malformed_matching_key_is_rejected(self):
        result, _, canonical = self.run_step("vps.example.invalid ssh-ed25519 not-a-key")
        self.assertNotEqual(0, result.returncode)
        self.assertIsNone(canonical)

    def test_invalid_ports_are_rejected(self):
        for port in ["", "0", "65536", "100000", "-1", "22x"]:
            with self.subTest(port=port):
                result, _, canonical = self.run_step("vps.example.invalid " + self.public_key, port=port)
                self.assertNotEqual(0, result.returncode)
                self.assertIsNone(canonical)

    def test_hashed_host_and_approved_aliases_are_preserved(self):
        entry = self.directory / "hashed-hosts"
        entry.write_text("vps.example.invalid " + self.public_key + "\n")
        subprocess.run([self.keygen, "-H", "-f", shell_path(entry)], check=True, capture_output=True)
        material = entry.read_text().strip()
        result, installed, _ = self.run_step(material)
        self.assertEqual(0, result.returncode, result.stderr + result.stdout)
        self.assertEqual(material + "\n", installed)
        aliases = "vps.example.invalid,192.0.2.10 " + self.public_key
        result, installed, _ = self.run_step(aliases)
        self.assertEqual(0, result.returncode, result.stderr + result.stdout)
        self.assertEqual(aliases + "\n", installed)

    def test_workflow_requires_independent_trust_and_strict_handshake(self):
        workflow = WORKFLOW.read_text()
        self.assertNotIn("ssh-keyscan", workflow)
        self.assertIn("secrets.VPS_SSH_KNOWN_HOSTS", workflow)
        self.assertIn("StrictHostKeyChecking=yes", workflow)
        self.assertIn("GlobalKnownHostsFile=/dev/null", workflow)
        self.assertRegex(workflow, r"ANSIBLE_HOST_KEY_CHECKING[^\n]*(?:true|True)")
        self.assertLess(workflow.index("Configure reviewed SSH host trust"), workflow.index("ansible-playbook"))
        if DEPLOYMENT == "api":
            self.assertIn('UserKnownHostsFile=\\\"$RUNNER_TEMP/fireguard-api-known_hosts\\\"', workflow)
        else:
            self.assertIn("/fireguard-web-known_hosts", workflow)

    def test_missing_router_identity_still_stops_before_deployment(self):
        step = WORKFLOW.read_text().split("      - name: Validate required deployment configuration\n", 1)[1]
        script = textwrap.dedent(step.split("        run: |\n", 1)[1].split("      - name: ", 1)[0])
        required = (
            "VPS_HOST VPS_USER VPS_SSH_KEY VPS_SSH_KNOWN_HOSTS IMAGE_REF VPS_APP_DIR "
            "COMPOSE_PROJECT_NAME VOLUME_PREFIX API_HOST MERCURE_HOST "
            "TRAEFIK_API_ROUTER_NAME TRAEFIK_MERCURE_ROUTER_NAME DEFAULT_URI MERCURE_PUBLIC_URL"
        ).split()
        environment = os.environ.copy()
        environment.update({name: "fixture" for name in required})
        environment.update({"SOURCE_BRANCH": "main", "DEPLOY_ENVIRONMENT": "production", "RESET_DEVELOPMENT_FIXTURES": "false"})
        environment.pop("TRAEFIK_API_ROUTER_NAME", None)
        result = subprocess.run([self.shell, "-c", script], env=environment, capture_output=True, text=True, timeout=10)
        self.assertNotEqual(0, result.returncode)
        self.assertIn("TRAEFIK_API_ROUTER_NAME is required", result.stdout)


if __name__ == "__main__":
    unittest.main()
