import { appendFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const SHA = /^[0-9a-f]{40}$/i;
const REPOSITORY = /^[A-Za-z0-9][A-Za-z0-9-]*\/[A-Za-z0-9_.-]+$/;
const PAGE_SIZE = 100;

function trustedWorkflowPath(path, branch, sha) {
  const base = '.github/workflows/ci.yml';
  return [base, `${base}@${branch}`, `${base}@refs/heads/${branch}`, `${base}@${sha}`].includes(
    path,
  );
}

export function validateCiConfig(env) {
  const {
    GH_TOKEN,
    GITHUB_REPOSITORY,
    SOURCE_BRANCH,
    SOURCE_SHA,
    SONAR_PROJECT_PREFIX,
    EXPECTED_RUN_ID,
    SONAR_DEPLOYMENTS_READY,
  } = env;
  if (!GH_TOKEN || !REPOSITORY.test(GITHUB_REPOSITORY ?? ''))
    throw new Error('GitHub token and repository are required');
  if (!['main', 'develop'].includes(SOURCE_BRANCH)) throw new Error('Unsupported source branch');
  if (!SHA.test(SOURCE_SHA ?? '')) throw new Error('Source SHA must be a full commit SHA');
  if (!['fireguard-web', 'fireguard-api'].includes(SONAR_PROJECT_PREFIX))
    throw new Error('Unsupported Sonar project prefix');
  if (SONAR_DEPLOYMENTS_READY !== 'true') throw new Error('Sonar deployment gate is not ready');
  if (
    EXPECTED_RUN_ID !== undefined &&
    EXPECTED_RUN_ID !== '' &&
    !/^[1-9]\d*$/.test(EXPECTED_RUN_ID)
  )
    throw new Error('Invalid expected run ID');
  return {
    token: GH_TOKEN,
    repository: GITHUB_REPOSITORY,
    branch: SOURCE_BRANCH,
    sha: SOURCE_SHA.toLowerCase(),
    prefix: SONAR_PROJECT_PREFIX,
    expectedRunId: EXPECTED_RUN_ID || undefined,
  };
}

async function getJson(fetcher, url, token) {
  const response = await fetcher(url, {
    signal: AbortSignal.timeout(30_000),
    headers: {
      Accept: 'application/vnd.github+json',
      Authorization: `Bearer ${token}`,
      'X-GitHub-Api-Version': '2022-11-28',
    },
  });
  if (!response?.ok)
    throw new Error(`GitHub API request failed (${response?.status ?? 'unknown'})`);
  return response.json();
}

async function allPages(fetcher, baseUrl, token, key) {
  const items = [];
  async function nextPage(page) {
    if (page > 1000) throw new Error('GitHub API pagination exceeded limit');
    const url = new URL(baseUrl);
    url.searchParams.set('per_page', String(PAGE_SIZE));
    url.searchParams.set('page', String(page));
    const body = await getJson(fetcher, url, token);
    if (!body || !Array.isArray(body[key])) throw new Error(`Invalid GitHub API ${key} response`);
    items.push(...body[key]);
    return body[key].length < PAGE_SIZE ? items : nextPage(page + 1);
  }
  return nextPage(1);
}

function trustedRun(run, config) {
  if (!run || !['push', 'workflow_dispatch'].includes(run.event)) return false;
  if (run.head_branch !== config.branch || run.head_sha?.toLowerCase() !== config.sha) return false;
  const expectedRepo = config.repository.toLowerCase();
  if (
    !run.head_repository?.full_name ||
    !run.repository?.full_name ||
    !run.path ||
    !Number.isSafeInteger(run.id) ||
    run.id < 1
  )
    throw new Error('CI run has incomplete provenance');
  return (
    run.head_repository?.full_name?.toLowerCase() === expectedRepo &&
    run.repository?.full_name?.toLowerCase() === expectedRepo &&
    trustedWorkflowPath(run.path, config.branch, run.head_sha)
  );
}

function runOrder(run) {
  const date = Date.parse(run.run_started_at ?? run.created_at);
  if (!Number.isFinite(date)) throw new Error('CI run has invalid execution date');
  return date;
}

export async function verifyCi(
  config,
  { fetcher = fetch, apiBase = 'https://api.github.com' } = {},
) {
  const encodedRepo = config.repository.split('/').map(encodeURIComponent).join('/');
  if (config.expectedRunId) {
    const ref = await getJson(
      fetcher,
      `${apiBase}/repos/${encodedRepo}/git/ref/heads/${encodeURIComponent(config.branch)}`,
      config.token,
    );
    if (!SHA.test(ref?.object?.sha ?? '') || ref.object.sha.toLowerCase() !== config.sha)
      throw new Error('Source SHA is no longer the current branch tip');
  }
  const base = `${apiBase}/repos/${encodedRepo}/actions/workflows/ci.yml/runs`;
  const runUrl = new URL(base);
  runUrl.searchParams.set('branch', config.branch);
  runUrl.searchParams.set('head_sha', config.sha);
  const runs = await allPages(fetcher, runUrl, config.token, 'workflow_runs');
  const candidates = runs.filter((run) => trustedRun(run, config));
  if (candidates.length === 0) throw new Error('No trusted CI run found for source commit');
  candidates.sort((a, b) => runOrder(b) - runOrder(a) || b.id - a.id);
  const latest = candidates[0];
  if (config.expectedRunId && String(latest.id) !== config.expectedRunId)
    throw new Error('Expected run is not the newest CI execution');
  if (latest.status !== 'completed' || latest.conclusion !== 'success')
    throw new Error('Newest CI execution did not complete successfully');
  if (!Number.isSafeInteger(latest.run_attempt) || latest.run_attempt < 1)
    throw new Error('Invalid CI run attempt');

  const jobs = await allPages(
    fetcher,
    `${apiBase}/repos/${encodedRepo}/actions/runs/${latest.id}/attempts/${latest.run_attempt}/jobs`,
    config.token,
    'jobs',
  );
  const expectedName = `SonarQube Quality Gate (${config.prefix}-${config.branch})`;
  const gates = jobs.filter((job) => job?.name === expectedName);
  if (gates.length !== 1) throw new Error('Expected SonarQube job is missing or duplicated');
  const gate = gates[0];
  if (gate.status !== 'completed' || gate.conclusion !== 'success' || !Array.isArray(gate.steps))
    throw new Error('SonarQube job did not complete successfully');
  for (const name of ['Run SonarQube scan', 'Enforce SonarQube quality gate']) {
    const steps = gate.steps.filter((step) => step?.name === name);
    if (steps.length !== 1 || steps[0].status !== 'completed' || steps[0].conclusion !== 'success')
      throw new Error(`${name} step did not complete successfully`);
  }
  return { runId: String(latest.id), runAttempt: latest.run_attempt };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const result = await verifyCi(validateCiConfig(process.env));
    if (!process.env.GITHUB_OUTPUT) throw new Error('GITHUB_OUTPUT is required');
    appendFileSync(process.env.GITHUB_OUTPUT, `verified-run-id=${result.runId}\n`);
    if (process.env.GITHUB_STEP_SUMMARY)
      appendFileSync(
        process.env.GITHUB_STEP_SUMMARY,
        `Verified CI run ${result.runId}, attempt ${result.runAttempt}, and its SonarQube quality gate.\n`,
      );
    process.stdout.write(`Verified CI run ${result.runId}, attempt ${result.runAttempt}\n`);
  } catch (error) {
    process.stderr.write(`${error instanceof Error ? error.message : 'CI verification failed'}\n`);
    process.exitCode = 1;
  }
}
