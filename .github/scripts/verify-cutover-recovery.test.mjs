import assert from 'node:assert/strict';
import test from 'node:test';
import {
  runCutoverRecovery,
  validateCutoverRecoveryConfig,
  verifyCutoverFailureLog,
  verifyCutoverRecovery,
} from './verify-cutover-recovery.mjs';

const oldSha = 'a'.repeat(40);
const newSha = 'b'.repeat(40);
const repo = 'example/fireguard-api';
const runId = 38288952348;
const now = Date.parse('2026-10-12T20:00:00Z');
const start = '2026-10-12T18:00:00Z';
const end = '2026-10-12T18:14:00Z';
const task = 'Verify the effective auth Webhook queue before snapshots or schema changes';
const message = 'The installed Webhook transport is unsupported; cutover was refused without discarding its work.';
const env = {
  RECOVER_CUTOVER_RUN_ID: String(runId),
  GITHUB_RUN_ID: String(runId + 1),
  SOURCE_BRANCH: 'develop',
  SOURCE_SHA: newSha,
  GITHUB_REPOSITORY: repo,
  GH_TOKEN: 'test-token',
  GITHUB_OUTPUT: 'output-file',
};
const config = validateCutoverRecoveryConfig(env);

function run(changes = {}) {
  return {
    id: runId,
    event: 'workflow_dispatch',
    head_branch: 'develop',
    head_sha: oldSha,
    head_repository: { full_name: repo },
    repository: { full_name: repo },
    path: '.github/workflows/deploy-vps.yml',
    status: 'completed',
    conclusion: 'failure',
    run_attempt: 1,
    ...changes,
  };
}

function step(changes = {}) {
  return {
    name: 'Run Ansible deployment',
    status: 'completed',
    conclusion: 'failure',
    started_at: '2026-10-12T18:01:00Z',
    completed_at: '2026-10-12T18:13:10Z',
    ...changes,
  };
}

function job(changes = {}) {
  return {
    id: 123,
    run_id: runId,
    head_branch: 'develop',
    head_sha: oldSha,
    name: 'Deploy to VPS',
    status: 'completed',
    conclusion: 'failure',
    started_at: start,
    completed_at: end,
    steps: [step()],
    ...changes,
  };
}

function guard(changes = {}) {
  return job({
    id: 124,
    name: 'Verify source CI and image provenance',
    conclusion: 'success',
    steps: [],
    ...changes,
  });
}

function fatal(changes = {}) {
  return {
    changed: false,
    changed_when_result: false,
    cmd: ['python3', '/private-path/fireguard-webhook-cutover.py', '--project', 'private-project'],
    rc: 1,
    msg: 'The command exited with a non-zero return code.',
    stderr: message,
    stderr_lines: [message],
    stdout: '',
    stdout_lines: [],
    ...changes,
  };
}

function logs(failure = fatal()) {
  return [
    '2026-10-12T18:13:06.1234567Z TASK [Synchronize main database role password] ************************',
    '2026-10-12T18:13:06.1234567Z changed: [fireguard]',
    `2026-10-12T18:13:07.1234567Z TASK [${task}] ************************`,
    `2026-10-12T18:13:09.1234567Z fatal: [fireguard]: FAILED! => ${JSON.stringify(failure)}`,
    '2026-10-12T18:13:09.1234567Z PLAY RECAP ********************************************************',
    '2026-10-12T18:13:09.1234567Z fireguard : ok=62 changed=11 unreachable=0 failed=1 skipped=15 rescued=0 ignored=0',
    '2026-10-12T18:13:10.1234567Z ##[error]Process completed with exit code 2.',
  ].join('\n');
}

function comparison(changes = {}) {
  return {
    status: 'ahead',
    base_commit: { sha: oldSha },
    merge_base_commit: { sha: oldSha },
    ahead_by: 1,
    behind_by: 0,
    ...changes,
  };
}

function response(body, changes = {}) {
  return {
    ok: true,
    status: 200,
    headers: { get: () => null },
    json: async () => body,
    text: async () => body,
    ...changes,
  };
}

