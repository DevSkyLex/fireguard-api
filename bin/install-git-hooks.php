<?php

declare(strict_types=1);

/**
 * Point Git at the versioned hooks in `.githooks/`.
 *
 * Run from composer's post-install/post-update hooks so the hooks are active on
 * every clone without anyone having to remember `git config core.hooksPath`.
 * A hook nobody enabled is worse than no hook: it reads as a guardrail while
 * silently doing nothing.
 *
 * Exits successfully when there is no repository to configure — Docker builds
 * and tarball deploys run `composer install` without `.git`, and failing there
 * would break the build for no benefit.
 */
$root = dirname(__DIR__);

// `.git` is a directory in a normal clone and a file inside a worktree.
if (!file_exists($root . '/.git')) {
  exit(0);
}

if (!is_dir($root . '/.githooks')) {
  fwrite(STDERR, "install-git-hooks: .githooks/ is missing, skipping.\n");

  exit(0);
}

$output = [];
$status = 0;
exec('git config core.hooksPath .githooks 2>&1', $output, $status);

if (0 !== $status) {
  fwrite(STDERR, 'install-git-hooks: could not set core.hooksPath: ' . implode(' ', $output) . "\n");

  exit(0);
}

echo "install-git-hooks: core.hooksPath set to .githooks\n";
