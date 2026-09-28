import { execFileSync } from 'node:child_process';
import { readdir } from 'node:fs/promises';
import path from 'node:path';

export function isProjectDocument(file) {
  const normalized = file.replaceAll('\\', '/');
  if (!/\.md$/i.test(normalized)) return false;
  if (/(?:^|\/)(AGENTS|CLAUDE|SKILL)\.md$/i.test(normalized)) return false;
  if (normalized.split('/').some((part) => part.startsWith('.'))) return false;
  if (!normalized.includes('/')) return true;
  return /^(src|e2e|docs)\//.test(normalized);
}

function canonical(value) {
  const normalized = path.resolve(value);
  return process.platform === 'win32' ? normalized.toLowerCase() : normalized;
}

export async function discoverDocuments(root) {
  // Git's inventory excludes caches, browser reports and installed dependencies.
  let gitRoot;
  try {
    gitRoot = execFileSync('git', ['rev-parse', '--show-toplevel'], {
      cwd: root,
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    }).trim();
  } catch {
    gitRoot = undefined;
  }
  if (gitRoot && canonical(gitRoot) === canonical(root)) {
    return [
      ...new Set(
        execFileSync(
          'git',
          ['ls-files', '--cached', '--others', '--exclude-standard', '-z', '--', '*.md', '*.MD'],
          { cwd: root, encoding: 'utf8', maxBuffer: 8 * 1024 * 1024 },
        ).split('\0'),
      ),
    ]
      .filter(isProjectDocument)
      .toSorted();
  }
  // Standalone fixture trees and extracted source archives have no Git index.
  const files = [];
  async function walk(directory) {
    await Promise.all(
      (await readdir(directory, { withFileTypes: true })).map(async (entry) => {
        if (
          entry.name.startsWith('.') ||
          ['node_modules', 'vendor', 'coverage', 'dist'].includes(entry.name)
        )
          return;
        const relative = path
          .relative(root, path.join(directory, entry.name))
          .replaceAll('\\', '/');
        if (entry.isFile() && isProjectDocument(relative)) files.push(relative);
        if (
          entry.isDirectory() &&
          (directory !== root || ['src', 'e2e', 'docs'].includes(entry.name))
        ) {
          await walk(path.join(directory, entry.name));
        }
      }),
    );
  }
  await walk(root);
  return files.toSorted();
}