function fakeApi({ metadata = run(), jobs = [job(), guard()], jobPages, activeRuns = [], log = logs(), compare = comparison(), intercept } = {}) {
  const calls = [];
  const fetcher = async (input, options) => {
    const url = new URL(input);
    calls.push({ url, options });
    const override = await intercept?.(url, options);
    if (override) return override;
    if (url.pathname.endsWith(`/runs/${runId}`)) return response(metadata);
    if (url.pathname.endsWith(`/runs/${runId}/attempts/1/jobs`)) return response({
      jobs: jobPages ? jobPages[Number(url.searchParams.get('page')) - 1] ?? [] : jobs,
      total_count: jobPages ? jobPages.flat().length : jobs?.length ?? 0,
    });
    if (url.pathname.endsWith('/workflows/deploy-vps.yml/runs')) return response({
      workflow_runs: activeRuns.filter((candidate) => candidate.status === url.searchParams.get('status')),
      total_count: activeRuns.filter((candidate) => candidate.status === url.searchParams.get('status')).length,
    });
    if (url.pathname.endsWith('/jobs/123/logs')) return response(log);
    if (url.pathname.includes('/compare/')) return response(compare);
    throw new Error('Unexpected test API request');
  };
  return { fetcher, calls, now: () => now };
}

test('empty recovery input is a no-op that writes only the disabled output', async () => {
  const writes = [];
  const messages = [];
  const result = await runCutoverRecovery({ GITHUB_OUTPUT: 'output-file' }, {
    fetcher: async () => { throw new Error('No API request expected'); },
    append: (...args) => writes.push(args),
    stdout: (value) => messages.push(value),
  });
  assert.deepEqual(result, { allowed: false });
  assert.deepEqual(writes, [['output-file', 'cutover-recovery-allowed=false\n']]);
  assert.deepEqual(messages, ['Cutover recovery was not requested.\n']);
  assert.deepEqual(validateCutoverRecoveryConfig({ RECOVER_CUTOVER_RUN_ID: '' }), { requested: false });
});

test('accepts only the precise original failure and returns public lock metadata', async () => {
  const api = fakeApi();
  assert.deepEqual(await verifyCutoverRecovery(config, api), {
    allowed: true,
    failedSha: oldSha,
    failedStart: String(Date.parse(start) / 1000),
    failedEnd: String(Date.parse(end) / 1000),
    failedRunId: String(runId),
  });
  assert.equal(api.calls.length, 11);
  assert.match(api.calls[1].url.pathname, /\/attempts\/1\/jobs$/);
  assert.match(api.calls[5].url.pathname, /\/jobs\/123\/logs$/);
  for (const { options } of api.calls) {
    assert.equal(options.redirect, 'manual');
    assert.equal(options.credentials, 'omit');
    assert.equal(options.headers.Authorization, 'Bearer test-token');
  }
});

test('allows identical commits only when the compare evidence agrees', async () => {
  const api = fakeApi({ compare: comparison({ status: 'identical', ahead_by: 0 }) });
  assert.equal((await verifyCutoverRecovery({ ...config, sha: oldSha }, api)).allowed, true);
  await assert.rejects(verifyCutoverRecovery(config, api), /not an ancestor/);
});

test('accepts workflow suffixes bound to the failed develop run', async () => {
  for (const suffix of ['@develop', '@refs/heads/develop', `@${oldSha}`]) {
    assert.equal((await verifyCutoverRecovery(config, fakeApi({
      metadata: run({ path: `.github/workflows/deploy-vps.yml${suffix}` }),
    }))).allowed, true);
  }
});

test('requested recovery validates IDs, development branch, source SHA and credentials', () => {
  for (const changes of [
    { RECOVER_CUTOVER_RUN_ID: '0' },
    { RECOVER_CUTOVER_RUN_ID: '-1' },
    { RECOVER_CUTOVER_RUN_ID: '01' },
    { RECOVER_CUTOVER_RUN_ID: '1.1' },
    { RECOVER_CUTOVER_RUN_ID: '9007199254740993' },
    { RECOVER_CUTOVER_RUN_ID: runId },
    { GITHUB_RUN_ID: String(runId) },
    { GITHUB_RUN_ID: undefined },
    { SOURCE_BRANCH: 'main' },
    { SOURCE_BRANCH: 'feature/test' },
    { SOURCE_SHA: 'short' },
    { GH_TOKEN: '' },
    { GH_TOKEN: '   ' },
    { GH_TOKEN: 'token\nsecret' },
    { GITHUB_REPOSITORY: 'other/path/extra' },
    { GITHUB_REPOSITORY: 'owner/..' },
  ]) assert.throws(() => validateCutoverRecoveryConfig({ ...env, ...changes }));
});

