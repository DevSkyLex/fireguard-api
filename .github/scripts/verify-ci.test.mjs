import assert from 'node:assert/strict';
import test from 'node:test';
import { validateCiConfig, verifyCi } from './verify-ci.mjs';

const sha = 'a'.repeat(40);
const repo = 'example/fireguard-sso-web';
const config = validateCiConfig({
  GH_TOKEN: 'test-token',
  GITHUB_REPOSITORY: repo,
  SOURCE_BRANCH: 'main',
  SOURCE_SHA: sha,
  SONAR_PROJECT_PREFIX: 'fireguard-web',
  SONAR_DEPLOYMENTS_READY: 'true',
});

function run(id, changes = {}) {
  return {
    id,
    event: 'push',
    head_branch: 'main',
    head_sha: sha,
    head_repository: { full_name: repo },
    repository: { full_name: repo },
    path: '.github/workflows/ci.yml',
    status: 'completed',
    conclusion: 'success',
    run_attempt: 1,
    run_started_at: new Date(2026, 0, id).toISOString(),
    ...changes,
  };
}

function job(changes = {}) {
  return {
    name: 'SonarQube Quality Gate (fireguard-web-main)',
    status: 'completed',
    conclusion: 'success',
    steps: ['Run SonarQube scan', 'Enforce SonarQube quality gate'].map((name) => ({
      name,
      status: 'completed',
      conclusion: 'success',
    })),
    ...changes,
  };
}

function fakeFetch(runPages, jobPages = [[job()]], tipSha = sha) {
  const calls = [];
  const fetcher = async (url) => {
    const target = new URL(url);
    calls.push(target);
    const page = Number(target.searchParams.get('page')) - 1;
    const isJobs = target.pathname.endsWith('/jobs');
    return {
      ok: true,
      json: async () =>
        target.pathname.includes('/git/ref/heads/')
          ? { object: { sha: tipSha } }
          : isJobs
            ? { jobs: jobPages[page] ?? [] }
            : { workflow_runs: runPages[page] ?? [] },
    };
  };
  return { fetcher, calls };
}

test('accepts the newest trusted CI run and exact successful Sonar steps', async () => {
  const api = fakeFetch([[run(1), run(2)]]);
  assert.deepEqual(await verifyCi(config, api), { runId: '2', runAttempt: 1 });
  assert.equal(api.calls[0].searchParams.get('branch'), 'main');
  assert.equal(api.calls[0].searchParams.get('head_sha'), sha);
  assert.match(api.calls[1].pathname, /\/runs\/2\/attempts\/1\/jobs$/);
});

test('accepts only CI workflow path suffixes bound to this branch or commit', async () => {
  await Promise.all(
    ['@main', '@refs/heads/main', `@${sha}`].map(async (suffix) => {
      const result = await verifyCi(
        config,
        fakeFetch([[run(2, { path: `.github/workflows/ci.yml${suffix}` })]]),
      );
      assert.equal(result.runId, '2');
    }),
  );
  await Promise.all(
    [
      '.github/workflows/ci.yml@develop',
      '.github/workflows/ci.yml@refs/heads/develop',
      `.github/workflows/ci.yml@${'b'.repeat(40)}`,
      '.github/workflows/ci.yml/evil@main',
    ].map((path) =>
      assert.rejects(verifyCi(config, fakeFetch([[run(2, { path })]])), /No trusted CI run/),
    ),
  );
});

test('a later failed or pending run blocks an older success', async () => {
  await Promise.all(
    [{ conclusion: 'failure' }, { status: 'in_progress', conclusion: null }].map((changes) =>
      assert.rejects(
        verifyCi(config, fakeFetch([[run(1), run(2, changes)]])),
        /Newest CI execution/,
      ),
    ),
  );
});

test('ignores pull requests and foreign repositories, then rejects no trusted run', async () => {
  await assert.rejects(
    verifyCi(
      config,
      fakeFetch([
        [
          run(1, { event: 'pull_request' }),
          run(2, { head_repository: { full_name: 'other/repo' } }),
        ],
      ]),
    ),
    /No trusted CI run/,
  );
});

test('incomplete matching run provenance cannot fall back to an older success', async () => {
  await assert.rejects(
    verifyCi(config, fakeFetch([[run(1), run(2, { head_repository: null })]])),
    /incomplete provenance/,
  );
});

test('requires latest rerun attempt and optional expected run ID', async () => {
  const latest = run(2, { run_attempt: 3 });
  const api = fakeFetch([[latest]]);
  assert.deepEqual(await verifyCi(config, api), { runId: '2', runAttempt: 3 });
  assert.match(api.calls[1].pathname, /\/attempts\/3\/jobs$/);
  await assert.rejects(
    verifyCi({ ...config, expectedRunId: '1' }, fakeFetch([[latest]])),
    /not the newest/,
  );
});

test('automatic deployment denies an obsolete commit even when its CI passed', async () => {
  const api = fakeFetch([[run(2)]], [[job()]], 'b'.repeat(40));
  await assert.rejects(
    verifyCi({ ...config, expectedRunId: '2' }, api),
    /no longer the current branch tip/,
  );
  assert.equal(api.calls.length, 1);
  assert.match(api.calls[0].pathname, /\/git\/ref\/heads\/main$/);
});

test('denies skipped, missing or duplicate scan and gate steps', async () => {
  const skippedScan = job().steps;
  skippedScan[0].conclusion = 'skipped';
  await Promise.all(
    [
      job().steps.slice(1),
      skippedScan,
      job().steps.slice(0, 1),
      [...job().steps, job().steps[1]],
    ].map((steps) =>
      assert.rejects(
        verifyCi(config, fakeFetch([[run(1)]], [[job({ steps })]])),
        /step did not complete/,
      ),
    ),
  );
});

test('paginates run and job results', async () => {
  const firstRuns = Array.from({ length: 100 }, (_, index) =>
    run(index + 1, { event: 'pull_request' }),
  );
  const firstJobs = Array.from({ length: 100 }, (_, index) => ({ name: `Unrelated ${index}` }));
  const api = fakeFetch([firstRuns, [run(101)]], [firstJobs, [job()]]);
  assert.equal((await verifyCi(config, api)).runId, '101');
  assert.equal(api.calls.length, 4);
});

test('invalid config and HTTP failures fail closed', async () => {
  assert.throws(
    () =>
      validateCiConfig({
        ...process.env,
        GH_TOKEN: 'x',
        GITHUB_REPOSITORY: repo,
        SOURCE_BRANCH: 'feature',
        SOURCE_SHA: sha,
        SONAR_PROJECT_PREFIX: 'fireguard-web',
        SONAR_DEPLOYMENTS_READY: 'true',
      }),
    /branch/,
  );
  assert.throws(
    () =>
      validateCiConfig({
        ...process.env,
        GH_TOKEN: 'x',
        GITHUB_REPOSITORY: repo,
        SOURCE_BRANCH: 'main',
        SOURCE_SHA: 'short',
        SONAR_PROJECT_PREFIX: 'fireguard-web',
        SONAR_DEPLOYMENTS_READY: 'true',
      }),
    /SHA/,
  );
  await assert.rejects(
    verifyCi(config, { fetcher: async () => ({ ok: false, status: 403 }) }),
    /GitHub API request failed/,
  );
});
