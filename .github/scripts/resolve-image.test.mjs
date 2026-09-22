import assert from 'node:assert/strict';
import test from 'node:test';
import {
  parseRegistryDigest,
  resolveImage,
  validateImageConfig,
  validateImageLabels,
} from './resolve-image.mjs';

const repo = 'example/fireguard-sso-web';
const sha = 'a'.repeat(40);
const digest = `sha256:${'b'.repeat(64)}`;
const image = `ghcr.io/${repo}`;
const config = validateImageConfig({
  GH_TOKEN: 'test-token',
  GITHUB_REPOSITORY: repo,
  IMAGE_REF: `${image}:main`,
  SOURCE_BRANCH: 'main',
  SOURCE_SHA: sha,
});
const labels = {
  'org.opencontainers.image.source': `https://github.com/${repo}`,
  'org.opencontainers.image.revision': sha,
};

test('validates repository-bound GHCR tag or digest', () => {
  assert.equal(config.imageRef, `${image}:main`);
  assert.equal(
    validateImageConfig({
      GH_TOKEN: 'x',
      GITHUB_REPOSITORY: repo,
      IMAGE_REF: `${image}@${digest}`,
      SOURCE_BRANCH: 'develop',
    }).imageRef,
    `${image}@${digest}`,
  );
  for (const imageRef of [
    `${image}/evil:main`,
    `${image}:main;echo`,
    `${image}:../evil`,
    'ghcr.io/other/repo:main',
    'docker.io/example/fireguard-sso-web:main',
    `${image}@sha256:short`,
  ]) {
    assert.throws(
      () =>
        validateImageConfig({
          GH_TOKEN: 'x',
          GITHUB_REPOSITORY: repo,
          IMAGE_REF: imageRef,
          SOURCE_BRANCH: 'main',
        }),
      /Image reference/,
    );
  }
});

test('resolves registry digest, pulls only immutable ref, verifies labels', () => {
  const calls = [];
  const runDocker = (args) => {
    calls.push(args);
    if (args[0] === 'buildx') return `Name: ${image}:main\nDigest: ${digest}\n`;
    if (args[0] === 'image') return JSON.stringify(labels);
    return '';
  };
  assert.deepEqual(resolveImage(config, { runDocker }), {
    imageRef: `${image}@${digest}`,
    sourceSha: sha,
  });
  assert.deepEqual(calls, [
    ['buildx', 'imagetools', 'inspect', `${image}:main`],
    ['pull', `${image}@${digest}`],
    ['image', 'inspect', `${image}@${digest}`, '--format', '{{json .Config.Labels}}'],
  ]);
});

test('rejects registry digest disagreement and malformed inspect output', () => {
  const digestConfig = { ...config, imageRef: `${image}@sha256:${'c'.repeat(64)}` };
  assert.throws(
    () => resolveImage(digestConfig, { runDocker: () => `Digest: ${digest}` }),
    /differs/,
  );
  assert.throws(
    () => parseRegistryDigest(`Digest: ${digest}\nDigest: ${digest}`),
    /one image digest/,
  );
});

test('rejects wrong source, revision and expected SHA', () => {
  assert.equal(
    validateImageLabels(
      {
        ...labels,
        'org.opencontainers.image.source': 'https://github.com/Example/Fireguard-SSO-Web',
      },
      repo,
      sha,
    ),
    sha,
  );
  for (const source of [
    'https://github.com.evil.test/example/fireguard-sso-web',
    'https://user@github.com/example/fireguard-sso-web',
    'https://github.com/example/fireguard-sso-web?ref=main',
    'https://github.com/example/fireguard-sso-web#source',
  ]) {
    assert.throws(
      () =>
        validateImageLabels({ ...labels, 'org.opencontainers.image.source': source }, repo, sha),
      /source/,
    );
  }
  assert.throws(
    () =>
      validateImageLabels(
        { ...labels, 'org.opencontainers.image.source': 'https://github.com/other/repo' },
        repo,
        sha,
      ),
    /source/,
  );
  assert.throws(
    () =>
      validateImageLabels({ ...labels, 'org.opencontainers.image.revision': 'short' }, repo, sha),
    /revision/,
  );
  assert.throws(() => validateImageLabels(labels, repo, 'c'.repeat(40)), /does not match/);
});
