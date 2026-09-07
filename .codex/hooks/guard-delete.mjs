#!/usr/bin/env node
// Checkout-local destructive-operation guard for Codex.
/**
 * PreToolUse hook (monorepo root) — block irreversible or history-rewriting shell
 * commands before they run. Deny = exit 2 with a message on stderr; everything else
 * = exit 0.
 *
 * The companion to guard.mjs: that one guards what gets *written*, this one guards
 * what gets *destroyed*. It reads `tool_input.command` from Bash and PowerShell calls.
 *
 * Denies:
 *  1. RECURSIVE deletion of an ABSOLUTE, drive-rooted, `~`-rooted or
 *     `..`-escaping path that is not clearly disposable. Relative targets inside
 *     the working tree pass: they live under git, so a wrong delete is one
 *     `git checkout -- <path>` away from recovery. `git rm` (recursive or not)
 *     always passes for the same reason — it stages the deletion for review.
 *  2. `git clean` with -f/-d/-x, which silently discards untracked work.
 *  3. `git reset --hard`, `git checkout/restore .`, and `git push --force` on a
 *     shared branch — history and uncommitted work are not the assistant's to drop.
 *  4. Destructive SQL (DROP DATABASE/TABLE, TRUNCATE) and `doctrine:schema:drop`.
 *  5. `rm` of a whole app tree, `.git/`, or the monorepo root itself.
 *
 * Loosened 2026-08-20 on the user's explicit instruction: `git branch -D` passes
 * (the reflog keeps the commits ~90 days, and squash-merged branches trip the -d
 * safety uselessly), `git rm` passes, and in-tree relative recursive deletes pass.
 * The guard's job is the blast radius git cannot undo, not deletion in general.
 *
 * Loosened again 2026-08-25, same instruction, after rule 1 denied
 * `rm -f <relative>.bak`. Two false positives, both in rule 1, neither of which
 * ever reduced the blast radius:
 *   - `-f` counted as recursive. It is not: `rm -f` takes named files only.
 *     Now `-r` is required.
 *   - The delete verb and the out-of-tree target were matched against the whole
 *     command line rather than one segment. Every compound command opens with
 *     `cd "G:/Projets/…"`, so that `cd` satisfied "absolute path" for any `rm`
 *     after the `&&`. Rule 1 now runs per shell segment.
 * Rules 2-5 are unchanged: `git reset --hard` and `git checkout .` in particular
 * stay denied, because they would erase uncommitted work in progress.
 */
function readStdin() {
  return new Promise((resolve) => {
    let data = '';
    process.stdin.on('data', (chunk) => (data += chunk));
    process.stdin.on('end', () => resolve(data));
    if (process.stdin.isTTY) resolve('');
  });
}

function deny(what, why) {
  process.stderr.write(
    `Blocked: ${what}\n${why}\nIf this is genuinely the task, run it yourself — it is not the assistant's to perform.\n`,
  );
  process.exit(2);
}

const raw = await readStdin();
let payload = {};
try {
  payload = JSON.parse(raw || '{}');
} catch {
  process.exit(0);
}

const command = payload?.tool_input?.command ?? '';
if (!command) process.exit(0);

// Normalize: collapse whitespace and Windows separators so one pattern set covers
// both shells. Keep the original for the message.
//
// `cSeparators` keeps the line breaks, because a NEWLINE separates two shell
// commands exactly as `&&` does and rule 1 splits on it. Collapsing whitespace
// first would erase that boundary and merge a `cd /abs/path` line into the
// `rm -rf relative/path` line below it — which is the very false positive rule 1
// was rewritten to avoid.
const cSeparators = command.replace(/\\/g, '/');
const c = cSeparators.replace(/\s+/g, ' ').trim();

// Paths that are always safe to blow away.
const DISPOSABLE =
  /(node_modules|dist|\.angular|var\/cache|var\/log|coverage|test-results|playwright-report|\/tmp\/|scratchpad)/;

// `git rm` stages a deletion for review and is always recoverable — mask it so
// the raw-rm pattern below cannot match inside it. The replacement must not
// itself contain an `rm` token (`git-tracked-rm` did: `-` is a word boundary).
const MASK_GIT_RM = /\bgit(\s+-C\s+\S+)?\s+rm\b/g;
const c2 = c.replace(MASK_GIT_RM, 'git-tracked-removal');
const c2Separators = cSeparators.replace(MASK_GIT_RM, 'git-tracked-removal');

