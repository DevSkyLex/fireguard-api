import { appendFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const SHA = /^[0-9a-f]{40}$/i;
const RUN_ID = /^[1-9]\d*$/;
const REPOSITORY = /^[A-Za-z0-9][A-Za-z0-9-]*\/[A-Za-z0-9_.-]+$/;
const PAGE_SIZE = 100;
const API_BASE = 'https://api.github.com';
const MAX_LOG_BYTES = 5 * 1024 * 1024;
const MAX_AGE_MS = 24 * 60 * 60 * 1000;
const FAILED_TASK = 'Verify the effective auth Webhook queue before snapshots or schema changes';
const FAILED_MESSAGE =
  'The installed Webhook transport is unsupported; cutover was refused without discarding its work.';
const DEPLOY_JOB = 'Deploy to VPS';
const GUARD_JOB = 'Verify source CI and image provenance';
const FAILED_STEP = 'Run Ansible deployment';

function validId(value) {
  return typeof value === 'string' && RUN_ID.test(value) && Number.isSafeInteger(Number(value));
}

export function validateCutoverRecoveryConfig(env) {
  const runId = env.RECOVER_CUTOVER_RUN_ID;
  if (runId === undefined || runId === '') return { requested: false };
  if (!validId(runId)) throw new Error('Invalid failed deployment run ID');
  if (!validId(env.GITHUB_RUN_ID) || runId === env.GITHUB_RUN_ID)
    throw new Error('Recovery must refer to a different deployment run');
  if (env.SOURCE_BRANCH !== 'develop') throw new Error('Cutover recovery is restricted to develop');
  if (!SHA.test(env.SOURCE_SHA ?? '')) throw new Error('Source SHA must be a full commit SHA');
  if (
    typeof env.GH_TOKEN !== 'string' ||
    !env.GH_TOKEN.trim() ||
    /[\r\n]/.test(env.GH_TOKEN) ||
    !REPOSITORY.test(env.GITHUB_REPOSITORY ?? '') ||
    ['.', '..'].includes(env.GITHUB_REPOSITORY.split('/')[1])
  )
    throw new Error('GitHub token and repository are required');
  return {
    requested: true,
    runId,
    currentRunId: env.GITHUB_RUN_ID,
    branch: env.SOURCE_BRANCH,
    sha: env.SOURCE_SHA.toLowerCase(),
    repository: env.GITHUB_REPOSITORY,
    token: env.GH_TOKEN,
  };
}

async function request(fetcher, url, token, accept = 'application/vnd.github+json') {
  try {
    return await fetcher(url, {
      redirect: 'manual',
      credentials: 'omit',
      signal: AbortSignal.timeout(30_000),
      headers: {
        Accept: accept,
        ...(token ? { Authorization: `Bearer ${token}`, 'X-GitHub-Api-Version': '2022-11-28' } : {}),
      },
    });
  } catch {
    throw new Error('GitHub recovery evidence request failed');
  }
}

async function getJson(fetcher, url, token) {
  const response = await request(fetcher, url, token);
  if (!response?.ok) throw new Error('GitHub recovery metadata request failed');
  try {
    return await response.json();
  } catch {
    throw new Error('Invalid GitHub recovery metadata response');
  }
}

async function allPages(fetcher, baseUrl, token, key) {
  const items = [];
  let total;
  for (let page = 1; page <= 10; page += 1) {
    const url = new URL(baseUrl);
    url.searchParams.set('per_page', String(PAGE_SIZE));
    url.searchParams.set('page', String(page));
    const body = await getJson(fetcher, url, token);
    if (
      !Array.isArray(body?.[key]) ||
      body[key].length > PAGE_SIZE ||
      !Number.isSafeInteger(body.total_count) ||
      body.total_count < 0 ||
      body.total_count > 1000 ||
      (total !== undefined && total !== body.total_count)
    )
      throw new Error(`Invalid GitHub recovery ${key} response`);
    total = body.total_count;
    items.push(...body[key]);
    if (items.length === total) return items;
    if (body[key].length < PAGE_SIZE || items.length > total) {
      throw new Error(`GitHub recovery ${key} response is incomplete`);
    }
  }
  throw new Error(`GitHub recovery ${key} pagination exceeded limit`);
}

async function requireNoOtherActiveDeployment(fetcher, base, config) {
  const results = await Promise.allSettled(
    ['in_progress', 'waiting', 'pending'].map(async (status) => {
      const url = new URL(`${base}/actions/workflows/deploy-vps.yml/runs`);
      url.searchParams.set('branch', 'develop');
      url.searchParams.set('status', status);
      const runs = await allPages(fetcher, url, config.token, 'workflow_runs');
      if (new Set(runs.map((run) => run?.id)).size !== runs.length)
        throw new Error('Active development deployment metadata is incomplete');
      for (const run of runs) {
        if (run?.head_branch === 'main') continue;
        if (
          !Number.isSafeInteger(run?.id) ||
          run.id < 1 ||
          run.head_branch !== 'develop' ||
          run.status !== status ||
          run.repository?.full_name?.toLowerCase() !== config.repository.toLowerCase() ||
          run.head_repository?.full_name?.toLowerCase() !== config.repository.toLowerCase()
        )
          throw new Error('Active development deployment metadata is incomplete');
        if (String(run.id) !== config.currentRunId)
          throw new Error('Another development deployment is active; recovery was refused');
      }
    }),
  );
  const failure = results.find((result) => result.status === 'rejected');
  if (failure) throw failure.reason;
}

function trustedRun(run, config) {
  const expectedRepo = config.repository.toLowerCase();
  const workflow = '.github/workflows/deploy-vps.yml';
  if (
    !run ||
    !Number.isSafeInteger(run.id) ||
    String(run.id) !== config.runId ||
    run.repository?.full_name?.toLowerCase() !== expectedRepo ||
    run.head_repository?.full_name?.toLowerCase() !== expectedRepo ||
    run.head_branch !== 'develop' ||
    !SHA.test(run.head_sha ?? '') ||
    ![workflow, `${workflow}@develop`, `${workflow}@refs/heads/develop`, `${workflow}@${run.head_sha}`].includes(
      run.path,
    )
  )
    throw new Error('Failed deployment run has invalid provenance');
  if (run.event !== 'workflow_dispatch' || run.status !== 'completed' || run.conclusion !== 'failure')
    throw new Error('Recovery requires a completed failed manual deployment');
  if (run.run_attempt !== 1) throw new Error('Recovery requires the original deployment attempt');
  return run.head_sha.toLowerCase();
}

function jobBelongsToRun(job, run, sha) {
  return (
    Number.isSafeInteger(job?.id) &&
    job.id > 0 &&
    job.run_id === run.id &&
    job.head_sha?.toLowerCase() === sha &&
    job.head_branch === 'develop' &&
    (job.run_attempt === undefined || job.run_attempt === run.run_attempt)
  );
}

function timestamp(value) {
  if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?Z$/.test(value))
    throw new Error('Failed deployment has an invalid execution window');
  const milliseconds = Date.parse(value);
  const normalized = value.replace(/(?:\.(\d{1,3}))?Z$/, (_, fraction = '') => `.${fraction.padEnd(3, '0')}Z`);
  if (!Number.isFinite(milliseconds) || new Date(milliseconds).toISOString() !== normalized)
    throw new Error('Failed deployment has an invalid execution window');
  return milliseconds;
}

