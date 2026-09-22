"""Fixture tests for logical profiles and native role policy; no runtime models."""
from pathlib import Path
import json
import tempfile
import unittest

from agent_profiles import (
    CATEGORIES, EFFORTS, GLOBAL_POLICY_KEYS, load_profiles, parse_profiles,
    validate_global_policy, validate_native_agent, validate_native_references,
    validate_profile_coverage,
)
from validate import validate


def profile(category='sol', effort='high'):
    return {'category': category, 'effort': effort}


class ProfileTests(unittest.TestCase):
    def test_all_logical_categories_and_efforts_are_supported(self):
        for category in CATEGORIES:
            for effort in EFFORTS:
                with self.subTest(category=category, effort=effort):
                    document = {'agents': {'fg-web-example-builder': profile(category, effort)}}
                    self.assertEqual(parse_profiles(document), document['agents'])

    def test_rejects_incomplete_extra_and_versioned_profile_fields(self):
        invalid = [
            None, [], {}, {'category': 'sol'}, {'effort': 'high'},
            {'category': 'sol', 'effort': 'high', 'model': 'gpt-6-sol'},
            profile('gpt-6-sol'), profile('unknown'), profile(None),
            profile(effort='low'), profile(effort=''), profile(effort=True),
        ]
        for entry in invalid:
            with self.subTest(entry=entry), self.assertRaises(ValueError):
                parse_profiles({'agents': {'fg-web-example-builder': entry}})

    def test_rejects_wrong_document_shape_or_role_name(self):
        for document in [
            [], {}, {'profiles': {}}, {'agents': []},
            {'agents': {}, 'version': 1},
            {'agents': {'upstream-role': profile()}},
            {'agents': {'fg-web-Invalid': profile()}},
        ]:
            with self.subTest(document=document), self.assertRaises(ValueError):
                parse_profiles(document)

    def test_profile_loader_rejects_duplicate_toml_roles(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / 'profiles.toml'
            path.write_text(
                '[agents."fg-web-example-builder"]\ncategory="sol"\neffort="high"\n'
                '[agents."fg-web-example-builder"]\ncategory="sol"\neffort="high"\n',
                encoding='utf-8',
            )
            with self.assertRaises(ValueError):
                load_profiles(path)

    def test_coverage_is_dynamic_and_excludes_unrelated_vendor_roles(self):
        for size in [0, 1, 4]:
            names = {f'fg-api-example-{index}-builder' for index in range(size)}
            with self.subTest(size=size):
                validate_profile_coverage(
                    {name: profile() for name in names}, names | {'upstream-worker'},
                )

    def test_coverage_reports_missing_and_orphaned_profiles(self):
        with self.assertRaisesRegex(ValueError, 'missing profiles: fg-api-one-builder'):
            validate_profile_coverage({}, {'fg-api-one-builder'})
        with self.assertRaisesRegex(ValueError, 'orphaned profiles: fg-web-one-builder'):
            validate_profile_coverage({'fg-web-one-builder': profile()}, set())

    def test_native_model_effort_and_approval_overrides_are_forbidden(self):
        for key in ['model', 'model_reasoning_effort', 'approval_policy']:
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, key):
                validate_native_agent(
                    {'name': 'fg-web-example-builder', key: 'override'}, 'role.toml',
                )

    def test_malformed_fireguard_names_cannot_bypass_profile_policy(self):
        with self.assertRaisesRegex(ValueError, 'Invalid FireGuard role'):
            validate_native_agent({'name': 'fg-web-Invalid', 'model': 'override'}, 'role.toml')

    def test_read_only_roles_require_read_only_sandbox(self):
        for suffix in ['reviewer', 'auditor', 'explorer']:
            role = {'name': f'fg-api-example-{suffix}', 'sandbox_mode': 'read-only'}
            with self.subTest(suffix=suffix):
                validate_native_agent(role, 'role.toml')
                for mode in [None, 'workspace-write', 'danger-full-access']:
                    invalid = {'name': role['name']}
                    if mode is not None:
                        invalid['sandbox_mode'] = mode
                    with self.subTest(mode=mode), self.assertRaisesRegex(ValueError, 'Read-only'):
                        validate_native_agent(invalid, 'role.toml')

    def test_writers_inherit_sandbox(self):
        validate_native_agent({'name': 'fg-web-example-builder'}, 'role.toml')
        for mode in ['read-only', 'workspace-write', 'danger-full-access']:
            with self.subTest(mode=mode), self.assertRaisesRegex(ValueError, 'inherit'):
                validate_native_agent(
                    {'name': 'fg-web-example-builder', 'sandbox_mode': mode}, 'role.toml',
                )

    def test_global_policy_rejects_every_protected_key(self):
        for key in GLOBAL_POLICY_KEYS:
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, key):
                validate_global_policy({key: 'override'})
        validate_global_policy({'mcp_servers': {'example': {'command': 'existing'}}})

    def test_global_subagent_defaults_are_forbidden_but_concurrency_is_preserved(self):
        for key in ['default_subagent_model', 'default_subagent_reasoning_effort']:
            with self.subTest(key=key), self.assertRaisesRegex(ValueError, key):
                validate_global_policy({'agents': {key: 'override', 'max_threads': 4}})
        validate_global_policy({'agents': {'max_threads': 4, 'max_depth': 1}})

    def test_native_references_include_markdown_resources(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            paths = [
                '.agents/skills/fg-web-example/SKILL.md',
                '.agents/skills/fg-web-example/references/details.md',
                '.codex/references/naming.md',
            ]
            for relative in paths:
                path = root / relative
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text('Reference', encoding='utf-8')
            instructions = 'Read ' + ', '.join(f'\u0060{path}\u0060' for path in paths) + '.'
            validate_native_references(instructions, root)
            (root / paths[1]).unlink()
            with self.assertRaisesRegex(ValueError, 'references/details.md'):
                validate_native_references(instructions, root)


class ValidatorIntegrationTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.root = Path(self.directory.name)
        for relative in ['.agents/skills', '.codex/agents']:
            (self.root / relative).mkdir(parents=True)
        self.write('.codex/config.toml', '')
        self.write('.codex/rules.md', '# Rules\n')
        self.write('.codex/agent-profiles.toml', '[agents]\n')
        self.write('.codex/hooks.json', json.dumps({
            'hooks': {'PreToolUse': [], 'PostToolUse': []},
        }))
        self.write('.agents/skills.lock.json', json.dumps({
            'schema_version': 2,
            'hashing': {
                'algorithm': 'sha256', 'text_normalization': 'utf8-lf',
                'text_extensions': ['.md'],
            },
            'packages': [],
        }))

    def write(self, relative, content):
        (self.root / relative).write_text(content, encoding='utf-8')

    def add_role(self, name, filename=None, instructions='Bounded implementation.'):
        self.write(
            '.codex/agents/' + (filename or name) + '.toml',
            f'name = {json.dumps(name)}\ndescription = "Example"\n'
            f'developer_instructions = {json.dumps(instructions)}\n',
        )

    def add_profiles(self, names):
        content = '[agents]\n' + ''.join(
            f'\n[agents."{name}"]\ncategory = "sol"\neffort = "high"\n'
            for name in names
        )
        self.write('.codex/agent-profiles.toml', content)

    def test_validates_catalogs_without_fixed_agent_counts(self):
        self.assertEqual(validate(self.root)['agents'], 0)
        names = []
        for index in range(3):
            name = f'fg-api-fixture-{index}-builder'
            names.append(name)
            self.add_role(name)
            self.add_profiles(names)
            self.assertEqual(validate(self.root)['agents'], len(names))

    def test_validator_rejects_missing_and_orphaned_profiles(self):
        self.add_role('fg-api-fixture-builder')
        with self.assertRaisesRegex(ValueError, 'missing profiles'):
            validate(self.root)
        self.add_profiles(['fg-web-orphan-builder'])
        with self.assertRaisesRegex(ValueError, 'orphaned profiles'):
            validate(self.root)

    def test_validator_preserves_duplicate_name_detection(self):
        self.add_role('fg-api-fixture-builder', 'first')
        self.add_role('fg-api-fixture-builder', 'second')
        self.add_profiles(['fg-api-fixture-builder'])
        with self.assertRaisesRegex(AssertionError, 'Duplicate agent'):
            validate(self.root)

    def test_validator_rejects_missing_native_reference(self):
        self.add_role(
            'fg-api-fixture-builder',
            instructions='Read .agents/skills/fg-api-fixture/references/details.md.',
        )
        self.add_profiles(['fg-api-fixture-builder'])
        with self.assertRaisesRegex(ValueError, 'agent reference'):
            validate(self.root)

    def test_validator_rejects_native_effort(self):
        name = 'fg-api-fixture-builder'
        self.add_role(name)
        self.add_profiles([name])
        path = self.root / f'.codex/agents/{name}.toml'
        path.write_text(path.read_text(encoding='utf-8') + 'model_reasoning_effort="high"\n',
                        encoding='utf-8')
        with self.assertRaisesRegex(ValueError, 'model_reasoning_effort'):
            validate(self.root)

    def test_validator_rejects_global_effort(self):
        self.write('.codex/config.toml', 'model_reasoning_effort="high"\n')
        with self.assertRaisesRegex(ValueError, 'model_reasoning_effort'):
            validate(self.root)

    def test_validator_checks_authored_document_links(self):
        self.write('.codex/workflow.md', '[Missing](references/missing.md)\n')
        with self.assertRaisesRegex(ValueError, 'Broken local Markdown links'):
            validate(self.root)


if __name__ == '__main__':
    unittest.main()
