import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import test from 'node:test';
import { editsFromPatch } from './adapter.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const workspace = !/fireguard-sso-(api|web)$/.test(root);
const api = workspace ? path.join(root, 'fireguard-sso-api') : root.endsWith('api') ? root : null;
const web = workspace ? path.join(root, 'fireguard-sso-web') : root.endsWith('web') ? root : null;
function invoke(tool_name, tool_input, phase = 'pre', cwd = root) {
  return spawnSync(process.execPath, [path.join(root, '.codex/hooks/adapter.mjs'), phase], {
    cwd, input: JSON.stringify({ cwd, tool_name, tool_input }), encoding: 'utf8', windowsHide: true,
  });
}
const patch = body => ({ command: '*** Begin Patch\n' + body + '\n*** End Patch' });
function allowed(body) {
  const result = invoke('apply_patch', patch(body));
  assert.equal(result.status, 0, result.stderr);
}
function blocked(body) {
  const result = invoke('apply_patch', patch(body));
  assert.equal(result.status, 2, result.stderr);
}
test('parses multiple files, removals and rename destinations', () => {
  const edits = editsFromPatch(patch('*** Add File: a.md\n+hello\n*** Update File: b.md\n*** Move to: c.md\n@@\n-old\n+new\n*** Delete File: d.md').command);
  assert.equal(edits.length, 3);
  assert.equal(edits[1].destination, 'c.md');
  assert.match(edits[1].content, /new/);
  assert.doesNotMatch(edits[1].content, /old/);
  assert.equal(edits[2].operation, 'Delete');
});
test('rejects unknown and incomplete patch payloads', () => {
  assert.equal(invoke('apply_patch', {}).status, 2);
  assert.throws(() => editsFromPatch('*** Begin Patch\n*** Add File: a.md\n+x'));
});
test('allows a documentation addition without creating it', () => {
  allowed('*** Add File: docs/codex-adapter-example.md\n+Documentation');
  assert.equal(existsSync(path.join(root, 'docs/codex-adapter-example.md')), false);
});
test('allows example environment files', () => allowed('*** Add File: .env.example\n+PUBLIC_URL=example'));
test('blocks secret addition and deletion', () => {
  blocked('*** Add File: .env.local\n+SECRET=value');
  blocked('*** Delete File: .env.local');
});
test('checks rename destination', () => blocked('*** Update File: docs/example.md\n*** Move to: .env.local\n@@\n-old\n+new'));
test('checks later files in a multi-file patch', () => blocked('*** Add File: docs/example.md\n+ok\n*** Add File: .env.local\n+SECRET=value'));
test('blocks traversal out of project', () => blocked('*** Add File: ../outside.md\n+no'));
test('blocks generated dependency files', () => blocked('*** Add File: node_modules/example/index.js\n+export {};'));
test('allows ordinary read-only Git commands', () => assert.equal(invoke('Bash', { command: 'git status --short' }).status, 0));
test('blocks destructive Git command', () => assert.equal(invoke('Bash', { command: 'git reset --hard' }).status, 2));
test('accepts exec command cmd alias', () => assert.equal(invoke('exec_command', { cmd: 'git status --short' }).status, 0));
test('enforces commit naming', () => assert.equal(invoke('Bash', { command: 'git commit -m "Bad message"' }).status, 2));
test('allows the Codex branch prefix', () => assert.equal(invoke('Bash', { command: 'git switch -c codex/api-tooling' }).status, 0));
test('post hook skips removed files', () => assert.equal(invoke('apply_patch', patch('*** Delete File: docs/not-present.xyz'), 'post').status, 0));
test('manifest hook resolves from a nested working directory', () => {
  const manifest = JSON.parse(readFileSync(path.join(root, '.codex/hooks.json'), 'utf8'));
  const command = manifest.hooks.PreToolUse[0].hooks[0].command;
  const cwd = path.join(root, '.codex/hooks');
  const result = spawnSync(command, {
    shell: true,
    cwd,
    input: JSON.stringify({ cwd, tool_name: 'Bash', tool_input: { command: 'git status --short' } }),
    encoding: 'utf8',
    windowsHide: true,
  });
  assert.equal(result.status, 0, result.stderr);
});
if (api) {
  const relative = path.relative(root, api).replaceAll('\\', '/');
  const prefix = relative ? relative + '/' : '';
  test('blocks API forbidden layer imports', () => blocked('*** Add File: ' + prefix + 'src/Example/Domain/Model/Example.php\n+<?php\n+use Example\\Infrastructure\\Adapter\\ExampleAdapter;'));
  test('allows API domain-only code', () => allowed('*** Add File: ' + prefix + 'src/Example/Domain/Model/Example.php\n+<?php\n+declare(strict_types=1);'));
  test('blocks JWT material', () => blocked('*** Add File: ' + prefix + 'config/jwt/example.pem\n+not-a-real-key'));
  test('blocks modifying an existing migration', () => {
    const folder = path.join(api, 'migrations/main');
    const migration = readdirSync(folder).find(name => /^Version\d+\.php$/.test(name));
    assert.ok(migration, 'An existing migration is required for this regression test.');
    blocked('*** Update File: ' + prefix + 'migrations/main/' + migration + '\n@@\n-old\n+new');
  });
}
if (web) {
  const relative = path.relative(root, web).replaceAll('\\', '/');
  const prefix = relative ? relative + '/' : '';
  test('blocks wildcard barrels', () => blocked('*** Add File: ' + prefix + "src/app/shared/example/index.ts\n+export * from './example';"));
  test('blocks component CSS in the theme file', () => blocked('*** Update File: ' + prefix + 'src/styles.css\n@@\n+.example { color: red; }'));
  test('blocks runtime services in models', () => blocked('*** Add File: ' + prefix + 'src/app/features/example/models/example.service.ts\n+export class Example {}'));
  test('blocks environment source files', () => blocked('*** Add File: ' + prefix + 'src/environments/environment.example.ts\n+export const environment = {};'));
}