function failedJob(jobs, run, sha, now) {
  if (
    jobs.length === 0 ||
    jobs.some((job) => !jobBelongsToRun(job, run, sha) || job.status !== 'completed') ||
    new Set(jobs.map((job) => job.id)).size !== jobs.length ||
    jobs.some((job) => !['success', 'failure', 'skipped'].includes(job.conclusion))
  )
    throw new Error('Deployment jobs have invalid provenance or status');
  const failures = jobs.filter((job) => job.conclusion === 'failure');
  const deployments = jobs.filter((job) => job.name === DEPLOY_JOB);
  const guards = jobs.filter((job) => job.name === GUARD_JOB);
  if (
    failures.length !== 1 ||
    deployments.length !== 1 ||
    failures[0] !== deployments[0] ||
    guards.length !== 1 ||
    guards[0].conclusion !== 'success'
  )
    throw new Error('Recovery requires only the deployment job to fail after a successful source guard');
  const job = failures[0];
  if (!Array.isArray(job.steps)) throw new Error('Failed deployment steps are unavailable');
  const failedSteps = job.steps.filter((step) => step?.conclusion === 'failure');
  const deploymentsSteps = job.steps.filter((step) => step?.name === FAILED_STEP);
  if (
    failedSteps.length !== 1 ||
    deploymentsSteps.length !== 1 ||
    failedSteps[0] !== deploymentsSteps[0] ||
    failedSteps[0].status !== 'completed' ||
    job.steps.some((step) => step?.status !== 'completed' || !['success', 'failure', 'skipped'].includes(step.conclusion))
  )
    throw new Error('Recovery requires the Ansible deployment step to be the only failed step');
  const failedIndex = job.steps.indexOf(failedSteps[0]);
  if (job.steps.slice(0, failedIndex).some((previous) => previous.conclusion !== 'success'))
    throw new Error('Deployment steps preceding Ansible did not all succeed');
  const start = timestamp(job.started_at);
  const end = timestamp(job.completed_at);
  const stepStart = timestamp(failedSteps[0].started_at);
  const stepEnd = timestamp(failedSteps[0].completed_at);
  if (
    !Number.isFinite(now) ||
    start > end ||
    start < now - MAX_AGE_MS ||
    end > now ||
    stepStart < start ||
    stepStart > stepEnd ||
    stepEnd > end
  )
    throw new Error('Failed deployment execution window is stale or inconsistent');
  return { job, start, end };
}

