"""Check independent native agent catalogs and shared-project role registration."""
from pathlib import Path
import argparse
import importlib.util
import json
import re
import sys
import tomllib

READ_ONLY_SUFFIXES = ('-reviewer', '-auditor', '-explorer')


def instruction_checker():
    spec = importlib.util.spec_from_file_location('fireguard_instruction_references',
                                                Path(__file__).resolve().parents[2] / '.codex/scripts/check_references.py')
    references = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(references)
    return references


def claude_fields(text: str) -> dict[str, str]:
    """Read the scalar frontmatter used by these authored agents, rejecting duplicate fields."""
    match = re.match(r'^---\r?\n(.*?)\r?\n---(?:\r?\n|$)', text, re.S)
    if not match:
        raise ValueError('Missing agent frontmatter')
    fields = {}
    for line in match[1].splitlines():
        field = re.match(r'^([A-Za-z][A-Za-z0-9_]*):\s*(.*)$', line)
        if field:
            key, value = field.groups()
            if key in fields:
                raise ValueError(f'Duplicate frontmatter field: {key}')
            fields[key] = value.strip().strip('\"\'')
    return fields


def validate_claude(fields: dict, text: str, root: Path) -> None:
    """Validate native fields, independent resources and read-only tool boundaries."""
    for key in ['name', 'description', 'tools', 'model', 'effort']:
        if not fields.get(key):
            raise ValueError(f'Missing Claude {key}')
    if not re.fullmatch(r'fg-(api|web)-[a-z0-9]+(?:-[a-z0-9]+)*', fields['name']):
        raise ValueError(f'Invalid Claude name: {fields["name"]}')
    efforts = {'sonnet': {'medium', 'high'}, 'opus': {'high', 'xhigh'}}
    if fields['model'] not in efforts or fields['effort'] not in efforts[fields['model']]:
        raise ValueError(f'Invalid Claude model/effort: {fields["name"]}')
    if {'fastMode', 'fast_mode', 'service_tier', 'model_reasoning_effort'} & fields.keys():
        raise ValueError('Fast/session fields do not belong in Claude agent frontmatter')
    if {'permissionMode', 'mcpServers', 'hooks', 'isolation'} & fields.keys():
        raise ValueError('Claude roles must inherit permission and execution policy')
    tools = {tool.strip() for tool in fields['tools'].split(',')}
    if fields['name'].endswith(READ_ONLY_SUFFIXES) and tools & {'Edit', 'Write', 'NotebookEdit', 'Agent', 'Task'}:
        raise ValueError(f'Read-only Claude role has writing/delegation tools: {fields["name"]}')
    if '.codex/agents' in text or re.search(r'(?m)^\s*(?:cd[^\n]*&&\s*)?codex\s+exec\b', text):
        raise ValueError(f'Claude role imports Codex agents or starts a nested challenge: {fields["name"]}')
    for number, line in instruction_checker().active_lines(text):
        line = re.sub(r'`[^`]*[<>*][^`]*`', '', line)
        for relative in re.findall(r'\.claude/(?:skills|rules)/(?:[\w.-]+/)*[\w.-]+\.md', line):
            if not (root / relative).is_file():
                raise ValueError(f'.claude/agents/{fields["name"]}.md:{number}: Missing Claude resource: {relative}')


def catalogs(root: Path) -> tuple[dict, dict]:
    """Read each vendor's own definitions; do not derive one prompt from another."""
    spec = importlib.util.spec_from_file_location('fireguard_native_policy', root / '.codex/scripts/agent_config.py')
    policy = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(policy)
    native, claude = {}, {}
    for path in (root / '.codex/agents').glob('fg-*.toml'):
        data = tomllib.loads(path.read_text(encoding='utf-8'))
        policy.validate_native_agent(data, path.name)
        if data['name'] != path.stem or data['name'] in native:
            raise ValueError(f'Duplicate/mismatched native filename: {path.name}')
        native[data['name']] = data
    for path in (root / '.claude/agents').glob('fg-*.md'):
        text = path.read_text(encoding='utf-8')
        fields = claude_fields(text)
        validate_claude(fields, text, root)
        if fields['name'] != path.stem or fields['name'] in claude:
            raise ValueError(f'Duplicate/mismatched Claude filename: {path.name}')
        claude[fields['name']] = fields
    if native.keys() != claude.keys():
        raise ValueError('Agent parity mismatch: missing Claude ' + ', '.join(sorted(native.keys() - claude.keys()))
                         + '; missing Codex ' + ', '.join(sorted(claude.keys() - native.keys())))
    settings = json.loads((root / '.claude/settings.json').read_text(encoding='utf-8'))
    if settings.get('fastMode') is not False or settings.get('env', {}).get('CLAUDE_CODE_DISABLE_FAST_MODE') != '1':
        raise ValueError('Claude Fast must be disabled in project settings and environment')
    return native, claude


def check(root: Path, *, local_only: bool = False) -> dict:
    """Verify local parity and the peer catalog from each primary configuration."""
    native, _ = catalogs(root)
    prefixes = {name.split('-')[1] for name in native}
    if len(prefixes) != 1:
        raise ValueError('Expected one owning repository')
    owner = next(iter(prefixes))
    peer_root = root.parent / ('fireguard-web' if owner == 'api' else 'fireguard-api')
    peer = {} if local_only else catalogs(peer_root)[0]
    if native.keys() & peer.keys():
        raise ValueError('Duplicate IDs across repositories')
    combined = native | peer
    spec = importlib.util.spec_from_file_location('fireguard_registry_policy', root / '.codex/scripts/agent_config.py')
    policy = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(policy)
    configs = []
    for filename in ['config.example.toml', 'config.toml']:
        path = root / '.codex' / filename
        if path.is_file():
            config = tomllib.loads(path.read_text(encoding='utf-8'))
            policy.validate_role_registry(config, path, local_only=local_only)
            registered = {name for name in config.get('agents', {}) if name.startswith(('fg-api-', 'fg-web-'))}
            if not local_only and native.keys() | registered != combined.keys():
                raise ValueError(f'Incomplete shared catalog: {filename}')
            configs.append(filename)
    if 'config.example.toml' not in configs:
        raise ValueError('Missing shared configuration example')
    references = instruction_checker()
    example = tomllib.loads((root / '.codex/config.example.toml').read_text(encoding='utf-8'))
    known = set(native) | {name for name in example.get('agents', {}) if policy.is_fireguard_agent(name)}
    known.update(path.name for path in (root / '.agents/skills').glob('fg-*') if (path / 'SKILL.md').is_file())
    documents = list((root / '.claude').rglob('*.md'))
    documents.extend(root / name for name in ['AGENTS.md', 'CLAUDE.md'] if (root / name).is_file())
    references.check_documents(root, documents, known, prefixes=('.claude',))
    return {'owner': owner, 'local_agents': len(native), 'peer_agents': None if local_only else len(peer),
            'shared_agents': None if local_only else len(combined),
            'luna_fast': sum(agent['model'] == 'gpt-6-luna' for agent in combined.values()),
            'scope': 'local' if local_only else 'shared',
            'peer_definitions': 'not_checked' if local_only else 'checked',
            'configs': configs, 'status': 'PASS', 'runtime_verified': False}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--local-only', action='store_true', help='Verify local parity and peer declaration structure only.')
    args = parser.parse_args()
    try:
        print(json.dumps(check(Path(__file__).resolve().parents[2], local_only=args.local_only), indent=2))
    except (OSError, ValueError, KeyError) as error:
        print(str(error), file=sys.stderr)
        raise SystemExit(1)
