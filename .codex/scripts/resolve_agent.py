"""Resolve one FireGuard profile against a supplied catalog; never contact a model."""
import sys

# Direct CLI invocation must not create a local __pycache__ while importing helpers.
sys.dont_write_bytecode = True

import argparse
import json
from pathlib import Path
import re

from agent_profiles import load_profiles, validate_profile

PROFILES_PATH = Path(__file__).resolve().parents[1] / 'agent-profiles.toml'
VERSION = r'(?:0|[1-9][0-9]*)(?:\.(?:0|[1-9][0-9]*))*'
RELEASE = re.compile(rf'^gpt-({VERSION})-(astra|sol|terra|luna)$')
NON_RELEASE = re.compile(
    r'(?:^|-)(?:preview|alpha|beta|rc|snapshot|experimental)(?:$|[-.0-9])'
    r'|-[0-9]{4}-[0-9]{2}-[0-9]{2}$|-[0-9]{8}$',
    re.IGNORECASE,
)


def normalized_version(version: str) -> tuple[int, ...]:
    """Compare decimal components numerically, with 5.6 and 5.6.0 equivalent."""
    parts = [int(part) for part in version.split('.')]
    while len(parts) > 1 and parts[-1] == 0:
        parts.pop()
    return tuple(parts)


def unique_json_object(pairs: list[tuple[str, object]]) -> dict:
    """Reject duplicate JSON keys instead of silently taking the last value."""
    result = {}
    for key, value in pairs:
        if key in result:
            raise ValueError(f'Duplicate JSON field: {key}')
        result[key] = value
    return result


def parse_catalog(document: object) -> list[dict]:
    """Require the parent's normalized, complete runtime catalog representation."""
    if not isinstance(document, dict) or set(document) != {'models'}:
        raise ValueError('Catalog must contain only a models array')
    if not isinstance(document['models'], list):
        raise ValueError('Catalog models must be an array')
    models = {}
    for index, entry in enumerate(document['models']):
        fields = {'model', 'hidden', 'supported_reasoning_efforts'}
        if not isinstance(entry, dict) or set(entry) != fields:
            raise ValueError(f'Incomplete or unnormalized catalog entry at index {index}')
        name, hidden, efforts = (
            entry['model'], entry['hidden'], entry['supported_reasoning_efforts'],
        )
        if not isinstance(name, str) or not name or any(char.isspace() for char in name):
            raise ValueError(f'Invalid model identifier at index {index}')
        if not isinstance(hidden, bool):
            raise ValueError(f'Catalog hidden must be a boolean for {name}')
        if not isinstance(efforts, list) or any(
            not isinstance(effort, str) or not effort or effort.strip() != effort
            for effort in efforts
        ):
            raise ValueError(f'Invalid supported_reasoning_efforts for {name}')
        record = {
            'model': name,
            'hidden': hidden,
            'supported_reasoning_efforts': frozenset(efforts),
        }
        if name in models and record != models[name]:
            raise ValueError(f'Contradictory duplicate catalog entries for {name}')
        models[name] = record
    return list(models.values())


def resolve_model(profile: object, catalog: object) -> dict[str, str]:
    """Select the newest visible canonical release supporting the unchanged effort."""
    selected = validate_profile(profile, 'requested agent')
    category, effort = selected['category'], selected['effort']
    relevant_category = re.compile(rf'(?<![a-z0-9]){category}(?![a-z0-9])', re.IGNORECASE)
    candidates = []
    for entry in parse_catalog(catalog):
        if entry['hidden'] or effort not in entry['supported_reasoning_efforts']:
            continue
        name = entry['model']
        match = RELEASE.fullmatch(name)
        if match:
            if match.group(2) == category:
                candidates.append((normalized_version(match.group(1)), name))
            continue
        if relevant_category.search(name) and not NON_RELEASE.search(name):
            raise ValueError(f'Unknown model naming for category {category}: {name}')
    if not candidates:
        raise ValueError(f'No visible canonical {category} model supports effort {effort}')
    latest = max(version for version, _ in candidates)
    winners = sorted(name for version, name in candidates if version == latest)
    if len(winners) != 1:
        raise ValueError('Ambiguous latest compatible model: ' + ', '.join(winners))
    return {'model': winners[0], 'reasoning_effort': effort}


def main(argv: list[str] | None = None) -> int:
    """Read a local profile and stdin catalog; emit a result only after full validation."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--agent', required=True, help='Exact FireGuard role name')
    args = parser.parse_args(argv)
    try:
        profiles = load_profiles(PROFILES_PATH)
        if args.agent not in profiles:
            raise ValueError(f'Unknown agent profile: {args.agent}')
        catalog = json.load(sys.stdin, object_pairs_hook=unique_json_object)
        result = resolve_model(profiles[args.agent], catalog)
    except (OSError, ValueError) as error:
        print(f'resolve_agent: {error}', file=sys.stderr)
        return 2
    print(json.dumps(result), file=sys.stdout)
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
