import { execFileSync } from 'node:child_process';
import { appendFileSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

/** Validate the small push payload downloaded from the already verified CI run. */
export function validatePushContext(context, { repository, branch, sha }) {
  const commit = /^[a-f0-9]{40}$/;
  if (!['main', 'develop'].includes(branch) || !commit.test(sha ?? '')) {
    throw new Error('Invalid deployment branch or revision');
  }
  if (
    typeof context?.repository !== 'string' ||
    context.repository.toLowerCase() !== repository.toLowerCase() ||
    context.ref !== `refs/heads/${branch}` ||
    context.after !== sha ||
    !commit.test(context.before ?? '')
  ) {
    throw new Error('Push context does not match the verified CI revision');
  }
  return context.before;
}

/** An empty or indeterminate diff must never suppress a deployment. */
export function isDocumentationOnly(files) {
  return (
    files.length > 0 && files.every((file) => file.endsWith('.md') || file.startsWith('docs/'))
  );
}

export function shouldDeploy(context, expected, git) {
  const before = validatePushContext(context, expected);
  if (/^0{40}$/.test(before)) return true;
  // A force-push or unavailable previous commit cannot prove a docs-only change.
  try {
    git(['cat-file', '-e', `${before}^{commit}`]);
  } catch {
    git(['fetch', '--no-tags', 'origin', before]);
  }
  try {
    git(['merge-base', '--is-ancestor', before, expected.sha]);
  } catch {
    return true;
  }
  const files = git(['diff', '--name-only', '-z', `${before}..${expected.sha}`])
    .split('\0')
    .filter(Boolean);
  return !isDocumentationOnly(files);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    const result = shouldDeploy(
      JSON.parse(readFileSync(process.env.CI_CONTEXT_PATH, 'utf8')),
      {
        repository: process.env.GITHUB_REPOSITORY,
        branch: process.env.SOURCE_BRANCH,
        sha: process.env.SOURCE_SHA,
      },
      (args) => execFileSync('git', args, { encoding: 'utf8', timeout: 60_000 }),
    );
    appendFileSync(process.env.GITHUB_OUTPUT, `should-deploy=${result}\n`);
    process.stdout.write(
      `${result ? 'Application changes require deployment.' : 'Documentation-only push: deployment skipped.'}\n`,
    );
  } catch (error) {
    process.stderr.write(`${error.message}\n`);
    process.exitCode = 1;
  }
}
