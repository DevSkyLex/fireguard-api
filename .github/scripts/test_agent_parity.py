"""Check frontmatter, read-only tools, independence and project Fast settings."""
from pathlib import Path
import json
import shutil
import subprocess
import sys
import tempfile
import unittest

from check_agent_parity import check, claude_fields, validate_claude


def fields(name='fg-api-example-reviewer'):
    return {'name': name, 'description': 'Review', 'tools': 'Skill, Read, Grep, Glob, Bash',
            'model': 'sonnet', 'effort': 'high'}


class ClaudePolicyTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)

    def test_valid_native_fields(self):
        for model, effort in [('sonnet', 'medium'), ('sonnet', 'high'), ('opus', 'high'), ('opus', 'xhigh')]:
            data = fields()
            data.update(model=model, effort=effort)
            validate_claude(data, 'Assigned review.', self.root)

    def test_unknown_inherited_and_missing_model_effort_are_rejected(self):
        for key, value in [('model', 'inherit'), ('model', 'luna'), ('effort', ''), ('effort', 'max')]:
            data = fields()
            data[key] = value
            with self.subTest(key=key, value=value), self.assertRaises(ValueError):
                validate_claude(data, '', self.root)

    def test_read_only_roles_cannot_write_or_delegate(self):
        for tool in ['Edit', 'Write', 'NotebookEdit', 'Agent', 'Task']:
            data = fields()
            data['tools'] += ', ' + tool
            with self.subTest(tool=tool), self.assertRaisesRegex(ValueError, 'Read-only'):
                validate_claude(data, '', self.root)
        data = fields('fg-api-example-builder')
        data['tools'] += ', Edit, Write'
        validate_claude(data, '', self.root)

    def test_no_codex_agent_import_or_automatic_challenge(self):
        for text in ['Read .codex/agents/fg-api-example.toml.', 'codex exec -m gpt-6-luna', 'cd fireguard-api && codex exec review']:
            with self.subTest(text=text), self.assertRaises(ValueError):
                validate_claude(fields(), text, self.root)

    def test_missing_resource_and_fast_frontmatter_are_rejected(self):
        with self.assertRaisesRegex(ValueError, 'Missing Claude resource'):
            validate_claude(fields(), 'Read .claude/skills/missing/SKILL.md.', self.root)
        for key in ['fastMode', 'fast_mode', 'service_tier', 'model_reasoning_effort', 'permissionMode']:
            data = fields()
            data[key] = 'true'
            with self.assertRaises(ValueError):
                validate_claude(data, '', self.root)

    def test_historical_resource_and_placeholder_are_not_active_instructions(self):
        validate_claude(fields(), '<!-- tooling-references: historical -->\n'
                        'Read .claude/skills/removed/SKILL.md.\n<!-- /tooling-references -->\n'
                        'Read `.claude/skills/<skill>/SKILL.md`.', self.root)

    def test_duplicate_frontmatter_is_rejected(self):
        self.assertEqual(claude_fields('---\nname: example\nmodel: sonnet\n---\nBody')['model'], 'sonnet')
        with self.assertRaisesRegex(ValueError, 'Duplicate'):
            claude_fields('---\nmodel: sonnet\nmodel: opus\n---\n')
        with self.assertRaisesRegex(ValueError, 'Missing'):
            claude_fields('No frontmatter')


class CatalogScopeTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name) / 'fireguard-api'
        self.peer = Path(self.directory.name) / 'fireguard-web'
        source = Path(__file__).resolve().parents[2]
        for root, owner, peer in [(self.root, 'api', 'web'), (self.peer, 'web', 'api')]:
            for folder in ['.codex/agents', '.codex/scripts', '.claude/agents', '.agents/skills', '.github/scripts']:
                (root / folder).mkdir(parents=True)
            for name in ['agent_config.py', 'check_references.py']:
                shutil.copyfile(source / '.codex/scripts' / name, root / '.codex/scripts' / name)
            shutil.copyfile(source / '.github/scripts/check_agent_parity.py', root / '.github/scripts/check_agent_parity.py')
            role = f'fg-{owner}-example-builder'
            (root / '.codex/agents' / (role + '.toml')).write_text(
                f'name="{role}"\ndescription="Example"\ndeveloper_instructions="Assigned task."\n'
                'model="gpt-6-sol"\nmodel_reasoning_effort="high"\nservice_tier="default"\n'
                '[features]\nfast_mode=false\n', encoding='utf-8')
            (root / '.claude/agents' / (role + '.md')).write_text(
                f'---\nname: {role}\ndescription: Example\ntools: Read, Write\nmodel: sonnet\neffort: high\n---\nAssigned task.\n', encoding='utf-8')
            (root / '.claude/settings.json').write_text(json.dumps({'fastMode': False, 'env': {'CLAUDE_CODE_DISABLE_FAST_MODE': '1'}}), encoding='utf-8')
            (root / '.codex/config.example.toml').write_text(
                f'[agents.fg-{peer}-example-builder]\ndescription="Example"\n'
                f'config_file="../../fireguard-{peer}/.codex/agents/fg-{peer}-example-builder.toml"\n', encoding='utf-8')

    def test_shared_mode_checks_both_catalogs_from_either_owner(self):
        for root in [self.root, self.peer]:
            result = check(root)
            self.assertEqual(result['shared_agents'], 2)
            self.assertEqual(result['peer_definitions'], 'checked')
            self.assertFalse(result['runtime_verified'])

    def test_local_mode_reports_no_peer_count_and_never_reads_peer(self):
        (self.peer / '.codex/agents/fg-web-example-builder.toml').write_text('broken = [', encoding='utf-8')
        result = check(self.root, local_only=True)
        self.assertEqual(result['local_agents'], 1)
        self.assertIsNone(result['peer_agents'])
        self.assertIsNone(result['shared_agents'])
        self.assertEqual(result['peer_definitions'], 'not_checked')
        with self.assertRaises(ValueError):
            check(self.root)

    def test_local_mode_detects_parity_and_stale_entrypoint_names(self):
        entrypoint = self.root / 'CLAUDE.md'
        entrypoint.write_text('Use `fg-old-builder`.\n', encoding='utf-8')
        with self.assertRaisesRegex(ValueError, 'CLAUDE.md:1: fg-old-builder'):
            check(self.root, local_only=True)
        entrypoint.unlink()
        (self.root / '.claude/agents/fg-api-example-builder.md').unlink()
        with self.assertRaisesRegex(ValueError, 'parity mismatch'):
            check(self.root, local_only=True)

    def test_local_cli_failure_returns_nonzero_for_ci(self):
        (self.root / '.codex/config.example.toml').write_text(
            '[agents.fg-web-example-builder]\ndescription=""\n'
            'config_file="../../fireguard-web/.codex/agents/fg-web-example-builder.toml"\n', encoding='utf-8')
        result = subprocess.run([sys.executable, '-B', str(self.root / '.github/scripts/check_agent_parity.py'), '--local-only'],
                                capture_output=True, text=True, timeout=10)
        self.assertEqual(result.returncode, 1)
        self.assertIn('description', result.stderr)


if __name__ == '__main__':
    unittest.main()
