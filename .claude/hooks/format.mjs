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

// The app root is found by walking up FROM THE EDITED FILE until the fixer binary
// appears. The script's own location cannot be used: `claude plugin install` COPIES
// this .claude/ into ~/.claude/plugins/cache, so in plugin mode the running copy
// lives nowhere near the app. Anchoring on the edited file works in both modes and
// self-scopes the hook — a file outside this app never finds vendor/bin/php-cs-fixer
// above it, and the hook stays a silent no-op.
function findAppRoot(fromFile, markerSegments) {
  let dir = path.dirname(path.resolve(fromFile));
  for (;;) {
    if (existsSync(path.join(dir, ...markerSegments))) return dir;
    const parent = path.dirname(dir);
    if (parent === dir) return null;
    dir = parent;
  }
}

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

const root = findAppRoot(filePath, ['vendor', 'bin', 'php-cs-fixer']);
if (!root) process.exit(0);

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
