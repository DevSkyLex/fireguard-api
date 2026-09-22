import assert from 'node:assert/strict';
import test from 'node:test';
import { isDocumentationOnly, shouldDeploy, validatePushContext } from './changed-files.mjs';

const sha = 'a'.repeat(40);
const before = 'b'.repeat(40);
const expected = { repository: 'DevSkyLex/fireguard-api', branch: 'develop', sha };
const context = { repository: expected.repository, ref: 'refs/heads/develop', after: sha, before };

test('documentation-only means every changed path in the complete push', () => {
  assert.equal(isDocumentationOnly(['README.md', 'docs/diagram.svg']), true);
  assert.equal(isDocumentationOnly(['README.md', 'src/Handler.php']), false);
  assert.equal(isDocumentationOnly([]), false);
  assert.equal(isDocumentationOnly(['src/docs/conf.php']), false);
});

test('push context must match repository, branch and exact commit', () => {
  for (const changed of [
    { repository: 'attacker/fork' },
    { ref: 'refs/heads/main' },
    { after: before },
    { before: '--output=malicious' },
  ]) {
    assert.throws(() => validatePushContext({ ...context, ...changed }, expected));
  }
});

test('new branch and force-push conservatively request deployment', () => {
  assert.equal(
    shouldDeploy({ ...context, before: '0'.repeat(40) }, expected, () => assert.fail()),
    true,
  );
  assert.equal(
    shouldDeploy(context, expected, (args) => {
      if (args[0] === 'merge-base') throw new Error('not an ancestor');
      return '';
    }),
    true,
  );
});

test('the diff covers before..after, including all commits in the push', () => {
  const calls = [];
  const result = shouldDeploy(context, expected, (args) => {
    calls.push(args);
    return args[0] === 'diff' ? 'README.md\0docs/setup.txt\0' : '';
  });
  assert.equal(result, false);
  assert.deepEqual(calls.at(-1), ['diff', '--name-only', '-z', `${before}..${sha}`]);
});

test('missing previous object is fetched by validated SHA', () => {
  const calls = [];
  assert.equal(
    shouldDeploy(context, expected, (args) => {
      calls.push(args);
      if (args[0] === 'cat-file') throw new Error('missing');
      return args[0] === 'diff' ? 'src/Foo.php\0' : '';
    }),
    true,
  );
  assert.deepEqual(calls[1], ['fetch', '--no-tags', 'origin', before]);
});

test('unavailable history fails closed instead of silently skipping', () => {
  assert.throws(() =>
    shouldDeploy(context, expected, () => {
      throw new Error('unavailable');
    }),
  );
});
