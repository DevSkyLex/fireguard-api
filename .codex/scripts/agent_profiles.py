"""Read and validate FireGuard's non-native delegation profiles (Python 3.11+)."""
from pathlib import Path
import re
import tomllib

CATEGORIES = frozenset({'astra', 'sol', 'terra', 'luna'})
EFFORTS = frozenset({'medium', 'high', 'xhigh'})
GLOBAL_POLICY_KEYS = frozenset({
    'model', 'model_reasoning_effort', 'approval_policy', 'sandbox_mode', 'projects',
})
GLOBAL_AGENT_MODEL_KEYS = frozenset({
    'default_subagent_model', 'default_subagent_reasoning_effort',
})
NATIVE_OVERRIDE_KEYS = frozenset({'model', 'model_reasoning_effort', 'approval_policy'})
FIREGUARD_NAME = re.compile(r'fg-(?:api|web)-[a-z0-9]+(?:-[a-z0-9]+)*')
READ_ONLY_SUFFIXES = ('-reviewer', '-auditor', '-explorer')


def is_fireguard_agent(name: str) -> bool:
    """Identify the authored role namespace without depending on catalog counts."""
    return name.startswith(('fg-api-', 'fg-web-'))


def validate_profile(profile: object, name: str) -> dict[str, str]:
    """Require one logical category and one exact effort, never a versioned model."""
    if not isinstance(profile, dict) or set(profile) != {'category', 'effort'}:
        raise ValueError(f'Invalid profile fields for {name}: expected category and effort')
    category, effort = profile['category'], profile['effort']
    if not isinstance(category, str) or category not in CATEGORIES:
        raise ValueError(f'Invalid category for {name}: expected astra, sol, terra or luna')
    if not isinstance(effort, str) or effort not in EFFORTS:
        raise ValueError(f'Invalid effort for {name}: expected medium, high or xhigh')
    return {'category': category, 'effort': effort}


def parse_profiles(document: object) -> dict[str, dict[str, str]]:
    """Validate the complete TOML document without making runtime model assumptions."""
    if not isinstance(document, dict) or set(document) != {'agents'}:
        raise ValueError('Profiles must contain only the agents table')
    entries = document['agents']
    if not isinstance(entries, dict):
        raise ValueError('Profiles agents must be a table')
    profiles = {}
    for name, profile in entries.items():
        if not isinstance(name, str) or not FIREGUARD_NAME.fullmatch(name):
            raise ValueError(f'Invalid FireGuard profile name: {name}')
        profiles[name] = validate_profile(profile, name)
    return profiles


def load_profiles(path: Path) -> dict[str, dict[str, str]]:
    """Read only the explicitly selected profile document."""
    return parse_profiles(tomllib.loads(path.read_text(encoding='utf-8')))


def validate_profile_coverage(profiles: dict[str, dict[str, str]], names: set[str]) -> None:
    """Require a bijection with discovered FireGuard roles, not a fixed role count."""
    roles = {name for name in names if is_fireguard_agent(name)}
    missing, orphaned = roles - profiles.keys(), profiles.keys() - roles
    if missing or orphaned:
        details = []
        if missing:
            details.append('missing profiles: ' + ', '.join(sorted(missing)))
        if orphaned:
            details.append('orphaned profiles: ' + ', '.join(sorted(orphaned)))
        raise ValueError('Agent/profile mismatch: ' + '; '.join(details))


def validate_native_agent(agent: dict, source: str) -> None:
    """Keep FireGuard model selection at dispatch and preserve role permissions."""
    name = agent['name']
    if not is_fireguard_agent(name):
        return
    if not FIREGUARD_NAME.fullmatch(name):
        raise ValueError(f'Invalid FireGuard role name in {source}: {name}')
    overrides = NATIVE_OVERRIDE_KEYS & agent.keys()
    if overrides:
        raise ValueError(f'Native policy override in {source}: {", ".join(sorted(overrides))}')
    if name.endswith(READ_ONLY_SUFFIXES):
        if agent.get('sandbox_mode') != 'read-only':
            raise ValueError(f'Read-only role requires sandbox_mode = "read-only": {source}')
    elif 'sandbox_mode' in agent:
        raise ValueError(f'Writer role must inherit its sandbox: {source}')


def validate_global_policy(config: dict) -> None:
    """Do not move role preferences into the user's global project policy."""
    overrides = GLOBAL_POLICY_KEYS & config.keys()
    if overrides:
        raise ValueError('Project config overrides user policy: ' + ', '.join(sorted(overrides)))
    agents = config.get('agents', {})
    if not isinstance(agents, dict):
        raise ValueError('Project agents configuration must be a table')
    defaults = GLOBAL_AGENT_MODEL_KEYS & agents.keys()
    if defaults:
        raise ValueError('Project config overrides delegation profiles: ' + ', '.join(
            f'agents.{key}' for key in sorted(defaults)
        ))


def validate_native_references(instructions: str, root: Path) -> None:
    """Check explicit procedure and reference paths without opening their contents."""
    pattern = r'(?<![\w./])((?:\.agents/skills|\.codex)/(?:[\w.-]+/)*[\w.-]+\.md)\b'
    for relative in re.findall(pattern, instructions):
        target = (root / relative).resolve()
        if not target.is_relative_to(root.resolve()) or not target.is_file():
            raise ValueError(f'Missing or out-of-checkout agent reference: {relative}')