test('rejects wrong or incomplete source run provenance and nonoriginal attempts', async () => {
  for (const changes of [
    { id: runId + 1 },
    { id: String(runId) },
    { repository: { full_name: 'other/repo' } },
    { head_repository: { full_name: 'other/repo' } },
    { head_repository: null },
    { head_branch: 'main' },
    { head_sha: 'short' },
    { path: '.github/workflows/ci.yml' },
    { path: '.github/workflows/deploy-vps.yml@main' },
    { path: `.github/workflows/deploy-vps.yml@${newSha}` },
    { event: 'push' },
    { event: 'workflow_run' },
    { event: 'pull_request' },
    { status: 'in_progress' },
    { conclusion: 'cancelled' },
    { conclusion: 'success' },
    { run_attempt: 2 },
    { run_attempt: undefined },
  ]) {
    const api = fakeApi({ metadata: run(changes) });
    await assert.rejects(verifyCutoverRecovery(config, api));
    assert.equal(api.calls.length, 1);
  }
});

test('binds every job to the failed run and rejects incomplete or canceled jobs', async () => {
  for (const changes of [
    { id: 0 },
    { id: '123' },
    { run_id: runId + 1 },
    { head_sha: newSha },
    { head_sha: undefined },
    { head_branch: 'main' },
    { run_attempt: 2 },
    { status: 'in_progress' },
    { conclusion: 'cancelled' },
  ]) await assert.rejects(verifyCutoverRecovery(config, fakeApi({ jobs: [job(changes), guard()] })), /provenance or status/);
});

test('requires exactly one failed deployment job and the unique successful source guard', async () => {
  for (const jobs of [
    [],
    [job()],
    [job(), guard({ conclusion: 'failure' })],
    [job(), guard({ conclusion: 'skipped' })],
    [job(), guard(), guard({ id: 125 })],
    [job(), job({ id: 125 }), guard()],
    [job({ name: 'Another job' }), guard()],
    [job({ conclusion: 'success' }), guard()],
    [job(), guard({ id: 123 })],
  ]) await assert.rejects(verifyCutoverRecovery(config, fakeApi({ jobs })));
});

test('requires Ansible to be the unique failed step', async () => {
  for (const steps of [
    undefined,
    [],
    [step({ name: 'Install Ansible' })],
    [step({ conclusion: 'success' })],
    [step({ status: 'in_progress' })],
    [step(), step()],
    [step(), step({ name: 'Other step' })],
    [step(), step({ name: 'Post cleanup', conclusion: 'cancelled' })],
    [step(), step({ name: 'Post cleanup', conclusion: 'success', status: 'in_progress' })],
    [step({ name: 'Configure SSH', conclusion: 'skipped' }), step()],
  ]) await assert.rejects(verifyCutoverRecovery(config, fakeApi({ jobs: [job({ steps }), guard()] })));
});

test('paginates the complete original attempt and rejects incomplete or inconsistent job counts', async () => {
  const firstPage = Array.from({ length: 100 }, (_, index) => guard({
    id: 1000 + index,
    name: `Unrelated ${index}`,
    conclusion: 'skipped',
  }));
  const api = fakeApi({ jobPages: [firstPage, [job(), guard()]] });
  assert.equal((await verifyCutoverRecovery(config, api)).allowed, true);
  assert.equal(api.calls.filter(({ url }) => url.pathname.endsWith('/jobs')).length, 2);
  for (const body of [
    { jobs: [job(), guard()], total_count: 3 },
    { jobs: [job(), guard()], total_count: -1 },
    { jobs: [job(), guard()] },
    { jobs: firstPage, total_count: 1001 },
  ]) await assert.rejects(verifyCutoverRecovery(config, fakeApi({
    intercept: async (url) => url.pathname.endsWith('/jobs') ? response(body) : undefined,
  })), /jobs response/);
  await assert.rejects(verifyCutoverRecovery(config, fakeApi({
    intercept: async (url) => url.pathname.endsWith('/jobs') ? response({
      jobs: Number(url.searchParams.get('page')) === 1 ? firstPage : [job(), guard()],
      total_count: Number(url.searchParams.get('page')) === 1 ? 102 : 103,
    }) : undefined,
  })), /jobs response/);
});

