"""Validate native FireGuard agent settings without changing session policy."""
from pathlib import Path, PurePosixPath, PureWindowsPath
import re
import tomllib
import importlib.util

MODEL_EFFORTS = {
    'gpt-6-luna': frozenset({'medium', 'high'}),
    'gpt-6-sol': frozenset({'medium', 'high', 'xhigh'}),
    'gpt-6.1-sol': frozenset({'medium', 'high', 'xhigh'}),
    'gpt-6-astra': frozenset({'high', 'xhigh'}),
}
GLOBAL_POLICY_KEYS = frozenset({
    'model', 'model_reasoning_effort', 'approval_policy', 'sandbox_mode', 'projects', 'service_tier',
})
GLOBAL_AGENT_MODEL_KEYS = frozenset({'default_subagent_model', 'default_subagent_reasoning_effort'})
FIREGUARD_NAME = re.compile(r'fg-(?:api|web)-[a-z0-9]+(?:-[a-z0-9]+)*')
READ_ONLY_SUFFIXES = ('-reviewer', '-auditor', '-explorer')
NATIVE_KEYS = frozenset({
    'name', 'description', 'developer_instructions', 'model', 'model_reasoning_effort',
    'service_tier', 'features', 'sandbox_mode',
})


def is_fireguard_agent(name: str) -> bool:
    """Select authored roles without asserting a fixed catalog count."""
    return name.startswith(('fg-api-', 'fg-web-'))


def validate_native_agent(agent: dict, source: str) -> None:
    """Require explicit settings and retain the parent's permission boundaries."""
    name = agent['name']
    if not is_fireguard_agent(name):
        return
    if not FIREGUARD_NAME.fullmatch(name):
        raise ValueError(f'Invalid FireGuard role name in {source}: {name}')
    extra = agent.keys() - NATIVE_KEYS
    if extra:
        raise ValueError(f'Unsupported native policy in {source}: {", ".join(sorted(extra))}')
    model = agent.get('model')
    if not isinstance(model, str) or model not in MODEL_EFFORTS:
        raise ValueError(f'Missing or unsupported model in {source}')
    effort = agent.get('model_reasoning_effort')
    if not isinstance(effort, str) or effort not in MODEL_EFFORTS[model]:
        raise ValueError(f'Missing or unsupported model_reasoning_effort in {source}')
    fast = model == 'gpt-6-luna'
    if agent.get('service_tier') != ('fast' if fast else 'default'):
        raise ValueError(f'Invalid service_tier in {source}: Fast is required for Luna only')
    features = agent.get('features')
    if not isinstance(features, dict) or set(features) != {'fast_mode'} or features['fast_mode'] is not fast:
        raise ValueError(f'Invalid features.fast_mode in {source}: must be explicit and match the model')
    if name.endswith(READ_ONLY_SUFFIXES):
        if agent.get('sandbox_mode') != 'read-only':
            raise ValueError(f'Read-only role requires sandbox_mode = "read-only": {source}')
    elif 'sandbox_mode' in agent:
        raise ValueError(f'Writer role must inherit its sandbox: {source}')


def validate_global_policy(config: dict) -> None:
    """Keep role preferences out of the primary session and personal policy."""
    extra = GLOBAL_POLICY_KEYS & config.keys()
    if extra:
        raise ValueError('Project config overrides user policy: ' + ', '.join(sorted(extra)))
    if 'fast_mode' in config.get('features', {}):
        raise ValueError('Project config overrides features.fast_mode: set it in each native role')
    agents = config.get('agents', {})
    if not isinstance(agents, dict):
        raise ValueError('Project agents configuration must be a table')
    defaults = GLOBAL_AGENT_MODEL_KEYS & agents.keys()
    if defaults:
        raise ValueError('Project config overrides agent defaults: ' + ', '.join(sorted(defaults)))


def validate_native_references(instructions: str, root: Path) -> None:
    """Check explicit resources against the owning checkout without opening secrets."""
    pattern = r'(?<![\w./])((?:\.agents/skills|\.codex)/(?:[\w.-]+/)*[\w.-]+\.[A-Za-z0-9]+)\b'
    spec = importlib.util.spec_from_file_location('fireguard_active_references', Path(__file__).with_name('check_references.py'))
    references = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(references)
    for number, line in references.active_lines(instructions):
        line = re.sub(r'`[^`]*[<>*][^`]*`', '', line)
        for relative in re.findall(pattern, line):
            if relative == '.codex/config.toml':
                continue
            target = (root / relative).resolve()
            if not target.is_relative_to(root.resolve()) or not target.is_file():
                raise ValueError(f'Missing or out-of-checkout agent reference at line {number}: {relative}')


def validate_role_registry(config: dict, config_path: Path, *, local_only: bool = False) -> int:
    """Check declaration structure locally; retain peer existence/coverage by default."""
    root = config_path.resolve().parent.parent
    registry = {name: value for name, value in config.get('agents', {}).items() if is_fireguard_agent(name)}
    own = {
        tomllib.loads(path.read_text(encoding='utf-8'))['name']
        for path in (root / '.codex/agents').glob('fg-*.toml')
    }
    prefixes = {name.split('-')[1] for name in own}
    if len(prefixes) > 1:
        raise ValueError('Native role definitions must belong to one checkout')
    for name, declaration in registry.items():
        if (not FIREGUARD_NAME.fullmatch(name) or not isinstance(declaration, dict)
                or set(declaration) != {'config_file', 'description'}):
            raise ValueError(f'Invalid peer role declaration: {name}')
        if name in own:
            raise ValueError(f'Duplicate native role registration: {name}')
        if prefixes and name.split('-')[1] in prefixes:
            raise ValueError(f'Peer role declaration belongs to the local owner: {name}')
        if not isinstance(declaration['description'], str) or not declaration['description'].strip():
            raise ValueError(f'Invalid peer role description: {name}')
        relative = declaration['config_file']
        repo = name.split('-')[1]
        expected = f'../../fireguard-{repo}/.codex/agents/{name}.toml'
        if (not isinstance(relative, str) or PurePosixPath(relative).is_absolute()
                or PureWindowsPath(relative).is_absolute() or relative != expected):
            raise ValueError(f'Invalid peer config_file for {name}: expected {expected}')
        if local_only:
            continue
        owner = (root.parent / ('fireguard-' + repo)).resolve()
        target = (config_path.parent / relative).resolve()
        if not target.is_relative_to(owner / '.codex/agents') or not target.is_file():
            raise ValueError(f'Missing or out-of-checkout peer agent: {name}')
        agent = tomllib.loads(target.read_text(encoding='utf-8'))
        if agent.get('name') != name or declaration['description'] != agent.get('description'):
            raise ValueError(f'Peer role name/description mismatch: {name}')
        validate_native_agent(agent, str(target))
        try:
            validate_native_references(agent['developer_instructions'], owner)
        except ValueError as error:
            raise ValueError(f'{target}: {error}') from error
    if prefixes and not local_only:
        peer = 'web' if next(iter(prefixes)) == 'api' else 'api'
        folder = root.parent / ('fireguard-' + peer) / '.codex/agents'
        if folder.is_dir():
            expected_names = {
                tomllib.loads(path.read_text(encoding='utf-8'))['name']
                for path in folder.glob('fg-*.toml')
            }
            if registry.keys() != expected_names:
                raise ValueError('Peer role coverage mismatch: missing ' + ', '.join(sorted(expected_names - registry.keys()))
                                 + '; orphaned ' + ', '.join(sorted(registry.keys() - expected_names)))
    return len(registry)
