import assert from 'node:assert/strict';
import test from 'node:test';
import { deploymentContext, isDocumentationOnly } from './deployment-context.mjs';

const repo = 'example/fireguard-sso-web';
const sha = 'a'.repeat(40);
const env = { GITHUB_REPOSITORY: repo, GITHUB_REF: 'refs/heads/main', GITHUB_SHA: sha };
const workflowEvent = {
  action: 'completed',
  repository: { full_name: repo },
  workflow_run: {
    id: 42,
    event: 'push',
    status: 'completed',
    conclusion: 'success',
    head_branch: 'develop',
    head_sha: sha,
    path: '.github/workflows/ci.yml',
    head_repository: { full_name: repo },
    repository: { full_name: repo },
  },
};

test('automatic publication accepts only a successful same-repo push CI completion', () => {
  assert.deepEqual(deploymentContext('workflow_run', workflowEvent, env), {
    sourceBranch: 'develop',
    sourceSha: sha,
    runId: '42',
    deployEnvironment: 'development',
  });
  for (const change of [
    { event: 'pull_request' },
    { conclusion: 'failure' },
    { status: 'in_progress' },
    { head_branch: 'feature' },
    { head_sha: 'short' },
    { head_repository: { full_name: 'other/repo' } },
    { path: '.github/workflows/other.yml' },
  ]) {
    assert.throws(() =>
      deploymentContext(
        'workflow_run',
        { ...workflowEvent, workflow_run: { ...workflowEvent.workflow_run, ...change } },
        env,
      ),
    );
  }
  assert.throws(() =>
    deploymentContext('workflow_run', { ...workflowEvent, action: 'requested' }, env),
  );
});

test('workflow event path suffix must match the workflow branch or SHA', () => {
  for (const suffix of ['@develop', '@refs/heads/develop', `@${sha}`]) {
    assert.equal(
      deploymentContext(
        'workflow_run',
        {
          ...workflowEvent,
          workflow_run: {
            ...workflowEvent.workflow_run,
            path: `.github/workflows/ci.yml${suffix}`,
          },
        },
        env,
      ).runId,
      '42',
    );
  }
  for (const path of [
    '.github/workflows/ci.yml@main',
    '.github/workflows/ci.yml@refs/heads/main',
    `.github/workflows/ci.yml@${'b'.repeat(40)}`,
    '.github/workflows/ci.yml/evil@develop',
  ]) {
    assert.throws(
      () =>
        deploymentContext(
          'workflow_run',
          {
            ...workflowEvent,
            workflow_run: { ...workflowEvent.workflow_run, path },
          },
          env,
        ),
      /provenance/,
    );
  }
});

test('manual diagnostic uses current branch ref and SHA, with blank run ID', () => {
  assert.deepEqual(
    deploymentContext(
      'workflow_dispatch',
      { repository: { full_name: repo }, ref: 'refs/heads/main' },
      env,
    ),
    {
      sourceBranch: 'main',
      sourceSha: sha,
      runId: '',
      deployEnvironment: 'production',
    },
  );
  assert.throws(
    () =>
      deploymentContext(
        'workflow_dispatch',
        { repository: { full_name: repo }, ref: 'refs/heads/develop' },
        env,
      ),
    /ref/,
  );
  assert.throws(
    () =>
      deploymentContext(
        'workflow_dispatch',
        { repository: { full_name: repo }, ref: 'refs/heads/main' },
        { ...env, GITHUB_REF: 'refs/tags/v1' },
      ),
    /ref/,
  );
  assert.throws(
    () =>
      deploymentContext(
        'workflow_dispatch',
        { repository: { full_name: repo }, ref: 'refs/heads/main' },
        { ...env, GITHUB_SHA: 'short' },
      ),
    /SHA/,
  );
  assert.throws(
    () =>
      deploymentContext(
        'workflow_dispatch',
        { repository: { full_name: 'other/repo' }, ref: 'refs/heads/main' },
        env,
      ),
    /repository/,
  );
});

test('documentation-only classification requires nonempty paths all Markdown or docs', () => {
  assert.equal(isDocumentationOnly(['README.md', 'docs/setup.txt', 'nested/CHANGELOG.md']), true);
  for (const files of [
    [],
    ['README.md', 'src/main.ts'],
    ['documentation/file.txt'],
    ['docs2/file.txt'],
    null,
  ]) {
    assert.equal(isDocumentationOnly(files), false);
  }
});