test('rechecks latest source metadata before authorizing to reject a concurrent rerun', async () => {
  let lookups = 0;
  const api = fakeApi({ intercept: async (url) => {
    if (url.pathname.endsWith(`/runs/${runId}`)) {
      lookups += 1;
      return response(run(lookups === 1 ? {} : { run_attempt: 2, status: 'in_progress' }));
    }
  } });
  await assert.rejects(verifyCutoverRecovery(config, api), /failed manual deployment|original deployment attempt/);
  assert.equal(lookups, 2);
});

test('refuses another development controller in progress, waiting or pending before reading logs', async () => {
  for (const status of ['in_progress', 'waiting', 'pending']) {
    const api = fakeApi({ activeRuns: [run({ id: runId + 2, status, conclusion: null })] });
    await assert.rejects(verifyCutoverRecovery(config, api), /Another development deployment/);
    assert.equal(api.calls.some(({ url }) => url.pathname.endsWith('/logs')), false);
    const statusCalls = api.calls.filter(({ url }) => url.pathname.endsWith('/workflows/deploy-vps.yml/runs'));
    assert.equal(statusCalls.length, 3);
    assert.equal(statusCalls.every(({ url }) => url.searchParams.get('branch') === 'develop'), true);
  }
});

test('allows the current development controller and leaves queued development or production runs out of scope', async () => {
  const api = fakeApi({ activeRuns: [
    run({ id: runId + 1, status: 'in_progress', conclusion: null }),
    run({ id: runId + 2, status: 'queued', conclusion: null }),
    run({ id: runId + 3, head_branch: 'main', status: 'in_progress', conclusion: null }),
  ] });
  assert.equal((await verifyCutoverRecovery(config, api)).allowed, true);
  assert.equal(api.calls.filter(({ url }) => url.pathname.endsWith('/workflows/deploy-vps.yml/runs')).length, 6);
});

test('the second controller check prevents authorization when another run becomes active', async () => {
  let progressChecks = 0;
  const writes = [];
  const api = fakeApi({ intercept: async (url) => {
    if (url.pathname.endsWith('/workflows/deploy-vps.yml/runs') && url.searchParams.get('status') === 'in_progress') {
      progressChecks += 1;
      if (progressChecks === 2) return response({
        workflow_runs: [run({ id: runId + 2, status: 'in_progress', conclusion: null })],
        total_count: 1,
      });
    }
  } });
  await assert.rejects(runCutoverRecovery(env, { ...api, append: (...args) => writes.push(args) }), /Another development deployment/);
  assert.equal(progressChecks, 2);
  assert.deepEqual(writes, []);
});

test('controller checks paginate completely and refuse ambiguous metadata', async () => {
  const mainRuns = Array.from({ length: 100 }, (_, index) => run({
    id: runId + 100 + index,
    head_branch: 'main',
    status: 'waiting',
    conclusion: null,
  }));
  const api = fakeApi({ intercept: async (url) => {
    if (url.pathname.endsWith('/workflows/deploy-vps.yml/runs') && url.searchParams.get('status') === 'waiting') return response({
      workflow_runs: Number(url.searchParams.get('page')) === 1 ? mainRuns : [run({ id: runId + 500, status: 'waiting', conclusion: null })],
      total_count: 101,
    });
  } });
  await assert.rejects(verifyCutoverRecovery(config, api), /Another development deployment/);
  for (const body of [
    { workflow_runs: [], total_count: 1 },
    { workflow_runs: [], total_count: 1001 },
    { workflow_runs: [run({ id: runId + 1, status: 'in_progress', head_repository: null })], total_count: 1 },
    { workflow_runs: [run({ id: runId + 1, status: 'in_progress' }), run({ id: runId + 1, status: 'in_progress' })], total_count: 2 },
  ]) await assert.rejects(verifyCutoverRecovery(config, fakeApi({
    intercept: async (url) => url.pathname.endsWith('/workflows/deploy-vps.yml/runs') && url.searchParams.get('status') === 'in_progress' ? response(body) : undefined,
  })));
});

