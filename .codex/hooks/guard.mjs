#!/usr/bin/env node
/** Checkout-local Codex guard for API secrets, generated code, layers and migrations. */
import { existsSync } from 'node:fs';

function readStdin() {
  return new Promise((resolve) => {
    let data = '';
    process.stdin.on('data', (chunk) => (data += chunk));
    process.stdin.on('end', () => resolve(data));
    if (process.stdin.isTTY) resolve('');
  });
}

function deny(message) {
  process.stderr.write(`Blocked: ${message}\n`);
  process.exit(2);
}

const raw = await readStdin();
let payload = {};
try {
  payload = JSON.parse(raw || '{}');
} catch {
  process.exit(0);
}

const filePath = (payload?.tool_input?.file_path ?? payload?.tool_input?.filePath ?? '').replace(/\\/g, '/');
if (!filePath) process.exit(0);

const base = filePath.split('/').pop() ?? '';
if (/(^|\/)\.env(\.|$)/.test(filePath) && !/^\.env\.(example|dist)$/.test(base)) {
  deny(`${filePath} is an environment or secret file. Document public variables in .env.example.`);
}
if (/\/config\/jwt\//.test(filePath)) {
  deny(`${filePath} is JWT key material and must never be edited.`);
}

for (const [segment, label] of [
  ['/vendor/', 'Composer dependency'],
  ['/var/', 'Symfony runtime output'],
  ['/node_modules/', 'npm dependency'],
  ['/public/bundles/', 'installed asset output'],
]) {
  if (filePath.includes(segment)) deny(`${filePath} is inside a generated ${label}. Edit its source.`);
}

const written = payload?.tool_input?.content ?? payload?.tool_input?.new_string ?? '';
const layerMatch = /\/src\/[A-Z][A-Za-z]*\/(Domain|Application|Infrastructure|Presentation)\//.exec(filePath);
if (layerMatch && written) {
  const forbiddenByLayer = {
    Domain: ['Application', 'Infrastructure', 'Presentation'],
    Application: ['Infrastructure', 'Presentation'],
  };
  for (const target of forbiddenByLayer[layerMatch[1]] ?? []) {
    if (new RegExp(String.raw`^\s*use\s+[A-Z][A-Za-z]*\\${target}\\`, 'm').test(written)) {
      deny(`${filePath} imports ${target} from ${layerMatch[1]}. Use the documented hexagonal direction and ports.`);
    }
  }
}

if (/\/migrations\/(auth|main)\/Version\d+\.php$/.test(filePath) && existsSync(filePath)) {
  deny(`${filePath} already exists. Create a new migration with the explicit database configuration.`);
}

process.exit(0);
