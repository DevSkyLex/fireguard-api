"""Check local Markdown link targets in authored Codex docs (no network or writes).

Fragments are ignored; this verifies files/directories, not renderer-specific heading IDs.
Fenced code and inline code are examples, not navigation links. Vendored skills are
covered by their integrity lock and intentionally excluded from authored-doc checks.
"""
from pathlib import Path
import json
import re
import sys
from urllib.parse import unquote


def authored_documents(root: Path) -> list[Path]:
    documents = list((root / '.codex').rglob('*.md'))
    for skill in (root / '.agents/skills').glob('fg-*'):
        documents.extend(skill.rglob('*.md'))
    if (root / 'AGENTS.md').is_file():
        documents.append(root / 'AGENTS.md')
    return sorted(set(documents))


def prose_lines(text: str):
    """Keep source line numbers while omitting fenced/inline code and comments."""
    fence = None
    in_comment = False
    for number, line in enumerate(text.splitlines(), 1):
        marker = re.match(r'^\s{0,3}(`{3,}|~{3,})', line)
        if marker:
            token = marker.group(1)
            if fence is None:
                fence = token
            elif token[0] == fence[0] and len(token) >= len(fence):
                fence = None
            continue
        if fence:
            continue
        line = re.sub(r'<!--.*?-->', '', line)
        if in_comment:
            if '-->' not in line:
                continue
            line = line.split('-->', 1)[1]
            in_comment = False
        if '<!--' in line:
            line = line.split('<!--', 1)[0]
            in_comment = True
        yield number, re.sub(r'(`+).*?\1', '', line)


def link_targets(text: str):
    """Support ordinary inline links and reference definitions used in these docs."""
    for number, line in prose_lines(text):
        for match in re.finditer(r'!?\[[^\]\n]*\]\(\s*(<[^>]+>|[^\s)]+)(?:\s+["\'][^\n]*?["\'])?\s*\)', line):
            yield number, match.group(1).strip('<>')
        definition = re.match(r'^\s{0,3}\[[^\]]+\]:\s*(<[^>]+>|\S+)', line)
        if definition:
            yield number, definition.group(1).strip('<>')


def check_links(root: Path) -> dict:
    count = 0
    errors = []
    documents = authored_documents(root)
    for document in documents:
        for number, target in link_targets(document.read_text(encoding='utf-8')):
            if target.startswith(('#', '//')) or re.match(r'^[a-zA-Z][a-zA-Z0-9+.-]*:', target):
                continue
            target_path = unquote(target.split('#', 1)[0].split('?', 1)[0])
            if not target_path:
                continue
            count += 1
            if not (document.parent / target_path).exists():
                errors.append(f'{document.relative_to(root).as_posix()}:{number}: {target}')
    if errors:
        raise ValueError('Broken local Markdown links:\n' + '\n'.join(errors))
    return {'documents': len(documents), 'local_links': count, 'status': 'PASS'}


if __name__ == '__main__':
    try:
        print(json.dumps(check_links(Path(__file__).resolve().parents[2]), indent=2))
    except (OSError, ValueError) as error:
        print(str(error), file=sys.stderr)
        raise SystemExit(1)
