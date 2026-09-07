"""Rebind checkout-local MCP paths without changing user policy."""
from pathlib import Path
import argparse
import json
import re
import tomllib


SERVERS = {'context7', 'serena-api'}


def bind_config(text: str, root: Path) -> str:
    tomllib.loads(text)
    location = json.dumps(root.resolve().as_posix())
    section = None
    output: list[str] = []
    for line in text.splitlines():
        header = re.fullmatch(r'\[mcp_servers\."?([\w-]+)"?\]', line.strip())
        if header:
            section = header.group(1)
        elif line.startswith('['):
            section = None
        if section in SERVERS and re.match(r'^cwd\s*=', line):
            line = 'cwd = ' + location
        output.append(line)

    result = '\n'.join(output) + '\n'
    parsed = tomllib.loads(result)
    serena = parsed.get('mcp_servers', {}).get('serena-api', {})
    args = list(serena.get('args', []))
    if '--project' in args:
        args[args.index('--project') + 1] = root.resolve().as_posix()
        pattern = r'(\[mcp_servers\."?serena-api"?\][\s\S]*?\bargs\s*=\s*)\[[\s\S]*?\]'
        result = re.sub(pattern, lambda match: match[1] + json.dumps(args), result, count=1)

    tomllib.loads(result)
    return result


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    root = Path(__file__).resolve().parents[2]
    path = root / '.codex/config.toml'
    current = path.read_text(encoding='utf-8')
    updated = bind_config(current, root)
    if args.check:
        if tomllib.loads(current) != tomllib.loads(updated):
            raise SystemExit('MCP paths target another checkout. Run python .codex/scripts/configure.py.')
        print('MCP paths match this checkout.')
        return
    path.write_text(updated, encoding='utf-8')
    print('Rebound project MCP paths; models, permissions and trust unchanged.')


if __name__ == '__main__':
    main()
