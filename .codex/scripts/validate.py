"""Validate native Codex manifests, skills, references and hooks."""
from pathlib import Path, PurePosixPath, PureWindowsPath
import argparse
import json
import re
import sys
import tomllib

from check_links import check_links
from check_references import check_documents, instruction_documents

from agent_config import (
    validate_global_policy, validate_native_agent, validate_role_registry,
)


def validate_mcp_portability(config: dict) -> None:
    """Keep the shared MCP example independent of machine paths."""
    for name, server in config.get('mcp_servers', {}).items():
        command = server.get('command', '')
        assert command and '/' not in command and '\\' not in command, f'{name}: resolve executables through PATH'
        values = [server.get('cwd', '')] + list(server.get('args', [])) + list(server.get('env', {}).values())
        for value in values:
            if isinstance(value, str):
                assert not (PurePosixPath(value).is_absolute() or PureWindowsPath(value).is_absolute()), (
                    f'{name}: absolute paths belong in personal configuration'
                )


def validate(root: Path, *, local_only: bool = False) -> dict[str, int | str]:
    skills_root = root / '.agents/skills'
    names: set[str] = set()
    for skill in sorted(path for path in skills_root.iterdir() if path.is_dir()):
        entry = skill / 'SKILL.md'
        assert entry.is_file(), f'Missing SKILL.md: {skill.name}'
        text = entry.read_text(encoding='utf-8')
        frontmatter = re.match(r'^---\r?\n(.*?)\r?\n---', text, re.S)
        assert frontmatter, f'Missing frontmatter: {skill.name}'
        fields = frontmatter.group(1)
        name = re.search(r'^name:\s*["\']?([a-z0-9-]+)', fields, re.M)
        assert name and name.group(1) == skill.name, f'Invalid skill name: {skill.name}'
        assert re.search(r'^description:\s*\S', fields, re.M), f'Missing description: {skill.name}'
        assert skill.name not in names, f'Duplicate skill: {skill.name}'
        names.add(skill.name)
        for relative in re.findall(r'\]\(([^)]+)\)', text):
            if '://' not in relative and not relative.startswith('#'):
                assert (skill / relative).resolve().is_file(), f'Broken reference in {skill.name}: {relative}'

    config = tomllib.loads((root / '.codex/config.example.toml').read_text(encoding='utf-8'))
    validate_global_policy(config)
    validate_mcp_portability(config)

    agents: set[str] = set()
    for path in sorted((root / '.codex/agents').glob('*.toml')):
        data = tomllib.loads(path.read_text(encoding='utf-8'))
        for key in ['name', 'description', 'developer_instructions']:
            assert isinstance(data.get(key), str) and data[key], f'Missing {key}: {path.name}'
        assert data['name'] not in agents, f'Duplicate agent: {data["name"]}'
        validate_native_agent(data, path.name)
        agents.add(data['name'])

    validate_role_registry(config, root / '.codex/config.example.toml', local_only=local_only)
    check_links(root)
    declared = {name for name in config.get('agents', {}) if name.startswith(('fg-api-', 'fg-web-'))}
    check_documents(root, instruction_documents(root), names | agents | declared)

    rules = (root / '.codex/rules.md').read_text(encoding='utf-8')
    for relative in re.findall(r'\]\((rules/[^)]+\.md)\)', rules):
        assert (root / '.codex' / relative).is_file(), f'Missing rule: {relative}'

    hooks = json.loads((root / '.codex/hooks.json').read_text(encoding='utf-8'))['hooks']
    assert {'PreToolUse', 'PostToolUse'} <= hooks.keys(), 'Incomplete hook manifest'
    for groups in hooks.values():
        for group in groups:
            re.compile(group['matcher'])
            assert group['hooks'] and all(hook['type'] == 'command' for hook in group['hooks'])

    legacy_root = b'.' + b'cl' + b'aude'
    legacy_env = b'CL' + b'AUDE_PROJECT_DIR'
    forbidden = re.compile(re.escape(legacy_root) + rb'(?:[/\\]|\b)|' + legacy_env + b'|' + legacy_root[1:] + b'/', re.I)
    legacy = [
        path.relative_to(root).as_posix()
        for folder in [root / '.codex', root / '.agents']
        for path in folder.rglob('*')
        if path.is_file() and '__pycache__' not in path.parts and path.suffix != '.pyc'
        and forbidden.search(path.read_bytes())
    ]
    assert not legacy, f'Legacy client paths: {legacy}'
    return {'skills': len(names), 'agents': len(agents), 'rules': len(list((root / '.codex/rules').glob('*.md'))),
            'scope': 'local' if local_only else 'shared',
            'peer_definitions': 'not_checked' if local_only else 'checked' if declared else 'not_declared',
            'status': 'PASS'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--local-only', action='store_true', help='Check peer declaration structure without opening peer definitions.')
    args = parser.parse_args()
    try:
        print(json.dumps(validate(Path(__file__).resolve().parents[2], local_only=args.local_only), indent=2))
    except (AssertionError, OSError, ValueError, KeyError) as error:
        print(str(error), file=sys.stderr)
        raise SystemExit(1)