async function jobLogs(fetcher, url, token) {
  let response = await request(fetcher, url, token);
  if (response?.status === 302) {
    const location = response.headers?.get('location');
    let download;
    try {
      download = new URL(location);
    } catch {
      throw new Error('Invalid recovery log download redirect');
    }
    if (
      download.protocol !== 'https:' ||
      download.username ||
      download.password ||
      download.hash ||
      (download.port && download.port !== '443') ||
      !/(^|\.)(blob\.core\.windows\.net|actions\.githubusercontent\.com)$/.test(download.hostname) ||
      location.includes(token)
    )
      throw new Error('Untrusted recovery log download redirect');
    // Signed log downloads never receive the GitHub token and cannot redirect again.
    response = await request(fetcher, download, undefined, 'text/plain');
  }
  if (!response?.ok) throw new Error('GitHub recovery log download failed');
  const contentLength = response.headers?.get('content-length');
  if (contentLength && (!/^\d+$/.test(contentLength) || Number(contentLength) > MAX_LOG_BYTES))
    throw new Error('Recovery log exceeds the verification limit');
  let logs;
  try {
    logs = await response.text();
  } catch {
    throw new Error('GitHub recovery log download failed');
  }
  if (typeof logs !== 'string' || Buffer.byteLength(logs) > MAX_LOG_BYTES)
    throw new Error('Recovery log exceeds the verification limit');
  return logs;
}

