"""Check the shared MCP example, independently of ignored local configuration."""
from pathlib import Path
import tomllib
import unittest

from validate import validate_mcp_portability


class PortabilityTests(unittest.TestCase):
    def test_shared_example_is_portable(self):
        root = Path(__file__).resolve().parents[2]
        config = tomllib.loads((root / '.codex/config.example.toml').read_text(encoding='utf-8'))
        self.assertTrue(config['mcp_servers'])
        validate_mcp_portability(config)

    def test_rejects_machine_paths_in_shared_settings(self):
        for absolute in ['D:/workspace/checkout', r'D:\workspace\checkout', '/home/developer/checkout', r'\\host\share\checkout']:
            for field in ['cwd', 'command', 'args', 'env']:
                with self.subTest(path=absolute, field=field):
                    server = {'command': 'npx', 'args': ['-y', 'example-mcp']}
                    server[field] = {
                        'cwd': absolute, 'command': absolute,
                        'args': ['--root', absolute], 'env': {'PROJECT_ROOT': absolute},
                    }[field]
                    with self.assertRaises(AssertionError):
                        validate_mcp_portability({'mcp_servers': {'example': server}})

    def test_accepts_native_cwd_placeholders_and_urls(self):
        validate_mcp_portability({'mcp_servers': {'example': {
            'command': 'npx', 'args': ['--read-only', 'example-mcp'],
            'cwd': '<WEB_CHECKOUT>', 'env': {'API_URL': 'https://example.com'},
        }}})


if __name__ == '__main__':
    unittest.main()
