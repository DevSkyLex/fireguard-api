#!/usr/bin/env python3
"""Require immutable commits for authored remote GitHub Actions."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
errors = []
for folder in ('workflows', 'actions'):
    for path in sorted((ROOT / '.github' / folder).rglob('*')):
        if not path.is_file() or path.suffix.lower() not in ('.yml', '.yaml'):
            continue
        for number, line in enumerate(path.read_text(encoding='utf-8').splitlines(), 1):
            match = re.search(r'^\s*(?:-\s*)?uses:\s*([^\s#]+)', line)
            if match and not match[1].startswith('./') and not re.fullmatch(r'[^@]+@[a-f0-9]{40}', match[1]):
                errors.append(f'{path.relative_to(ROOT)}:{number}: remote action must use a commit SHA: {match[1]}')
if errors:
    raise SystemExit('\n'.join(errors))
print('PASS immutable GitHub Action pins')