test('validates timestamps, rejects stale windows and prevents future or misplaced steps', async () => {
  for (const changes of [
    { started_at: undefined },
    { started_at: '2026-02-30T18:00:00Z' },
    { started_at: '2026-10-12T18:00:00+00:00' },
    { completed_at: 'invalid' },
    { started_at: '2026-10-11T19:59:59Z' },
    { completed_at: '2026-10-12T20:00:01Z' },
    { started_at: '2026-10-12T18:15:00Z' },
    { steps: [step({ started_at: '2026-10-12T17:59:59Z' })] },
    { steps: [step({ completed_at: '2026-10-12T18:14:01Z' })] },
    { steps: [step({ completed_at: '2026-10-12T18:00:01Z' })] },
  ]) await assert.rejects(verifyCutoverRecovery(config, fakeApi({ jobs: [job(changes), guard()] })), /window/);
  assert.equal((await verifyCutoverRecovery(config, fakeApi({
    jobs: [job({ started_at: '2026-10-11T20:00:00Z' }), guard()],
  }))).allowed, true);
});

test('precise failure proof also accepts timestamp-free or ANSI-colored job logs', () => {
  verifyCutoverFailureLog(logs().replace(/^\d{4}-[^ ]+ /gm, ''));
  verifyCutoverFailureLog(logs().replace(`TASK [${task}]`, `\u001b[0;31mTASK [${task}]`));
});

test('failure proof rejects any mismatched helper failure or incomplete recap', () => {
  for (const changes of [
    { rc: 0 },
    { rc: 2 },
    { rc: '1' },
    { changed: true },
    { changed_when_result: undefined },
    { msg: 'different failure' },
    { stderr: `${message}\nother error` },
    { stderr_lines: [] },
    { stdout: 'unrecognized output' },
    { stdout_lines: ['unrecognized output'] },
  ]) assert.throws(() => verifyCutoverFailureLog(logs(fatal(changes))));
  for (const malformed of [
    logs().replace(task, 'Other task'),
    `${logs()}\nTASK [${task}] ****`,
    logs().replace('fatal: [fireguard]', 'fatal: [other-host]'),
    logs().replace('FAILED! => {', 'FAILED! => malformed {'),
    logs().replace('PLAY RECAP', 'Other recap'),
    logs().replace('failed=1', 'failed=2'),
    logs().replace('unreachable=0', 'unreachable=1'),
    logs().replace('rescued=0', 'rescued=1'),
    logs().replace('ignored=0', 'ignored=1'),
    `fatal: [fireguard]: FAILED! => {}\n${logs()}`,
  ]) assert.throws(() => verifyCutoverFailureLog(malformed));
});

test('refuses every downstream snapshot, migration, recovery or rollout task, including skipped tasks', () => {
  for (const downstream of [
    'Stop every managed writer before the consistent deployment snapshot',
    'Upload the explicitly activated encrypted pre-migration snapshot',
    'Create deployment backup directory',
    'Back up auth database',
    'Back up main database',
    'Stop application service before schema changes',
    'Run auth migrations',
    'Run main migrations',
    'Recover pending Webhook reservations before restarting producers',
    'Start only the scheduler before restoring HTTP and queue producers',
    'Start application service',
    'Unknown later task',
  ]) assert.throws(() => verifyCutoverFailureLog(`${logs()}\nTASK [${downstream}] ******\nskipping: [fireguard]`), /before snapshots/);
});

test('requires an ancestor comparison tied to the exact failed source', async () => {
  for (const changes of [
    { status: 'behind' },
    { status: 'diverged' },
    { status: 'unknown' },
    { base_commit: { sha: newSha } },
    { merge_base_commit: { sha: newSha } },
    { behind_by: 1 },
    { ahead_by: 0 },
    { ahead_by: undefined },
    { ahead_by: 1.5 },
    { status: 'identical', ahead_by: 0 },
  ]) await assert.rejects(verifyCutoverRecovery(config, fakeApi({ compare: comparison(changes) })), /not an ancestor/);
});

test('downloads only failed job logs and strips credentials on signed storage redirect', async () => {
  const download = 'https://productionresultssa1.blob.core.windows.net/actions-results/signed?sig=private';
  const api = fakeApi({ intercept: async (url) => {
    if (url.pathname.endsWith('/jobs/123/logs')) return response(null, {
      ok: false,
      status: 302,
      headers: { get: (name) => name === 'location' ? download : null },
    });
    if (url.href === download) return response(logs());
  } });
  assert.equal((await verifyCutoverRecovery(config, api)).allowed, true);
  const storage = api.calls.find(({ url }) => url.href === download);
  assert.deepEqual(storage.options.headers, { Accept: 'text/plain' });
  assert.equal(storage.options.redirect, 'manual');
  assert.equal(api.calls.filter(({ url }) => url.pathname.endsWith('/logs')).length, 1);
});