export function verifyCutoverFailureLog(logs) {
  const lines = logs.split(/\r?\n/).map((line) =>
    line
      .replace(/\u001b\[[0-?]*[ -/]*[@-~]/g, '')
      .replace(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z\s/, '')
      .trim(),
  );
  const taskLines = lines.flatMap((line, index) => (line.startsWith('TASK [') ? [{ line, index }] : []));
  const targets = taskLines.filter(({ line }) => new RegExp(`^TASK \\[${FAILED_TASK}\\] \\*+$`).test(line));
  if (targets.length !== 1 || taskLines.some(({ index }) => index > targets[0].index))
    throw new Error('Recovery log does not prove a failure before snapshots and schema changes');
  const fatalLines = lines.flatMap((line, index) => (line.startsWith('fatal:') ? [{ line, index }] : []));
  if (fatalLines.length !== 1 || fatalLines[0].index <= targets[0].index)
    throw new Error('Recovery log has an unrecognized Ansible failure');
  let failure;
  try {
    const match = /^fatal: \[fireguard\]: FAILED! => (.+)$/.exec(fatalLines[0].line);
    failure = JSON.parse(match?.[1] ?? '');
  } catch {
    throw new Error('Recovery log has an unrecognized Ansible failure');
  }
  if (
    failure?.rc !== 1 ||
    failure.changed !== false ||
    failure.changed_when_result !== false ||
    !['non-zero return code', 'The command exited with a non-zero return code.'].includes(failure.msg) ||
    failure.stderr !== FAILED_MESSAGE ||
    JSON.stringify(failure.stderr_lines) !== JSON.stringify([FAILED_MESSAGE]) ||
    failure.stdout !== '' ||
    JSON.stringify(failure.stdout_lines) !== '[]'
  )
    throw new Error('Recovery log does not prove the supported cutover refusal');
  const recaps = lines.flatMap((line, index) => (line.startsWith('PLAY RECAP ') ? [{ line, index }] : []));
  const summaries = lines.flatMap((line, index) =>
    /^fireguard\s+:\s+ok=\d+\s+changed=\d+\s+unreachable=0\s+failed=1\s+skipped=\d+\s+rescued=0\s+ignored=0$/.test(line)
      ? [{ index }]
      : [],
  );
  if (
    recaps.length !== 1 ||
    recaps[0].index <= fatalLines[0].index ||
    summaries.length !== 1 ||
    summaries[0].index <= recaps[0].index
  )
    throw new Error('Recovery log does not prove a single unrecovered deployment failure');
}

export async function verifyCutoverRecovery(
  config,
  { fetcher = fetch, now = () => Date.now() } = {},
) {
  if (!config.requested) return { allowed: false };
  const encodedRepo = config.repository.split('/').map(encodeURIComponent).join('/');
  const base = `${API_BASE}/repos/${encodedRepo}`;
  const run = await getJson(fetcher, `${base}/actions/runs/${config.runId}`, config.token);
  const sha = trustedRun(run, config);
  const jobs = await allPages(
    fetcher,
    `${base}/actions/runs/${config.runId}/attempts/1/jobs`,
    config.token,
    'jobs',
  );
  const { job, start, end } = failedJob(jobs, run, sha, now());
  await requireNoOtherActiveDeployment(fetcher, base, config);
  const logs = await jobLogs(fetcher, `${base}/actions/jobs/${job.id}/logs`, config.token);
  verifyCutoverFailureLog(logs);
  const comparison = await getJson(fetcher, `${base}/compare/${sha}...${config.sha}`, config.token);
  if (
    !['ahead', 'identical'].includes(comparison?.status) ||
    comparison.base_commit?.sha?.toLowerCase() !== sha ||
    comparison.merge_base_commit?.sha?.toLowerCase() !== sha ||
    comparison.behind_by !== 0 ||
    !Number.isSafeInteger(comparison.ahead_by) ||
    (comparison.status === 'ahead' && (comparison.ahead_by < 1 || config.sha === sha)) ||
    (comparison.status === 'identical' && (comparison.ahead_by !== 0 || config.sha !== sha))
  )
    throw new Error('Failed deployment source is not an ancestor of the verified recovery source');
  const latestRun = await getJson(fetcher, `${base}/actions/runs/${config.runId}`, config.token);
  if (trustedRun(latestRun, config) !== sha)
    throw new Error('Failed deployment source changed while verifying recovery');
  failedJob(jobs, latestRun, sha, now());
  await requireNoOtherActiveDeployment(fetcher, base, config);
  return {
    allowed: true,
    failedSha: sha,
    failedStart: String(Math.floor(start / 1000)),
    failedEnd: String(Math.floor(end / 1000)),
    failedRunId: config.runId,
  };
}

export async function runCutoverRecovery(
  env,
  { append = appendFileSync, stdout = (message) => process.stdout.write(message), ...options } = {},
) {
  const config = validateCutoverRecoveryConfig(env);
  if (!env.GITHUB_OUTPUT) throw new Error('GITHUB_OUTPUT is required');
  const result = await verifyCutoverRecovery(config, options);
  const output = result.allowed
    ? `cutover-recovery-allowed=true\ncutover-failed-sha=${result.failedSha}\ncutover-failed-start=${result.failedStart}\ncutover-failed-end=${result.failedEnd}\ncutover-failed-run-id=${result.failedRunId}\n`
    : 'cutover-recovery-allowed=false\n';
  append(env.GITHUB_OUTPUT, output);
  stdout(result.allowed ? 'Verified the original development cutover refusal for guarded recovery.\n' : 'Cutover recovery was not requested.\n');
  return result;
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    await runCutoverRecovery(process.env);
  } catch {
    process.stderr.write('Cutover recovery verification failed; the installation lock remains held.\n');
    process.exitCode = 1;
  }
}
