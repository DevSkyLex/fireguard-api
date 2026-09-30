"""Exercise native settings, permission guards, peer ownership and validator integration."""
from pathlib import Path
import json
import tempfile
import unittest

from agent_config import (
    GLOBAL_POLICY_KEYS, MODEL_EFFORTS, validate_global_policy, validate_native_agent,
    validate_native_references, validate_role_registry,
)
from validate import validate


def agent(name='fg-api-example-builder', model='gpt-6.1-sol', effort='high'):
    fast = model == 'gpt-6-luna'
    result = {'name': name, 'description': 'Example', 'developer_instructions': 'Bounded task.',
              'model': model, 'model_reasoning_effort': effort,
              'service_tier': 'fast' if fast else 'default', 'features': {'fast_mode': fast}}
    if name.endswith(('-reviewer', '-auditor', '-explorer')):
        result['sandbox_mode'] = 'read-only'
    return result


def toml(data):
    scalar = ''.join(f'{key} = {json.dumps(value)}\n' for key, value in data.items() if key != 'features')
    return scalar + '\n[features]\nfast_mode = ' + str(data['features']['fast_mode']).lower() + '\n'


class NativePolicyTests(unittest.TestCase):
    def test_accepts_all_assigned_model_effort_combinations(self):
        for model, efforts in MODEL_EFFORTS.items():
            for effort in efforts:
                with self.subTest(model=model, effort=effort):
                    validate_native_agent(agent(model=model, effort=effort), 'role.toml')

    def test_rejects_missing_unknown_and_inherited_model_effort(self):
        for key, values in {'model': [None, 'inherit', 'gpt-6-terra', True],
                            'model_reasoning_effort': [None, 'inherit', 'low', True]}.items():
            for value in values:
                data = agent()
                data[key] = value
                with self.subTest(key=key, value=value), self.assertRaisesRegex(ValueError, key):
                    validate_native_agent(data, 'role.toml')
        with self.assertRaisesRegex(ValueError, 'model_reasoning_effort'):
            validate_native_agent(agent(model='gpt-6-astra', effort='medium'), 'role.toml')

    def test_fast_settings_are_explicit_and_luna_only(self):
        for model in MODEL_EFFORTS:
            for key in ['service_tier', 'features']:
                data = agent(model=model)
                data.pop(key)
                with self.subTest(model=model, key=key), self.assertRaises(ValueError):
                    validate_native_agent(data, 'role.toml')
            data = agent(model=model)
            data['service_tier'] = 'default' if model == 'gpt-6-luna' else 'fast'
            with self.assertRaisesRegex(ValueError, 'service_tier'):
                validate_native_agent(data, 'role.toml')
            for feature in [None, {}, {'fast_mode': 'false'}, {'fast_mode': 1}, {'fast_mode': model != 'gpt-6-luna'}, {'fast_mode': False, 'extra': True}]:
                data = agent(model=model)
                data['features'] = feature
                with self.subTest(model=model, feature=feature), self.assertRaisesRegex(ValueError, 'fast_mode'):
                    validate_native_agent(data, 'role.toml')

    def test_permissions_and_model_defaults_cannot_be_elevated(self):
        for key in ['approval_policy', 'mcp_servers', 'projects', 'permissions', 'default_subagent_model']:
            data = agent()
            data[key] = 'override'
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, key):
                validate_native_agent(data, 'role.toml')
        for suffix in ['reviewer', 'auditor', 'explorer']:
            data = agent('fg-api-example-' + suffix)
            validate_native_agent(data, 'role.toml')
            for mode in [None, 'workspace-write', 'danger-full-access']:
                data['sandbox_mode'] = mode
                with self.subTest(mode=mode), self.assertRaisesRegex(ValueError, 'Read-only'):
                    validate_native_agent(data, 'role.toml')
        for mode in ['read-only', 'workspace-write', 'danger-full-access']:
            data = agent()
            data['sandbox_mode'] = mode
            with self.assertRaisesRegex(ValueError, 'inherit'):
                validate_native_agent(data, 'role.toml')

    def test_global_policy_preserves_user_preferences_and_concurrency(self):
        for key in GLOBAL_POLICY_KEYS:
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, key):
                validate_global_policy({key: 'override'})
        for key in ['default_subagent_model', 'default_subagent_reasoning_effort']:
            with self.assertRaisesRegex(ValueError, key):
                validate_global_policy({'agents': {key: 'override'}})
        with self.assertRaisesRegex(ValueError, 'fast_mode'):
            validate_global_policy({'features': {'fast_mode': True}})
        validate_global_policy({'agents': {'max_threads': 4, 'max_depth': 1}, 'mcp_servers': {}})

    def test_malformed_fireguard_name_is_rejected(self):
        with self.assertRaisesRegex(ValueError, 'Invalid FireGuard'):
            validate_native_agent(agent('fg-api-Invalid'), 'role.toml')

    def test_native_references_cannot_escape_the_owner_or_be_missing(self):
        with tempfile.TemporaryDirectory() as folder:
            root = Path(folder)
            reference = root / '.codex/references/details.md'
            reference.parent.mkdir(parents=True)
            reference.write_text('Reference', encoding='utf-8')
            validate_native_references('Read .codex/references/details.md.', root)
            reference.unlink()
            with self.assertRaisesRegex(ValueError, 'agent reference'):
                validate_native_references('Read .codex/references/details.md.', root)

    def test_native_references_honor_explicit_history_and_local_configuration(self):
        with tempfile.TemporaryDirectory() as folder:
            validate_native_references('<!-- tooling-references: historical -->\n'
                                       'Read .codex/references/removed.md.\n<!-- /tooling-references -->\n'
                                       'Read `.codex/config.toml` and `.codex/agents/<role>.toml`.', Path(folder))


class RegistryTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        parent = Path(self.directory.name)
        self.root = parent / 'fireguard-api'
        self.peer = parent / 'fireguard-web'
        for root, name in [(self.root, 'fg-api-example-builder'), (self.peer, 'fg-web-example-builder')]:
            (root / '.codex/agents').mkdir(parents=True)
            (root / '.codex/agents' / (name + '.toml')).write_text(toml(agent(name)), encoding='utf-8')
        self.config_path = self.root / '.codex/config.example.toml'
        self.entry = {'config_file': '../../fireguard-web/.codex/agents/fg-web-example-builder.toml', 'description': 'Example'}
        self.config = {'agents': {'fg-web-example-builder': self.entry}}

    def test_valid_peer_from_either_primary_folder(self):
        self.assertEqual(validate_role_registry(self.config, self.config_path), 1)
        reverse = {'agents': {'fg-api-example-builder': {
            'config_file': '../../fireguard-api/.codex/agents/fg-api-example-builder.toml', 'description': 'Example'}}}
        self.assertEqual(validate_role_registry(reverse, self.peer / '.codex/config.example.toml'), 1)

    def test_missing_peer_has_explicit_failure(self):
        (self.peer / '.codex/agents/fg-web-example-builder.toml').unlink()
        with self.assertRaisesRegex(ValueError, 'Missing'):
            validate_role_registry(self.config, self.config_path)

    def test_local_mode_does_not_read_missing_or_malformed_peer_definitions(self):
        target = self.peer / '.codex/agents/fg-web-example-builder.toml'
        target.write_text('malformed = [', encoding='utf-8')
        self.assertEqual(validate_role_registry(self.config, self.config_path, local_only=True), 1)
        target.unlink()
        self.assertEqual(validate_role_registry(self.config, self.config_path, local_only=True), 1)
        self.assertEqual(validate_role_registry({}, self.config_path, local_only=True), 0)

    def test_local_mode_still_rejects_bad_descriptions_ownership_and_paths(self):
        for value in [None, '', '  ', 5]:
            entry = {**self.entry, 'description': value}
            with self.subTest(description=value), self.assertRaisesRegex(ValueError, 'description'):
                validate_role_registry({'agents': {'fg-web-example-builder': entry}}, self.config_path, local_only=True)
        for path in ['../../../outside.toml', '../../fireguard-api/.codex/agents/fg-web-example-builder.toml']:
            entry = {**self.entry, 'config_file': path}
            with self.subTest(path=path), self.assertRaisesRegex(ValueError, 'config_file'):
                validate_role_registry({'agents': {'fg-web-example-builder': entry}}, self.config_path, local_only=True)
        local = {'config_file': '../../fireguard-api/.codex/agents/fg-api-other-builder.toml', 'description': 'Other'}
        with self.assertRaisesRegex(ValueError, 'local owner'):
            validate_role_registry({'agents': {'fg-api-other-builder': local}}, self.config_path, local_only=True)

    def test_rejects_absolute_escaped_and_wrong_owner_paths(self):
        for value in ['C:/outside/agent.toml', '/outside/agent.toml', '../../../agent.toml',
                      '../../fireguard-api/.codex/agents/fg-web-example-builder.toml']:
            self.entry['config_file'] = value
            with self.subTest(value=value), self.assertRaisesRegex(ValueError, 'config_file'):
                validate_role_registry(self.config, self.config_path)

    def test_rejects_duplicate_mismatched_and_extra_settings(self):
        with self.assertRaisesRegex(ValueError, 'Duplicate'):
            validate_role_registry({'agents': {'fg-api-example-builder': self.entry}}, self.config_path)
        self.entry['description'] = 'Stale description'
        with self.assertRaisesRegex(ValueError, 'mismatch'):
            validate_role_registry(self.config, self.config_path)
        self.entry['model'] = 'override'
        with self.assertRaisesRegex(ValueError, 'declaration'):
            validate_role_registry(self.config, self.config_path)

    def test_requires_complete_peer_coverage(self):
        with self.assertRaisesRegex(ValueError, 'coverage'):
            validate_role_registry({}, self.config_path)


class ValidatorIntegrationTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        for folder in ['.agents/skills', '.codex/agents']:
            (self.root / folder).mkdir(parents=True)
        self.write('.codex/config.example.toml', '')
        self.write('.codex/rules.md', '# Rules\n')
        self.write('.codex/hooks.json', json.dumps({'hooks': {'PreToolUse': [], 'PostToolUse': []}}))
        self.write('.agents/skills.lock.json', json.dumps({'schema_version': 2, 'hashing': {
            'algorithm': 'sha256', 'text_normalization': 'utf8-lf', 'text_extensions': ['.md']}, 'packages': []}))

    def write(self, relative, content):
        (self.root / relative).write_text(content, encoding='utf-8')

    def test_catalog_counts_remain_dynamic(self):
        self.assertEqual(validate(self.root)['agents'], 0)
        for index in range(3):
            name = f'fg-api-fixture-{index}-builder'
            self.write(f'.codex/agents/{name}.toml', toml(agent(name)))
            self.assertEqual(validate(self.root)['agents'], index + 1)

    def test_duplicate_names_are_rejected(self):
        for file in ['first', 'second']:
            self.write(f'.codex/agents/{file}.toml', toml(agent()))
        with self.assertRaisesRegex(AssertionError, 'Duplicate agent'):
            validate(self.root)

    def test_missing_model_and_reference_are_rejected(self):
        data = agent()
        data.pop('model')
        self.write('.codex/agents/example.toml', toml(data))
        with self.assertRaisesRegex(ValueError, 'model'):
            validate(self.root)
        data = agent()
        data['developer_instructions'] = 'Read .codex/references/missing.md.'
        self.write('.codex/agents/example.toml', toml(data))
        with self.assertRaisesRegex(ValueError, r'example.toml:3: .codex/references/missing.md'):
            validate(self.root)

    def test_global_effort_and_broken_links_are_rejected(self):
        self.write('.codex/config.example.toml', 'model_reasoning_effort="high"\n')
        with self.assertRaisesRegex(ValueError, 'model_reasoning_effort'):
            validate(self.root)
        self.write('.codex/config.example.toml', '')
        self.write('.codex/workflow.md', '[Missing](references/missing.md)\n')
        with self.assertRaisesRegex(ValueError, 'Broken local Markdown links'):
            validate(self.root)

    def test_local_scope_explicitly_reports_unchecked_peer_and_checks_inline_paths(self):
        self.write('.codex/agents/fg-api-example-builder.toml', toml(agent()))
        self.write('.codex/config.example.toml', '[agents.fg-web-example-builder]\n'
                   'description="Example"\nconfig_file="../../fireguard-web/.codex/agents/fg-web-example-builder.toml"\n')
        result = validate(self.root, local_only=True)
        self.assertEqual(result['scope'], 'local')
        self.assertEqual(result['peer_definitions'], 'not_checked')
        with self.assertRaisesRegex(ValueError, 'Missing'):
            validate(self.root)
        self.write('.codex/workflow.md', 'Read `.codex/scripts/resolve_agent.py`.\n')
        with self.assertRaisesRegex(ValueError, r'workflow.md:1: .codex/scripts/resolve_agent.py'):
            validate(self.root, local_only=True)


if __name__ == '__main__':
    unittest.main()
