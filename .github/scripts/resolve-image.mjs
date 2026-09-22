import { execFileSync } from 'node:child_process';
import { appendFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const SHA = /^[0-9a-f]{40}$/i;
const DIGEST = /^sha256:[0-9a-f]{64}$/i;
const REPOSITORY = /^[A-Za-z0-9][A-Za-z0-9-]*\/[A-Za-z0-9_.-]+$/;
const TAG = /^[A-Za-z0-9_][A-Za-z0-9_.-]{0,127}$/;

export function validateImageConfig(env) {
  const { GH_TOKEN, GITHUB_REPOSITORY, IMAGE_REF, SOURCE_BRANCH, SOURCE_SHA } = env;
  if (!GH_TOKEN || !REPOSITORY.test(GITHUB_REPOSITORY ?? ''))
    throw new Error('GitHub token and repository are required');
  if (!['main', 'develop'].includes(SOURCE_BRANCH)) throw new Error('Unsupported source branch');
  if (SOURCE_SHA && !SHA.test(SOURCE_SHA)) throw new Error('Source SHA must be a full commit SHA');
  const repository = GITHUB_REPOSITORY.toLowerCase();
  const image = `ghcr.io/${repository}`;
  if (typeof IMAGE_REF !== 'string' || !IMAGE_REF.startsWith(image))
    throw new Error('Image reference must use this repository in GHCR');
  const suffix = IMAGE_REF.slice(image.length);
  if (
    !(suffix.startsWith(':') && TAG.test(suffix.slice(1))) &&
    !(suffix.startsWith('@') && DIGEST.test(suffix.slice(1)))
  ) {
    throw new Error('Image reference must have one valid tag or digest');
  }
  return { repository, image, imageRef: IMAGE_REF, sourceSha: SOURCE_SHA?.toLowerCase() };
}

function docker(args) {
  const { GH_TOKEN: unused, ...safeEnv } = process.env;
  void unused;
  return execFileSync('docker', args, {
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    env: safeEnv,
    maxBuffer: 1024 * 1024,
  });
}

export function parseRegistryDigest(output) {
  const matches = [...output.matchAll(/^Digest:\s+(sha256:[0-9a-f]{64})\s*$/gim)];
  if (matches.length !== 1) throw new Error('Registry did not return one image digest');
  return matches[0][1].toLowerCase();
}

export function validateImageLabels(labels, repository, expectedSha) {
  if (!labels || typeof labels !== 'object' || Array.isArray(labels))
    throw new Error('Image labels are missing');
  let source;
  try {
    source = new URL(labels['org.opencontainers.image.source']);
  } catch {
    throw new Error('Image source does not match repository');
  }
  const revision = labels['org.opencontainers.image.revision'];
  if (
    source.protocol !== 'https:' ||
    source.host !== 'github.com' ||
    source.username ||
    source.password ||
    source.search ||
    source.hash ||
    source.pathname.toLowerCase() !== `/${repository.toLowerCase()}`
  )
    throw new Error('Image source does not match repository');
  if (typeof revision !== 'string' || !SHA.test(revision))
    throw new Error('Image revision is not a full commit SHA');
  if (expectedSha && revision.toLowerCase() !== expectedSha.toLowerCase())
    throw new Error('Image revision does not match source SHA');
  return revision.toLowerCase();
}

export function resolveImage(config, { runDocker = docker } = {}) {
  const inspect = runDocker(['buildx', 'imagetools', 'inspect', config.imageRef]);
  const digest = parseRegistryDigest(inspect);
  if (config.imageRef.includes('@') && config.imageRef.split('@')[1].toLowerCase() !== digest)
    throw new Error('Registry digest differs from requested digest');
  const immutableRef = `${config.image}@${digest}`;
  runDocker(['pull', immutableRef]);
  const rawLabels = runDocker([
    'image',
    'inspect',
    immutableRef,
    '--format',
    '{{json .Config.Labels}}',
  ]);
  let labels;
  try {
    labels = JSON.parse(rawLabels);
  } catch {
    throw new Error('Docker returned invalid image labels');
  }
  const sourceSha = validateImageLabels(labels, config.repository, config.sourceSha);
  return { imageRef: immutableRef, sourceSha };
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  try {
    const result = resolveImage(validateImageConfig(process.env));
    if (!process.env.GITHUB_OUTPUT) throw new Error('GITHUB_OUTPUT is required');
    appendFileSync(
      process.env.GITHUB_OUTPUT,
      `image-ref=${result.imageRef}\nsource-sha=${result.sourceSha}\n`,
    );
    if (process.env.GITHUB_STEP_SUMMARY)
      appendFileSync(
        process.env.GITHUB_STEP_SUMMARY,
        `Resolved ${result.imageRef} from source revision ${result.sourceSha}.\n`,
      );
    process.stdout.write(`Resolved ${result.imageRef} from ${result.sourceSha}\n`);
  } catch (error) {
    process.stderr.write(`${error instanceof Error ? error.message : 'Image resolution failed'}\n`);
    process.exitCode = 1;
  }
}
