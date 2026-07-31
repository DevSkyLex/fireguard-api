#!/usr/bin/env node
/**
 * PostToolUse hook (fireguard-sso-api) - auto-format the file Claude just edited with
 * the project's own PHP-CS-Fixer config, so no change ever fails `make cs-lint` for
 * style reasons alone.
 *
 *  - `*.php` under `src/` or `tests/` -> `php vendor/bin/php-cs-fixer fix <file>`
 *
 * The fixer config sets `setIndent('  ')` - two spaces, not the PSR-12 four - so
 * hand-written PHP will almost always need this pass.
 *
 * Anything else (YAML, docs, migrations outside the fixer's scope) is a silent no-op.
 * Degrades to a no-op when the toolchain is absent, so it is safe in a fresh checkout.
 * A genuine formatting failure is reported on stderr with exit 2 so Claude sees it.
 */
import { execFileSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

// This file is `<app>/.claude/hooks/format.mjs`, so the app root is two levels up.
// Deriving it from the script's own location rather than the payload cwd keeps the
// hook correct in both modes: standalone (cwd IS the app root) and loaded as a plugin
// from the monorepo root (cwd is the monorepo, and a cwd-based root would silently
// find no vendor/bin/php-cs-fixer and turn the hook into a no-op).
const APP_ROOT = path
  .resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..')
  .replace(/\\/g, '/');

const SKIP_SEGMENTS = ['/vendor/', '/var/', '/node_modules/', '/.git/'];

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
if (SKIP_SEGMENTS.some((seg) => normalized.includes(seg))) process.exit(0);
if (path.extname(normalized).toLowerCase() !== '.php') process.exit(0);
if (!/\/(src|tests)\//.test(normalized)) process.exit(0);

const root = APP_ROOT;
if (!existsSync(path.join(root, 'vendor', 'bin', 'php-cs-fixer'))) process.exit(0);

try {
  execFileSync('php', ['vendor/bin/php-cs-fixer', 'fix', filePath], {
    cwd: root,
    stdio: 'ignore',
    shell: process.platform === 'win32',
  });
} catch (error) {
  // ENOENT (no PHP on PATH) must not block; a real fixer failure should.
  if (error?.code === 'ENOENT') process.exit(0);
  process.stderr.write(`format hook: php-cs-fixer failed for ${filePath}: ${error.message}\n`);
  process.exit(2);
}

process.exit(0);
