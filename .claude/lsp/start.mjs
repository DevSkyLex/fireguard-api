#!/usr/bin/env node
/**
 * LSP launcher — resolves the app directory and the global Intelephense install at runtime,
 * so nothing here is machine-specific.
 *
 * `${CLAUDE_PLUGIN_ROOT}` points at the plugin *cache copy* and `${CLAUDE_PROJECT_DIR}` is
 * the monorepo root in a root session but the app itself in an app session — no single
 * expansion covers both. This file finds the app by looking for `bin/console`, then:
 *
 *  1. spawns Intelephense from wherever `npm install -g` put it, with the app as cwd;
 *  2. rewrites `rootUri` / `rootPath` / `workspaceFolders` in the ONE `initialize` request,
 *     which is what `workspaceFolder` in .lsp.json used to hardcode;
 *  3. pipes every later byte through untouched — the framing is parsed exactly once, so a
 *     bug here cannot corrupt a running session.
 *
 * Usage: node start.mjs php
 */
import { execFileSync, spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const APP_MARKER = join('bin', 'console');
const APP_DIRNAME = 'fireguard-sso-api';
const ENTRY = join('intelephense', 'lib', 'intelephense.js');

/** Candidate roots, most specific first; the first one holding APP_MARKER wins. */
function resolveAppDir() {
  const seeds = [process.env.CLAUDE_PROJECT_DIR, process.cwd()].filter(Boolean).map((p) => resolve(p));
  const candidates = [];
  for (const seed of seeds) {
    candidates.push(seed, join(seed, APP_DIRNAME));
    for (let dir = seed, up = dirname(dir); up !== dir; dir = up, up = dirname(dir))
      candidates.push(up, join(up, APP_DIRNAME));
  }
  return candidates.find((c) => existsSync(join(c, APP_MARKER)));
}

/**
 * Where a global npm install lands: next to the node binary on Windows, under
 * `<prefix>/lib/node_modules` on POSIX. `npm root -g` is the fallback because it costs a
 * process spawn.
 */
function resolveIntelephense() {
  const nodeDir = dirname(process.execPath);
  const guesses = [join(nodeDir, 'node_modules', ENTRY), join(nodeDir, '..', 'lib', 'node_modules', ENTRY)];
  const hit = guesses.find((g) => existsSync(g));
  if (hit) return hit;
  try {
    const root = execFileSync('npm', ['root', '-g'], { encoding: 'utf8', shell: process.platform === 'win32' }).trim();
    const fromNpm = join(root, ENTRY);
    if (existsSync(fromNpm)) return fromNpm;
  } catch {
    /* npm not on PATH — fall through to the error below. */
  }
  return undefined;
}

if (process.argv[2] !== 'php') {
  process.stderr.write(`[lsp-launcher] unknown server "${process.argv[2]}" — expected "php"\n`);
  process.exit(2);
}

const app = resolveAppDir();
if (!app) {
  process.stderr.write(`[lsp-launcher] no ${APP_MARKER} found from CLAUDE_PROJECT_DIR=${process.env.CLAUDE_PROJECT_DIR} or cwd=${process.cwd()}\n`);
  process.exit(2);
}

const entry = resolveIntelephense();
if (!entry) {
  process.stderr.write('[lsp-launcher] intelephense not found — run: npm install -g intelephense\n');
  process.exit(2);
}

const child = spawn(process.execPath, [entry, '--stdio'], { cwd: app, stdio: ['pipe', 'pipe', 'inherit'] });
child.on('error', (e) => {
  process.stderr.write(`[lsp-launcher] spawn failed: ${e.message}\n`);
  process.exit(2);
});
child.on('exit', (code, signal) => process.exit(code ?? (signal ? 1 : 0)));

child.stdout.pipe(process.stdout);

const frame = (msg) => {
  const body = JSON.stringify(msg);
  return `Content-Length: ${Buffer.byteLength(body)}\r\n\r\n${body}`;
};

/** Rewrite the workspace roots the client asked for to the app we resolved. */
function retarget(msg) {
  if (msg?.method !== 'initialize' || !msg.params) return msg;
  const uri = pathToFileURL(app).href;
  msg.params.rootUri = uri;
  msg.params.rootPath = app;
  msg.params.workspaceFolders = [{ uri, name: APP_DIRNAME }];
  return msg;
}

let buf = Buffer.alloc(0);
let done = false;
process.stdin.on('data', (chunk) => {
  if (done) return;
  buf = Buffer.concat([buf, chunk]);
  const head = buf.indexOf('\r\n\r\n');
  if (head === -1) return;
  const len = Number(/Content-Length: (\d+)/i.exec(buf.subarray(0, head).toString())?.[1]);
  if (!Number.isFinite(len) || buf.length < head + 4 + len) return;

  const body = buf.subarray(head + 4, head + 4 + len).toString();
  const rest = buf.subarray(head + 4 + len);
  try {
    child.stdin.write(frame(retarget(JSON.parse(body))));
  } catch {
    child.stdin.write(buf.subarray(0, head + 4 + len)); // Unparseable: forward verbatim.
  }
  if (rest.length > 0) child.stdin.write(rest);

  done = true;
  buf = Buffer.alloc(0);
  process.stdin.pipe(child.stdin);
});
process.stdin.on('end', () => child.stdin.end());
