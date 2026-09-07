#!/usr/bin/env node
/** Format a touched API PHP source file with the project's installed PHP-CS-Fixer. */
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';

function findRoot(fromFile) {
  let directory = path.dirname(path.resolve(fromFile));
  for (;;) {
    if (existsSync(path.join(directory, 'vendor', 'bin', 'php-cs-fixer'))) return directory;
    const parent = path.dirname(directory);
    if (parent === directory) return null;
    directory = parent;
  }
}

function readStdin() {
  return new Promise((resolve) => {
    let data = '';
    process.stdin.on('data', (chunk) => (data += chunk));
    process.stdin.on('end', () => resolve(data));
    if (process.stdin.isTTY) resolve('');
  });
}

const raw = await readStdin();
let payload = {};
try {
  payload = JSON.parse(raw || '{}');
} catch {
  process.exit(0);
}

const filePath = payload?.tool_input?.file_path ?? payload?.tool_input?.filePath;
if (!filePath || !existsSync(filePath)) process.exit(0);
const normalized = filePath.replace(/\\/g, '/');
if (['/vendor/', '/var/', '/node_modules/', '/.git/'].some((part) => normalized.includes(part))) process.exit(0);
if (!/\/(src|tests)\/.*\.php$/.test(normalized)) process.exit(0);

const root = findRoot(filePath);
if (!root) process.exit(0);
try {
  execFileSync('php', ['vendor/bin/php-cs-fixer', 'fix', filePath], {
    cwd: root,
    stdio: 'ignore',
    shell: process.platform === 'win32',
    windowsHide: true,
  });
} catch (error) {
  if (error?.code === 'ENOENT') process.exit(0);
  process.stderr.write(`format hook: php-cs-fixer failed for ${filePath}: ${error.message}\n`);
  process.exit(2);
}

process.exit(0);