test('rejects insecure redirects, credential leaks and chained redirects', async () => {
  for (const location of [
    undefined,
    'not-a-url',
    'http://productionresultssa1.blob.core.windows.net/logs',
    'https://attacker.example/logs',
    'https://evil.actions.githubusercontent.com.attacker.example/logs',
    'https://user:password@productionresultssa1.blob.core.windows.net/logs',
    'https://productionresultssa1.blob.core.windows.net:444/logs',
    'https://productionresultssa1.blob.core.windows.net/logs#fragment',
    'https://productionresultssa1.blob.core.windows.net/logs?token=test-token',
  ]) {
    const api = fakeApi({ intercept: async (url) => url.pathname.endsWith('/logs') ? response(null, {
      ok: false,
      status: 302,
      headers: { get: () => location },
    }) : undefined });
    await assert.rejects(verifyCutoverRecovery(config, api), /redirect/);
    assert.equal(api.calls.length, 6);
  }
  const api = fakeApi({ intercept: async (url) => {
    if (url.pathname.endsWith('/logs') || url.hostname.endsWith('.blob.core.windows.net')) return response(null, {
      ok: false,
      status: 302,
      headers: { get: () => 'https://productionresultssa1.blob.core.windows.net/download' },
    });
  } });
  await assert.rejects(verifyCutoverRecovery(config, api), /download failed/);
  assert.equal(api.calls.length, 7);
  assert.equal(api.calls[6].options.headers.Authorization, undefined);
});

test('API failures and malformed bodies fail closed without exposing their contents', async () => {
  for (const endpoint of ['/runs/', '/jobs', '/logs', '/compare/']) {
    const api = fakeApi({ intercept: async (url) => url.pathname.includes(endpoint) ? response(null, { ok: false, status: 403 }) : undefined });
    await assert.rejects(verifyCutoverRecovery(config, api), /request failed|download failed/);
  }
  await assert.rejects(verifyCutoverRecovery(config, {
    fetcher: async () => { throw new Error('https://secret.example/?token=test-token'); },
  }), { message: 'GitHub recovery evidence request failed' });
  await assert.rejects(verifyCutoverRecovery(config, {
    fetcher: async () => response(null, { json: async () => { throw new Error('private response'); } }),
  }), { message: 'Invalid GitHub recovery metadata response' });
  await assert.rejects(verifyCutoverRecovery(config, fakeApi({ jobs: null })), /jobs response/);
  await assert.rejects(verifyCutoverRecovery(config, fakeApi({ intercept: async (url) => url.pathname.endsWith('/logs') ? response(null, { text: async () => { throw new Error('private logs'); } }) : undefined })), /download failed/);
});

test('bounded logs cannot bypass proof checks or persist private contents', async () => {
  await assert.rejects(verifyCutoverRecovery(config, fakeApi({ log: 'x'.repeat(5 * 1024 * 1024 + 1) })), /verification limit/);
  await assert.rejects(verifyCutoverRecovery(config, fakeApi({ intercept: async (url) => url.pathname.endsWith('/logs') ? response(logs(), { headers: { get: () => '999999999' } }) : undefined })), /verification limit/);
  const writes = [];
  const messages = [];
  await runCutoverRecovery(env, {
    ...fakeApi(),
    append: (...args) => writes.push(args),
    stdout: (value) => messages.push(value),
  });
  assert.equal(writes.length, 1);
  assert.equal(writes[0][1], `cutover-recovery-allowed=true\ncutover-failed-sha=${oldSha}\ncutover-failed-start=${Date.parse(start) / 1000}\ncutover-failed-end=${Date.parse(end) / 1000}\ncutover-failed-run-id=${runId}\n`);
  assert.deepEqual(messages, ['Verified the original development cutover refusal for guarded recovery.\n']);
  assert.doesNotMatch(JSON.stringify({ writes, messages }), /test-token|private-path|private-project|stderr|TASK|http/);
});

test('missing output or refused recovery writes no authorization', async () => {
  const writes = [];
  await assert.rejects(runCutoverRecovery({ ...env, GITHUB_OUTPUT: '' }, { append: (...args) => writes.push(args) }), /GITHUB_OUTPUT/);
  await assert.rejects(runCutoverRecovery(env, { ...fakeApi({ log: 'unrecognized failure' }), append: (...args) => writes.push(args) }));
  assert.deepEqual(writes, []);
});
