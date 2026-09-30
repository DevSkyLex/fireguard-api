"""Check active instruction references, including inline code, without opening targets."""
from pathlib import Path
import re


NAME = re.compile(r'(?<![\w/.-])fg-[a-z0-9]+(?:-[a-z0-9]+)*(?![\w*-])')
ROLE_SUFFIXES = ('-builder', '-reviewer', '-auditor', '-explorer', '-writer', '-maintainer', '-documenter')
LOCAL_CONFIGURATION = frozenset({'.codex/config.toml'})
RESOURCE_PREFIXES = ('.codex', '.agents/skills', '.github', 'docs', 'src', 'config', 'tests', 'scripts')
MARKER = re.compile(r'^\s*<!-- tooling-references: (historical|example) -->\s*$')
END_MARKER = '<!-- /tooling-references -->'


def active_lines(text: str):
    """Explicit examples/history are excluded; inline and fenced commands stay active."""
    fence = None
    excluded = None
    comment = False
    for number, line in enumerate(text.splitlines(), 1):
        marker = re.match(r'^\s{0,3}(`{3,}|~{3,})(.*)$', line)
        if marker:
            token, suffix = marker.groups()
            if fence is None:
                fence = token
            elif token[0] == fence[0] and len(token) >= len(fence) and not suffix.strip():
                fence = None
            continue
        if fence is not None:
            if excluded is None:
                yield number, line
            continue
        if not comment and MARKER.fullmatch(line):
            if excluded is not None:
                raise ValueError(f'Nested reference exclusion at line {number}')
            excluded = number
            continue
        if not comment and line.strip() == END_MARKER:
            if excluded is None:
                raise ValueError(f'Unmatched reference exclusion at line {number}')
            excluded = None
            continue
        if excluded is not None:
            continue
        line = re.sub(r'<!--.*?-->', '', line)
        if comment:
            if '-->' not in line:
                continue
            line = line.split('-->', 1)[1]
            comment = False
        if '<!--' in line:
            line = line.split('<!--', 1)[0]
            comment = True
        yield number, line
    if excluded is not None:
        raise ValueError(f'Unclosed reference exclusion at line {excluded}')


def instruction_documents(root: Path) -> list[Path]:
    documents = list((root / '.codex').rglob('*.md'))
    for skill in (root / '.agents/skills').glob('fg-*'):
        documents.extend(skill.rglob('*.md'))
    documents.extend(root / name for name in ['AGENTS.md', 'CLAUDE.md'] if (root / name).is_file())
    documents.extend((root / '.codex/agents').glob('*.toml'))
    return sorted(set(documents))


def check_documents(root: Path, documents: list[Path], names: set[str], *,
                    prefixes: tuple[str, ...] = RESOURCE_PREFIXES) -> dict:
    """References name existing roles/skills or explicit project-owned resources."""
    prefix = '|'.join(re.escape(value) for value in prefixes)
    paths = re.compile(r'(?<![\w./-])((?:' + prefix + r')/(?:[\w.-]+/)*[\w.-]+\.[A-Za-z0-9]+)(?![\w/])')
    errors = []
    checked = 0
    for document in documents:
        source = document.relative_to(root).as_posix()
        try:
            for number, line in active_lines(document.read_text(encoding='utf-8')):
                # Parameterized paths and role names cannot identify a concrete resource.
                line = re.sub(r'`[^`]*[<>*][^`]*`', '', line)
                # Relative Markdown destinations use the link checker's document base.
                line = re.sub(r'(?<=\]\()[^)]+(?=\))', '', line)
                for match in NAME.finditer(line):
                    name = match.group()
                    if name.startswith(('fg-api-', 'fg-web-')) or name.endswith(ROLE_SUFFIXES):
                        checked += 1
                        if name not in names:
                            errors.append(f'{source}:{number}: {name}')
                for match in paths.finditer(line):
                    relative = match.group().rstrip('.')
                    if relative in LOCAL_CONFIGURATION:
                        continue
                    checked += 1
                    target = (root / relative).resolve()
                    if not target.is_relative_to(root.resolve()) or not target.is_file():
                        errors.append(f'{source}:{number}: {relative}')
        except ValueError as error:
            errors.append(f'{source}: {error}')
    if errors:
        raise ValueError('Broken active tooling references:\n' + '\n'.join(errors))
    return {'documents': len(documents), 'references': checked, 'status': 'PASS'}