// 1. Recursive deletion — but only of a target git cannot bring back: an
// absolute path, a drive root, a `~` home path, or a `..` escape out of the
// working tree. Relative in-tree targets are tracked and one checkout away
// from recovery.
//
// `-r` is required, not `-r` OR `-f`: `rm -f` deletes named files only and
// cannot take out a tree, so treating it as a recursive delete blocked routine
// single-file cleanups (`rm -f foo.bak`) for no gain in blast radius.
const RECURSIVE_RM = /\b(rm\s+(-[a-z]*r[a-z]*\s+)+|Remove-Item\s+.*-Recurse|rmdir\s+\/s|rd\s+\/s)/i;
const OUT_OF_TREE_TARGET = /(\s|=|["'])(\/|[A-Za-z]:\/|~\/|\.\.\/)/;

// Evaluated per shell segment, never across the whole line. A compound command
// almost always opens with `cd "G:/Projets/…"`, and matching the delete verb in
// one clause against an absolute path in another denied every `rm` that
// followed a `cd` — a false positive with no relation to what was deleted.
for (const rawSegment of c2Separators.split(/(?:&&|\|\||;|\||\n)/)) {
  const segment = rawSegment.replace(/\s+/g, ' ').trim();
  if (RECURSIVE_RM.test(segment) && !DISPOSABLE.test(segment) && OUT_OF_TREE_TARGET.test(segment)) {
    deny(
      `recursive delete of an out-of-tree path — ${command}`,
      'Recursively deleting an absolute, home, or parent-escaping path risks work git cannot restore.',
    );
  }
}

// Whole-tree targets: a filesystem root, a `.git` directory, or an entire app tree.
// The app-tree pattern must match the END of the argument — `rm -rf fireguard-sso-web`
// deletes the application, while `rm -rf fireguard-sso-web/node_modules` is a routine
// reinstall. Anchoring on the name alone blocked the second, which is a false positive
// that teaches people to disable the guard.
const WHOLE_TREE =
  /\b(rm|Remove-Item)\b[^|;&]*\s(?:\/|[A-Za-z]:\/|~\/?)(?=\s|$)|\b(rm|Remove-Item)\b[^|;&]*\s\S*\.git(?:\/)?(?=\s|$)|\b(rm|Remove-Item)\b[^|;&]*\sfireguard-sso-(?:api|web)\/?(?=\s|$)/i;
if (WHOLE_TREE.test(c)) {
  deny(
    `delete of a repository or root path — ${command}`,
    'That target is an entire app tree, a .git directory, or a filesystem root.',
  );
}

// 2. git clean.
if (/\bgit\s+clean\b/.test(c) && /-[a-z]*[fdx]/.test(c)) {
  deny(
    `git clean — ${command}`,
    'git clean discards untracked files permanently, including work in progress that was never staged.',
  );
}

// 3. History and working-tree destruction.
if (/\bgit\s+reset\s+.*--hard\b/.test(c)) {
  deny(`git reset --hard — ${command}`, 'That discards uncommitted changes with no recovery path.');
}
if (/\bgit\s+(checkout|restore)\s+(--\s+)?\.\s*$/.test(c)) {
  deny(
    `git ${/checkout/.test(c) ? 'checkout' : 'restore'} . — ${command}`,
    'That reverts every uncommitted change in the working tree at once.',
  );
}
if (/\bgit\s+push\b/.test(c) && /(--force(?!-with-lease)|\s-f\b)/.test(c)) {
  deny(
    `forced push — ${command}`,
    'A force push rewrites published history. Use --force-with-lease at minimum, and only deliberately.',
  );
}
// `git branch -D` is allowed (user decision, 2026-08-20): squash-merged branches
// always fail the -d ancestry check even though their content is upstream, and a
// genuinely unmerged branch's commits stay in the reflog for ~90 days.

// 4. Destructive database operations.
if (/\b(DROP\s+(DATABASE|SCHEMA|TABLE)|TRUNCATE\s+TABLE)\b/i.test(c)) {
  deny(`destructive SQL — ${command}`, 'Dropping or truncating is irreversible against a real database.');
}
if (/doctrine:(schema:drop|database:drop)/.test(c)) {
  deny(
    `${c.match(/doctrine:[a-z:]+/)?.[0]} — ${command}`,
    'Change the schema with a migration rather than dropping it: php -d memory_limit=1G ' +
      'bin/console doctrine:migrations:diff --configuration=config/migrations/<db>.yaml ' +
      '(use the fg-api-migration-builder agent or fg-api-migrate skill).',
  );
}

process.exit(0);
