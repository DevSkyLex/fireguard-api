"""Compare local file expectations with supplied, sanitized active-session evidence.

Optional JSON sections on stdin (omitting a section means non observable):
  roles/skills: {source: string, complete: bool, names: [string, ...]}
  models: {source: string, includeHidden: bool, pages: [{cursor: null|string, data: model/list data,
                                  nextCursor: null|string}, ...]}
  execution: {source: string, kind: request_metadata|execution_metadata,
              roles: {role_name: {model, reasoning_effort, service_tier, fast_mode}}}
Pages include the request cursor so an omitted intermediate page cannot look complete.
includeHidden records the query scope; picker-only/unknown scope cannot prove a model absent.
Sources must identify actual session/catalog/request observations, not file settings or
an agent's assertion. This evaluator does not collect evidence or verify its provenance.
It performs no RPC, launches no session/agent, and writes only its JSON report to stdout.
"""
from pathlib import Path
import json
import sys
import tomllib

from agent_config import validate_native_agent


PARAMETERS = ('model', 'reasoning_effort', 'service_tier', 'fast_mode')


def source(snapshot: object) -> str | None:
    if isinstance(snapshot, dict) and isinstance(snapshot.get('source'), str) and snapshot['source'].strip():
        return snapshot['source']
    return None


def discovery(expected: set[str], snapshot: object) -> dict:
    result = {'status': 'non observable', 'source': source(snapshot), 'missing': None}
    if (not result['source'] or not isinstance(snapshot, dict) or snapshot.get('complete') is not True
            or not isinstance(snapshot.get('names'), list) or not all(isinstance(name, str) for name in snapshot['names'])):
        return result
    missing = sorted(expected - set(snapshot['names']))
    return {**result, 'status': 'mismatch' if missing else 'verified', 'missing': missing}


def model_catalog(snapshot: object) -> dict[str, set[str]] | None:
    """Only a sourced, valid and uninterrupted cursor chain establishes availability."""
    if not source(snapshot) or not isinstance(snapshot, dict):
        return None
    pages = snapshot.get('pages')
    if not isinstance(pages, list) or not pages:
        return None
    cursor = None
    seen = set()
    models = {}
    for index, page in enumerate(pages):
        if (not isinstance(page, dict) or 'cursor' not in page or page['cursor'] != cursor
                or 'nextCursor' not in page or not isinstance(page.get('data'), list)):
            return None
        for model in page['data']:
            if (not isinstance(model, dict) or not isinstance(model.get('model'), str)
                    or not model['model'].strip() or model['model'] in models):
                return None
            efforts = model.get('supportedReasoningEfforts')
            if (not isinstance(efforts, list) or not all(isinstance(item, dict)
                    and isinstance(item.get('reasoningEffort'), str) and item['reasoningEffort'].strip() for item in efforts)):
                return None
            models[model['model']] = {item['reasoningEffort'] for item in efforts}
        cursor = page['nextCursor']
        if cursor is None:
            return models if index == len(pages) - 1 else None
        if not isinstance(cursor, str) or not cursor or cursor in seen:
            return None
        seen.add(cursor)
    return None


def diagnose(agents: dict[str, dict], skills: set[str], evidence: dict) -> dict:
    catalog = model_catalog(evidence.get('models'))
    execution = evidence.get('execution')
    trustworthy_kind = isinstance(execution, dict) and execution.get('kind') in {'request_metadata', 'execution_metadata'}
    observed_roles = execution.get('roles', {}) if trustworthy_kind else {}
    availability = []
    applied = []
    for name, expected in sorted(agents.items()):
        if catalog is None:
            available = None
        elif expected['model'] in catalog:
            available = expected['reasoning_effort'] in catalog[expected['model']]
        else:
            available = False if evidence['models'].get('includeHidden') is True else None
        availability.append({'role': name, 'model': expected['model'], 'effort': expected['reasoning_effort'],
                             'source': source(evidence.get('models')),
                             'status': 'non observable' if available is None else 'verified' if available else 'mismatch'})
        observed = observed_roles.get(name) if isinstance(observed_roles, dict) else None
        complete = source(execution) and isinstance(observed, dict) and all(key in observed for key in PARAMETERS)
        matched = complete and all(type(observed[key]) is type(expected[key]) and observed[key] == expected[key] for key in PARAMETERS)
        applied.append({'role': name, 'expected': expected, 'source': source(execution),
                        'observed': {key: observed[key] for key in PARAMETERS if key in observed} if isinstance(observed, dict) else None,
                        'status': 'non observable' if not complete else 'verified' if matched else 'mismatch'})
    return {'expectation_scope': 'local_files',
            'roles': discovery(set(agents), evidence.get('roles')),
            'skills': discovery(skills, evidence.get('skills')),
            'availability': availability, 'execution': applied}


def expectations(root: Path) -> tuple[dict[str, dict], set[str]]:
    agents = {}
    for path in sorted((root / '.codex/agents').glob('fg-*.toml')):
        data = tomllib.loads(path.read_text(encoding='utf-8'))
        validate_native_agent(data, path.name)
        agents[data['name']] = {'model': data['model'], 'reasoning_effort': data['model_reasoning_effort'],
                                'service_tier': data['service_tier'], 'fast_mode': data['features']['fast_mode']}
    skills = {path.name for path in (root / '.agents/skills').glob('fg-*') if (path / 'SKILL.md').is_file()}
    return agents, skills


if __name__ == '__main__':
    try:
        evidence = json.load(sys.stdin)
        if not isinstance(evidence, dict):
            raise ValueError('Expected an evidence object on stdin')
        print(json.dumps(diagnose(*expectations(Path(__file__).resolve().parents[2]), evidence), indent=2))
    except (OSError, ValueError, KeyError) as error:
        print(str(error), file=sys.stderr)
        raise SystemExit(1)
