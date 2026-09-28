import { mkdtemp, realpath, writeFile } from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { checkLinks } from './lib/check-links.mjs';
import { discoverDocuments } from './lib/discover-documents.mjs';
import { renderMermaid } from './lib/render-mermaid.mjs';

const args = process.argv.slice(2);
const options = new Map();
for (let index = 0; index < args.length; index += 2) {
  if (!['--root', '--output-dir'].includes(args[index]) || !args[index + 1]) {
    throw new Error('Usage: node check.mjs [--root directory] [--output-dir directory]');
  }
  options.set(args[index], args[index + 1]);
}
const root = await realpath(
  path.resolve(options.get('--root') ?? fileURLToPath(new URL('../..', import.meta.url))),
);
const outputDirectory = options.has('--output-dir')
  ? path.resolve(options.get('--output-dir'))
  : await mkdtemp(path.join(os.tmpdir(), 'fireguard-documentation-'));
const documents = await discoverDocuments(root);
if (!documents.length) throw new Error('No project Markdown documents discovered');
const links = await checkLinks(root, documents);
const diagrams = [];
for (const file of documents) {
  const document = links.parsed.get(path.resolve(root, file));
  for (const diagram of document.diagrams) diagrams.push({ file, ...diagram });
}
const mermaid = await renderMermaid(diagrams, outputDirectory);
const errors = [...links.errors, ...mermaid.errors];
const summary = {
  documents: documents.length,
  links: links.checked,
  diagrams: mermaid.rendered,
  errors: errors.length,
  outputDirectory,
};
await writeFile(path.join(outputDirectory, 'summary.json'), JSON.stringify(summary, null, 2));
for (const error of errors) process.stderr.write(`${error}\n`);
process.stdout.write(`${JSON.stringify(summary, null, 2)}\n`);
if (errors.length) process.exitCode = 1;
