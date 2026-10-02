#!/usr/bin/env python3
"""Fail when a test is outside every explicit PHPUnit suite or repeats a class."""
from pathlib import Path
import re
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[2]
configs = [ROOT / 'phpunit.dist.xml', ROOT / 'tests/E2E/phpunit.xml', ROOT / 'tests/Performance/phpunit.xml']
directories = []
for config in configs:
    for entry in ET.parse(config).findall('./testsuites/testsuite/directory'):
        directories.append((config.parent / (entry.text or '')).resolve())
classes = {}
errors = []
phpat_services = set(re.findall(r'^\s+class:\s+(\S+)', (ROOT / 'phpstan.dist.neon').read_text(encoding='utf-8'), re.M))
for path in sorted((ROOT / 'tests').rglob('*Test.php')):
    source = path.read_text(encoding='utf-8')
    namespace = re.search(r'^namespace\s+([^;]+);', source, re.M)
    declaration = re.search(r'^(?:(?:final|abstract|readonly)\s+)*class\s+(\w+)', source, re.M)
    name = namespace[1] + '\\' + declaration[1] if namespace and declaration else None
    # PHPAt rules are discovered by PHPStan's extension, not the PHPUnit runner.
    if (
        path.parent == ROOT / 'tests/Architecture/Rule'
        and re.search(r'^use\s+PHPat\\', source, re.M | re.I)
        and not re.search(r'^use\s+PHPUnit\\|\bextends\s+(?:\\?PHPUnit\\Framework\\)?TestCase\b', source, re.M)
        and (name in phpat_services or re.search(r'^abstract\s+class\s+', source, re.M))
    ):
        continue
    if not any(path.resolve().is_relative_to(directory) for directory in directories):
        errors.append(f'Undiscovered PHPUnit test: {path.relative_to(ROOT)}')
    if name:
        if name in classes:
            errors.append(f'Duplicate test class {name}: {classes[name]} and {path.relative_to(ROOT)}')
        classes[name] = path.relative_to(ROOT)
if errors:
    raise SystemExit('\n'.join(errors))
print(f'PASS PHPUnit discovery: {len(classes)} unique test classes in {len(configs)} explicit configurations')
