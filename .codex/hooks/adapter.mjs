import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, realpathSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const apps = ['fireguard-sso-api', 'fireguard-sso-web'];
const inside = (parent, file) => {
  const relative = path.relative(parent, file);
  return relative === '' || (!relative.startsWith(`..${path.sep}`) && relative !== '..' && !path.isAbsolute(relative));
};

/** Parse native apply_patch input without executing it or reading target contents. */
export function editsFromPatch(patch) {
  if (typeof patch !== 'string' || !patch.startsWith('*** Begin Patch')) {
    throw new Error('Unsupported patch payload; expected tool_input.command.');
  }
  const edits = [];
  let current;
  for (const line of patch.replaceAll('\r\n', '\n').split('\n')) {
    const header = /^\*\*\* (Add|Update|Delete) File: (.+)$/.exec(line);
    if (header) {
      current = { file: header[2], operation: header[1], content: '' };
      edits.push(current);
    } else if (line.startsWith('*** Move to: ')) {
      if (!current || current.operation !== 'Update') throw new Error('Invalid move header.');
      current.destination = line.slice('*** Move to: '.length);
    } else if (current && line.startsWith('+')) {
      current.content += `${line.slice(1)}\n`;
    } else if (current && line.startsWith(' ')) {
      current.content += `${line.slice(1)}\n`;
    } else if (current && (line.startsWith('@@') || line.startsWith('-'))) {
      current.content += '\n';
    }
  }
  if (!patch.trimEnd().endsWith('*** End Patch') || edits.length === 0) {
    throw new Error('Incomplete or empty patch; cannot inspect it.');
  }
  return edits;
}

function run(script, payload, cwd) {
  if (!existsSync(script)) throw new Error(`Missing shared hook: ${script}`);
  const result = spawnSync(process.execPath, [script], {
    cwd,
    env: process.env,
    input: JSON.stringify(payload),
    encoding: 'utf8',
    timeout: 20000,
    windowsHide: true,
  });
  if (result.error || result.status !== 0) {
    throw new Error(result.stderr?.trim() || result.error?.message || `Hook exited ${result.status}`);
  }
}

function canonicalTarget(file) {
  let ancestor = file;
  while (!existsSync(ancestor)) {
    const parent = path.dirname(ancestor);
    if (parent === ancestor) throw new Error('Cannot resolve target parent.');
    ancestor = parent;
  }
  return path.resolve(realpathSync(ancestor), path.relative(ancestor, file));
}

export function handle(payload, phase) {
  const cwd = path.resolve(payload.cwd || root);
  const isWorkspace = !apps.includes(path.basename(root));
  // Parent and child config layers may both load: the child owns nested sessions.
  if (isWorkspace && apps.some(app => inside(path.join(root, app), cwd))) return;
  const input = payload.tool_input;
  if (/^(Bash|PowerShell|exec_command)$/.test(payload.tool_name || '')) {
    if (phase !== 'pre') return;
    const command = input?.command ?? input?.cmd;
    if (typeof command !== 'string') throw new Error('Missing shell command.');
    const mapped = { ...payload, tool_input: { command } };
    run(path.join(root, '.codex/hooks/guard-git.mjs'), mapped, root);
    const deletionGuard = path.join(root, '.codex/hooks/guard-delete.mjs');
    run(deletionGuard, mapped, root);
    return;
  }
  const patch = typeof input === 'string' ? input : input?.command ?? input?.patch ?? input?.input;
  const edits = /apply_patch/.test(payload.tool_name || '') || typeof patch === 'string'
    ? editsFromPatch(patch)
    : [{ file: input?.file_path ?? input?.filePath, content: input?.content ?? input?.new_string ?? '', operation: 'Update' }];
  for (const edit of edits) {
    if (!edit.file) throw new Error('Missing edit path.');
    const targets = phase === 'pre' ? [edit.file, edit.destination].filter(Boolean) : [edit.destination || edit.file];
    for (const target of targets) {
      const file = path.resolve(cwd, target);
      if (!inside(root, file) || !inside(realpathSync(root), canonicalTarget(file))) {
        throw new Error('Edit escapes the configured project (including symlinks).');
      }
      const owner = isWorkspace ? apps.map(app => path.join(root, app)).find(app => inside(app, file)) || root : root;
      const normalized = file.replaceAll('\\', '/');
      if (phase === 'pre' && /\/src\/environments\/environment[^/]*\.ts$/.test(normalized)) {
        throw new Error('Environment files are excluded; use documented public configuration.');
      }
      const mapped = { ...payload, tool_input: { file_path: file, content: edit.content } };
      if (phase === 'pre') {
        run(path.join(owner, '.codex/hooks/guard.mjs'), mapped, owner);
      } else if (edit.operation !== 'Delete' && existsSync(file)) {
        run(path.join(owner, '.codex/hooks/format.mjs'), mapped, owner);
      }
    }
  }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const payload = JSON.parse(readFileSync(0, 'utf8'));
    const phase = process.argv[2];
    if (!['pre', 'post'].includes(phase)) throw new Error('Expected pre or post.');
    handle(payload, phase);
  } catch (error) {
    process.stderr.write(`FireGuard Codex hook: ${error.message}\n`);
    process.exitCode = 2;
  }
}
