import assert from 'node:assert/strict';
import { execFile } from 'node:child_process';
import { mkdtemp, readFile, writeFile, mkdir } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { promisify } from 'node:util';
import { checkLinks } from '../lib/check-links.mjs';
import { discoverDocuments } from '../lib/discover-documents.mjs';
import { parseMarkdown } from '../lib/parse-markdown.mjs';

const execute = promisify(execFile);
const fixtures = fileURLToPath(new URL('./fixtures/', import.meta.url));
const entry = fileURLToPath(new URL('../check.mjs', import.meta.url));

test('valid links, references, images, duplicate headings and explicit anchors pass', async () => {
  const root = path.join(fixtures, 'valid');
  const documents = await discoverDocuments(root);
  assert.deepEqual(documents, ['README.md', 'docs/guide.md']);
  const result = await checkLinks(root, documents);
  assert.deepEqual(result.errors, []);
  assert.ok(result.checked >= 5);
});

test('missing inline and reference targets report source lines', async () => {
  const root = path.join(fixtures, 'broken-links');
  const result = await checkLinks(root, await discoverDocuments(root));
  assert.ok(
    result.errors.some((error) =>
      error.includes('README.md:3: docs/absent.md: missing local target'),
    ),
  );
  assert.ok(result.errors.some((error) => error.includes('absent.md: missing local target')));
});

test('unknown same-document headings fail', async () => {
  const root = path.join(fixtures, 'broken-anchors');
  const result = await checkLinks(root, await discoverDocuments(root));
  assert.match(result.errors[0], /README.md:3:.*unknown anchor #absent/);
});

test('GitHub slugs retain Unicode and disambiguate repeated headings', () => {
  const result = parseMarkdown('# Café\n\n## Repeat\n\n## Repeat\n');
  assert.deepEqual([...result.anchors], ['café', 'repeat', 'repeat-1']);
});

test('code and comments cannot create navigation links or diagrams', () => {
  const result = parseMarkdown(
    '`[inline](missing.md)`\n\n<!-- [hidden](missing.md) -->\n\n```md\n[code](missing.md)\n```\n',
  );
  assert.deepEqual(result.links, []);
  assert.deepEqual(result.diagrams, []);
});

test('project discovery includes source contracts and excludes agent and dependency trees', async () => {
  const root = await mkdtemp(path.join(os.tmpdir(), 'documentation-discovery-'));
  await mkdir(path.join(root, 'src', 'Example'), { recursive: true });
  await mkdir(path.join(root, '.agents', 'skills'), { recursive: true });
  await mkdir(path.join(root, 'node_modules'), { recursive: true });
  await writeFile(path.join(root, 'README.md'), '# Project');
  await writeFile(path.join(root, 'AGENTS.md'), '# Agents');
  await writeFile(path.join(root, 'src', 'Example', 'MODULE.md'), '# Module');
  await writeFile(path.join(root, 'src', 'Example', 'AGENTS.md'), '# Nested instructions');
  await writeFile(path.join(root, 'src', 'Example', 'CLAUDE.md'), '# Nested client instructions');
  await writeFile(path.join(root, '.agents', 'skills', 'SKILL.md'), '# Skill');
  await writeFile(path.join(root, 'node_modules', 'README.md'), '# Dependency');
  assert.deepEqual(await discoverDocuments(root), ['README.md', 'src/Example/MODULE.md']);
});

test('malformed URL encodings and local links outside the repository fail', async () => {
  const root = await mkdtemp(path.join(os.tmpdir(), 'documentation-boundary-'));
  await writeFile(
    path.join(root, 'README.md'),
    '# Project\n\n[Outside](../outside.md)\n\n[Encoding](%ZZ.md)',
  );
  const result = await checkLinks(root, ['README.md']);
  assert.equal(result.errors.length, 2);
  assert.ok(result.errors.some((error) => error.includes('leaves the repository')));
  assert.ok(result.errors.some((error) => error.includes('missing local target')));
});

test(
  'the CLI renders a real diagram and retains its SVG and summary',
  { timeout: 60000 },
  async () => {
    const output = await mkdtemp(path.join(os.tmpdir(), 'documentation-valid-render-'));
    await execute(process.execPath, [
      entry,
      '--root',
      path.join(fixtures, 'valid'),
      '--output-dir',
      output,
    ]);
    const summary = JSON.parse(await readFile(path.join(output, 'summary.json'), 'utf8'));
    assert.equal(summary.errors, 0);
    assert.equal(summary.diagrams, 1);
    assert.match(await readFile(path.join(output, '001-docs-guide.md-13.svg'), 'utf8'), /<svg/);
  },
);

test(
  'the CLI fails for an actual Mermaid syntax error and names its source',
  { timeout: 60000 },
  async () => {
    const output = await mkdtemp(path.join(os.tmpdir(), 'documentation-invalid-render-'));
    await assert.rejects(
      execute(process.execPath, [
        entry,
        '--root',
        path.join(fixtures, 'invalid-mermaid'),
        '--output-dir',
        output,
      ]),
      (error) =>
        error.code === 1 &&
        /README\.md:3: Mermaid:/.test(error.stderr) &&
        /Parse error|Syntax error/.test(error.stderr),
    );
    const summary = JSON.parse(await readFile(path.join(output, 'summary.json'), 'utf8'));
    assert.equal(summary.errors, 1);
    assert.equal(summary.diagrams, 0);
  },
);

test('the CLI fails on broken links instead of reporting a successful render-only check', async () => {
  const output = await mkdtemp(path.join(os.tmpdir(), 'documentation-invalid-links-'));
  await assert.rejects(
    execute(process.execPath, [
      entry,
      '--root',
      path.join(fixtures, 'broken-links'),
      '--output-dir',
      output,
    ]),
    (error) => error.code === 1 && /missing local target/.test(error.stderr),
  );
});
