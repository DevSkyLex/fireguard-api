"""Exercise model resolution on supplied fixtures without invoking any model."""
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path
import io
import json
import os
import tempfile
import unittest
from unittest.mock import patch

import resolve_agent
from resolve_agent import normalized_version, parse_catalog, resolve_model


def model(name, *, hidden=False, efforts=None):
    return {
        'model': name, 'hidden': hidden,
        'supported_reasoning_efforts': ['high'] if efforts is None else efforts,
    }


def catalog(*models):
    return {'models': list(models)}


class ResolverTests(unittest.TestCase):
    profile = {'category': 'sol', 'effort': 'high'}

    def test_compares_numeric_version_components_not_strings_or_floats(self):
        result = resolve_model(self.profile, catalog(
            model('gpt-5.9-sol'), model('gpt-5.10-sol'), model('gpt-5.8-sol'),
        ))
        self.assertEqual(result, {'model': 'gpt-5.10-sol', 'reasoning_effort': 'high'})
        self.assertEqual(normalized_version('5.10.0'), (5, 10))

    def test_selects_newest_generation_independent_of_catalog_order(self):
        for entries in [
            [model('gpt-6-sol'), model('gpt-5.999-sol')],
            [model('gpt-5.999-sol'), model('gpt-6-sol')],
        ]:
            with self.subTest(entries=entries):
                self.assertEqual(
                    resolve_model(self.profile, catalog(*entries))['model'], 'gpt-6-sol',
                )

    def test_excludes_other_categories_hidden_and_incompatible_efforts(self):
        result = resolve_model(self.profile, catalog(
            model('gpt-100-astra'), model('gpt-99-sol', hidden=True),
            model('gpt-98-sol', efforts=['medium', 'xhigh']), model('gpt-6-sol'),
        ))
        self.assertEqual(result['model'], 'gpt-6-sol')

    def test_returns_the_requested_effort_unchanged_for_every_category(self):
        for category in ['astra', 'sol', 'terra', 'luna']:
            for effort in ['medium', 'high', 'xhigh']:
                with self.subTest(category=category, effort=effort):
                    name = f'gpt-6-{category}'
                    result = resolve_model(
                        {'category': category, 'effort': effort},
                        catalog(model(name, efforts=['low', 'medium', 'high', 'xhigh', 'max'])),
                    )
                    self.assertEqual(result, {'model': name, 'reasoning_effort': effort})

    def test_excludes_snapshots_and_prereleases(self):
        for name in [
            'gpt-7-sol-2026-09-22', 'gpt-7-sol-20260922', 'gpt-7-sol-preview',
            'gpt-7-sol-preview-2026-09-22', 'gpt-7-preview-sol',
            'gpt-7-sol-alpha', 'gpt-7-sol-beta.1', 'gpt-7-sol-rc1',
            'gpt-7-sol-snapshot', 'gpt-7-sol-experimental',
        ]:
            with self.subTest(name=name):
                result = resolve_model(self.profile, catalog(model(name), model('gpt-6-sol')))
                self.assertEqual(result['model'], 'gpt-6-sol')

    def test_rejects_unknown_relevant_naming_without_guessing(self):
        for name in [
            'gpt-next-sol', 'sol-latest', 'gpt-7-sol-latest', 'gpt-07-sol',
            'provider/gpt-7-sol', 'gpt-7-SOL', 'gpt-7-sol-fast',
            'gpt-next_sol', 'gpt-next_sol_latest', 'gpt-7_sol',
        ]:
            with self.subTest(name=name), self.assertRaisesRegex(ValueError, 'Unknown model naming'):
                resolve_model(self.profile, catalog(model('gpt-6-sol'), model(name)))

    def test_unrelated_or_ineligible_unknown_naming_does_not_block(self):
        result = resolve_model(self.profile, catalog(
            model('another-provider-model'), model('gpt-next-astra'), model('gpt-7-solaris'),
            model('gpt-next-sol', hidden=True),
            model('gpt-future-sol', efforts=['medium']), model('gpt-6-sol'),
        ))
        self.assertEqual(result['model'], 'gpt-6-sol')

    def test_rejects_absent_compatible_candidate_without_fallback(self):
        for entries in [
            [], [model('gpt-6-astra')], [model('gpt-6-sol', hidden=True)],
            [model('gpt-6-sol', efforts=['medium'])], [model('gpt-6-sol', efforts=[])],
            [model('gpt-6-sol-preview')],
        ]:
            with self.subTest(entries=entries), self.assertRaisesRegex(ValueError, 'No visible'):
                resolve_model(self.profile, catalog(*entries))

    def test_rejects_equal_latest_versions_with_different_identifiers(self):
        with self.assertRaisesRegex(ValueError, 'Ambiguous latest'):
            resolve_model(self.profile, catalog(model('gpt-6-sol'), model('gpt-6.0-sol')))
        result = resolve_model(self.profile, catalog(
            model('gpt-5-sol'), model('gpt-5.0-sol'), model('gpt-6-sol'),
        ))
        self.assertEqual(result['model'], 'gpt-6-sol')

    def test_deduplicates_equivalent_records_but_rejects_contradictions(self):
        first = model('gpt-6-sol', efforts=['medium', 'high'])
        second = model('gpt-6-sol', efforts=['high', 'medium'])
        self.assertEqual(len(parse_catalog(catalog(first, second))), 1)
        for conflicting in [
            model('gpt-6-sol', hidden=True, efforts=['medium', 'high']),
            model('gpt-6-sol', efforts=['medium']),
        ]:
            with self.subTest(conflicting=conflicting), self.assertRaisesRegex(
                ValueError, 'Contradictory duplicate',
            ):
                resolve_model(self.profile, catalog(first, conflicting))

    def test_rejects_incomplete_or_malformed_normalized_catalogs(self):
        bad = [
            None, [], {}, {'models': {}}, {'models': [], 'extra': True},
            catalog(None), catalog('gpt-6-sol'),
            catalog({'model': 'gpt-6-sol'}),
            catalog({**model('gpt-6-sol'), 'id': 'alternate-id'}),
            catalog(model('')), catalog(model('gpt-6-sol ')),
            catalog(model('gpt-6-sol', hidden='false')),
            catalog(model('gpt-6-sol', hidden=0)),
            catalog(model('gpt-6-sol', efforts='high')),
            catalog(model('gpt-6-sol', efforts=[{'reasoningEffort': 'high'}])),
            catalog(model('gpt-6-sol', efforts=[None])),
            catalog(model('gpt-6-sol', efforts=[' high'])),
        ]
        for document in bad:
            with self.subTest(document=document), self.assertRaises(ValueError):
                resolve_model(self.profile, document)

    def test_rejects_bad_profiles_before_resolution(self):
        for profile in [
            {}, {'category': 'sol', 'effort': 'low'},
            {'category': 'unknown', 'effort': 'high'},
        ]:
            with self.subTest(profile=profile), self.assertRaises(ValueError):
                resolve_model(profile, catalog(model('gpt-6-sol')))


class ResolverCliTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory()
        self.addCleanup(self.directory.cleanup)
        self.path = Path(self.directory.name) / 'agent-profiles.toml'
        self.path.write_text(
            '[agents."fg-web-example-builder"]\ncategory="sol"\neffort="high"\n',
            encoding='utf-8',
        )

    def invoke(self, payload, name='fg-web-example-builder'):
        output, errors = io.StringIO(), io.StringIO()
        with patch.object(resolve_agent, 'PROFILES_PATH', self.path), \
                patch('sys.stdin', io.StringIO(payload)), \
                redirect_stdout(output), redirect_stderr(errors):
            status = resolve_agent.main(['--agent', name])
        return status, output.getvalue(), errors.getvalue()

    def test_cli_returns_only_result_json_and_does_not_modify_profiles(self):
        before = self.path.read_bytes()
        status, output, errors = self.invoke(json.dumps(catalog(model('gpt-6-sol'))))
        self.assertEqual(status, 0)
        self.assertEqual(json.loads(output), {
            'model': 'gpt-6-sol', 'reasoning_effort': 'high',
        })
        self.assertEqual(errors, '')
        self.assertEqual(self.path.read_bytes(), before)
        self.assertEqual(list(self.path.parent.iterdir()), [self.path])

    def test_cli_keeps_stdout_empty_on_resolution_or_input_failure(self):
        for payload in [
            '', '{', '{}', '{"models":[]}',
            '{"models":[],"models":[]}',
            '{"models":[{"model":"gpt-6-sol","hidden":true,"hidden":false,'
            '"supported_reasoning_efforts":["high"]}]}',
            json.dumps(catalog(model('gpt-6-sol'), model('gpt-6.0-sol'))),
            json.dumps(catalog(model('gpt-next-sol'))),
        ]:
            with self.subTest(payload=payload):
                status, output, errors = self.invoke(payload)
                self.assertEqual(status, 2)
                self.assertEqual(output, '')
                self.assertIn('resolve_agent:', errors)

    def test_cli_rejects_unknown_role_or_missing_invalid_profile_file(self):
        payload = json.dumps(catalog(model('gpt-6-sol')))
        status, output, errors = self.invoke(payload, 'fg-web-absent-builder')
        self.assertEqual((status, output), (2, ''))
        self.assertIn('Unknown agent profile', errors)
        self.path.write_text('invalid TOML', encoding='utf-8')
        self.assertEqual(self.invoke(payload)[:2], (2, ''))
        self.path.unlink()
        self.assertEqual(self.invoke(payload)[:2], (2, ''))

    def test_profile_location_is_independent_of_working_directory(self):
        original = Path.cwd()
        try:
            os.chdir(self.path.parent)
            status, _, errors = self.invoke(json.dumps(catalog(model('gpt-6-sol'))))
            self.assertEqual(status, 0, errors)
        finally:
            os.chdir(original)
        self.assertEqual(
            resolve_agent.PROFILES_PATH,
            Path(resolve_agent.__file__).resolve().parents[1] / 'agent-profiles.toml',
        )


if __name__ == '__main__':
    unittest.main()
