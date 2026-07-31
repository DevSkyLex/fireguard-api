#!/usr/bin/env node
/**
 * PreToolUse hook (fireguard-sso-api) - block clearly-unsafe or architecture-violating
 * writes before they happen. Deny = exit 2 with a message on stderr; everything else
 * = exit 0.
 *
 * Paths are relative to the API root, because this config is loaded when
 * `fireguard-sso-api/` is the workspace root.
 *
 * Denies:
 *  1. Secrets: any `.env*` (except `.env.example` / `.env.dist`) and `config/jwt/`.
 *  2. Generated trees: `vendor/`, `var/`, `node_modules/`, `public/bundles/`.
 *  3. Hexagonal layer violations, caught before deptrac runs:
 *     - a Domain file importing Application, Infrastructure, or Presentation,
 *     - an Application file importing Infrastructure or Presentation.
 *     Domain must depend on nothing but SharedDomain; Application defines ports and
 *     must never reference the adapters that fulfil them (ARCHITECTURE.md, Core
 *     Principles). `make deptrac` enforces the same rule at the gate - this only
 *     moves the feedback earlier.
 *  4. Editing a migration that already exists on disk. An applied migration is
 *     history: change the schema with a NEW migration instead.
 *
 * Intentionally narrow. It never blocks ordinary source edits, tests, config, or docs
 * - MODULE.md and ARCHITECTURE.md stay writable because the standard requires them to
 * be updated in the same change.
 */
import { existsSync } from 'node:fs';

function readStdin() {
  return new Promise((resolve) => {
    let data = '';
    process.stdin.on('data', (chunk) => (data += chunk));
    process.stdin.on('end', () => resolve(data));
    if (process.stdin.isTTY) resolve('');
  });
}

function deny(message) {
  process.stderr.write(`Blocked: ${message}\n`);
  process.exit(2);
}

const raw = await readStdin();
let payload = {};
try {
  payload = JSON.parse(raw || '{}');
} catch {
  process.exit(0);
}

const filePath = (payload?.tool_input?.file_path ?? payload?.tool_input?.filePath ?? '').replace(
  /\\/g,
  '/',
);
if (!filePath) process.exit(0);

// 1. Secrets.
const base = filePath.split('/').pop() ?? '';
if (/(^|\/)\.env(\.|$)/.test(filePath) && !/^\.env\.(example|dist)$/.test(base)) {
  deny(
    `${filePath} looks like an environment/secret file. Do not write secrets. ` +
      'Document required variables in .env.example instead.',
  );
}
if (/\/config\/jwt\//.test(filePath)) {
  deny(`${filePath} is under config/jwt/. JWT keys are generated, never written by the assistant.`);
}

// 2. Generated / vendored trees.
const GENERATED = [
  ['/vendor/', 'a Composer dependency'],
  ['/var/', 'a Symfony runtime/cache dir'],
  ['/node_modules/', 'an npm dependency'],
  ['/public/bundles/', 'an asset install output'],
];
for (const [seg, what] of GENERATED) {
  if (filePath.includes(seg)) {
    deny(
      `${filePath} is inside ${what} (${seg.replaceAll('/', '')}). Edit the source, not the output.`,
    );
  }
}

// 3. Hexagonal layer direction, checked on the written content.
const written = payload?.tool_input?.content ?? payload?.tool_input?.new_string ?? '';
const layerOf = /\/src\/[A-Z][A-Za-z]*\/(Domain|Application|Infrastructure|Presentation)\//.exec(
  filePath,
);
if (layerOf && written) {
  const layer = layerOf[1];
  // A `use` statement crossing into a forbidden layer. Namespaces are
  // `<Module>\<Layer>\...`, so the layer segment is the discriminator.
  const forbidden = {
    Domain: ['Application', 'Infrastructure', 'Presentation'],
    Application: ['Infrastructure', 'Presentation'],
  }[layer];

  if (forbidden) {
    for (const target of forbidden) {
      const useRe = new RegExp(String.raw`^\s*use\s+[A-Z][A-Za-z]*\\${target}\\`, 'm');
      if (useRe.test(written)) {
        deny(
          `${filePath} is in the ${layer} layer and imports ${target}. ` +
            'Hexagonal dependency direction is one-way: Presentation -> Application -> Domain, ' +
            'and Infrastructure -> Application (it implements ports). Domain depends on nothing ' +
            'but SharedDomain. Depend on a port under Application/Port/Outbound instead of the ' +
            'concrete class (ARCHITECTURE.md, Core Principles). `make deptrac` enforces this too.',
        );
      }
    }
  }
}

// 4. An applied migration is history.
if (/\/migrations\/(auth|main)\/Version\d+\.php$/.test(filePath) && existsSync(filePath)) {
  deny(
    `${filePath} already exists. A migration that has been generated - and very likely ` +
      'applied - must not be edited: its checksum and its place in the history are fixed. ' +
      'Generate a NEW migration for the schema change: ' +
      'php bin/console doctrine:migrations:diff --configuration=config/migrations/<db>.yaml ' +
      '(see the fg-migration-builder agent).',
  );
}

process.exit(0);
