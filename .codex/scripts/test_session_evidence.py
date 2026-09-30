"""Unknown/partial discovery and configured settings must never become runtime proof."""
from copy import deepcopy
import unittest

from session_evidence import diagnose


AGENTS = {'fg-api-example-builder': {'model': 'gpt-6-sol', 'reasoning_effort': 'high',
                                    'service_tier': 'default', 'fast_mode': False}}
SKILLS = {'fg-api-review', 'fg-api-tooling-diagnose'}


def evidence():
    return {'roles': {'source': 'active custom-role catalog', 'complete': True, 'names': list(AGENTS)},
            'skills': {'source': 'active skill catalog', 'complete': True, 'names': sorted(SKILLS)},
            'models': {'source': 'active model/list responses', 'includeHidden': True, 'pages': [
                {'cursor': None, 'data': [{'model': 'gpt-6-luna', 'supportedReasoningEfforts': [{'reasoningEffort': 'medium'}]}], 'nextCursor': 'page-2'},
                {'cursor': 'page-2', 'data': [{'model': 'gpt-6-sol', 'supportedReasoningEfforts': [{'reasoningEffort': 'high'}]}], 'nextCursor': None}]},
            'execution': {'source': 'client request metadata', 'kind': 'request_metadata', 'roles': deepcopy(AGENTS)}}


class SessionEvidenceTests(unittest.TestCase):
    def test_complete_sourced_session_observations_are_distinct_and_verified(self):
        result = diagnose(AGENTS, SKILLS, evidence())
        self.assertEqual(result['roles']['status'], 'verified')
        self.assertEqual(result['skills']['status'], 'verified')
        self.assertEqual(result['availability'][0]['status'], 'verified')
        self.assertEqual(result['execution'][0]['status'], 'verified')

    def test_session_without_runtime_metadata_preserves_unknowns(self):
        result = diagnose(AGENTS, SKILLS, {})
        for item in [result['roles'], result['skills'], *result['availability'], *result['execution']]:
            self.assertEqual(item['status'], 'non observable')

    def test_partial_missing_middle_repeated_and_malformed_pages_are_not_proof(self):
        full = evidence()['models']['pages']
        cases = [full[:1], full[1:], [full[0], {**full[1], 'cursor': 'skipped'}],
                 [full[0], {**full[1], 'nextCursor': 'page-2'}],
                 [{**full[0], 'nextCursor': None}, full[1]],
                 [{'cursor': None, 'data': [{'model': 'gpt-6-sol'}], 'nextCursor': None}]]
        for pages in cases:
            value = evidence()
            value['models']['pages'] = pages
            with self.subTest(pages=pages):
                self.assertEqual(diagnose(AGENTS, SKILLS, value)['availability'][0]['status'], 'non observable')

    def test_missing_model_or_effort_requires_complete_catalog(self):
        value = evidence()
        value['models']['pages'][1]['data'][0]['supportedReasoningEfforts'] = [{'reasoningEffort': 'medium'}]
        self.assertEqual(diagnose(AGENTS, SKILLS, value)['availability'][0]['status'], 'mismatch')
        value['models']['pages'][1]['data'] = []
        self.assertEqual(diagnose(AGENTS, SKILLS, value)['availability'][0]['status'], 'mismatch')

    def test_picker_only_or_unknown_model_scope_cannot_prove_absence(self):
        for hidden in [False, None]:
            value = evidence()
            value['models']['includeHidden'] = hidden
            self.assertEqual(diagnose(AGENTS, SKILLS, value)['availability'][0]['status'], 'verified')
            value['models']['pages'][1]['data'] = []
            self.assertEqual(diagnose(AGENTS, SKILLS, value)['availability'][0]['status'], 'non observable')

    def test_partial_role_or_skill_catalog_cannot_prove_absence(self):
        for section in ['roles', 'skills']:
            value = evidence()
            value[section]['names'] = []
            value[section]['complete'] = False
            self.assertEqual(diagnose(AGENTS, SKILLS, value)[section]['status'], 'non observable')
            value[section]['complete'] = True
            self.assertEqual(diagnose(AGENTS, SKILLS, value)[section]['status'], 'mismatch')

    def test_configuration_agent_assertions_and_missing_sources_are_not_execution(self):
        for kind in ['declared_configuration', 'agent_statement', None]:
            value = evidence()
            value['execution']['kind'] = kind
            self.assertEqual(diagnose(AGENTS, SKILLS, value)['execution'][0]['status'], 'non observable')
        for section in ['roles', 'skills', 'models', 'execution']:
            value = evidence()
            value[section].pop('source')
            result = diagnose(AGENTS, SKILLS, value)
            item = result[section][0] if section == 'execution' else result['availability'][0] if section == 'models' else result[section]
            self.assertEqual(item['status'], 'non observable')

    def test_applied_mismatch_and_partial_settings_do_not_get_defaults(self):
        value = evidence()
        observed = value['execution']['roles']['fg-api-example-builder']
        observed['fast_mode'] = True
        self.assertEqual(diagnose(AGENTS, SKILLS, value)['execution'][0]['status'], 'mismatch')
        observed['fast_mode'] = 0
        self.assertEqual(diagnose(AGENTS, SKILLS, value)['execution'][0]['status'], 'mismatch')
        observed.pop('service_tier')
        self.assertEqual(diagnose(AGENTS, SKILLS, value)['execution'][0]['status'], 'non observable')
