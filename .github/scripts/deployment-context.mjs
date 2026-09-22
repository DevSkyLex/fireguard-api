import { appendFileSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const SHA = /^[0-9a-f]{40}$/i;
const REPOSITORY = /^[A-Za-z0-9][A-Za-z0-9-]*\/[A-Za-z0-9_.-]+$/;

function trustedWorkflowPath(path, branch, sha) {
  const base = '.github/workflows/ci.yml';
  return [base, `${base}@${branch}`, `${base}@refs/heads/${branch}`, `${base}@${sha}`].includes(
    path,
  );
}

export function isDocumentationOnly(files) {
  return (
    Array.isArray(files) &&
    files.length > 0 &&
    files.every(
      (file) => typeof file === 'string' && (file.endsWith('.md') || file.startsWith('docs/')),
    )
  );
}

export function deploymentContext(eventName, event, env) {
  const repository = env.GITHUB_REPOSITORY;
  if (
    !REPOSITORY.test(repository ?? '') ||
    event?.repository?.full_name?.toLowerCase() !== repository.toLowerCase()
  ) {
    throw new Error('Deployment event repository does not match GitHub repository');
  }
  let branch;
  let sha;
  let runId = '';
  if (eventName === 'workflow_run') {
    const run = event.workflow_run;
    if (
      event.action !== 'completed' ||
      run?.event !== 'push' ||
      run.status !== 'completed' ||
      run.conclusion !== 'success'
    ) {
      throw new Error('Only successful push CI completions may publish automatically');
    }
    if (
      run.head_repository?.full_name?.toLowerCase() !== repository.toLowerCase() ||
      run.repository?.full_name?.toLowerCase() !== repository.toLowerCase()
    ) {
      throw new Error('CI run source repository does not match');
    }
    if (
      !trustedWorkflowPath(run.path, run.head_branch, run.head_sha) ||
      !Number.isSafeInteger(run.id) ||
      run.id < 1
    )
      throw new Error('Invalid CI run provenance');
    branch = run.head_branch;
    sha = run.head_sha;
    runId = String(run.id);
  } else if (eventName === 'workflow_dispatch') {
    const ref = env.GITHUB_REF;
    if (!ref?.startsWith('refs/heads/')) throw new Error('Manual deployment ref is not a branch');
    branch = ref.slice('refs/heads/'.length);
    sha = env.GITHUB_SHA;
    if (event.ref && event.ref !== ref && event.ref !== branch)
      throw new Error('Manual deployment ref does not match event');
    if (event.after && event.after.toLowerCase() !== sha?.toLowerCase())
      throw new Error('Manual deployment SHA does not match event');
  } else {
    throw new Error('Unsupported deployment event');
  }
  if (!['main', 'develop'].includes(branch)) throw new Error('Unsupported deployment branch');
  if (!SHA.test(sha ?? '')) throw new Error('Deployment source SHA must be a full commit SHA');
  return {
    sourceBranch: branch,
    sourceSha: sha.toLowerCase(),
    runId,
    deployEnvironment: branch === 'main' ? 'production' : 'development',
  };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    if (!process.env.GITHUB_EVENT_PATH || !process.env.GITHUB_EVENT_NAME)
      throw new Error('GitHub event path and name are required');
    const event = JSON.parse(readFileSync(process.env.GITHUB_EVENT_PATH, 'utf8'));
    const result = deploymentContext(process.env.GITHUB_EVENT_NAME, event, process.env);
    if (!process.env.GITHUB_OUTPUT) throw new Error('GITHUB_OUTPUT is required');
    appendFileSync(
      process.env.GITHUB_OUTPUT,
      `source-branch=${result.sourceBranch}\nsource-sha=${result.sourceSha}\nrun-id=${result.runId}\ndeploy-environment=${result.deployEnvironment}\n`,
    );
    if (process.env.GITHUB_STEP_SUMMARY)
      appendFileSync(
        process.env.GITHUB_STEP_SUMMARY,
        `Deployment source: ${result.sourceBranch} at ${result.sourceSha}${result.runId ? ` (CI run ${result.runId})` : ' (manual)'}.\n`,
      );
    process.stdout.write(`Deployment source ${result.sourceBranch} at ${result.sourceSha}\n`);
  } catch (error) {
    process.stderr.write(
      `${error instanceof Error ? error.message : 'Deployment context failed'}\n`,
    );
    process.exitCode = 1;
  }
}
