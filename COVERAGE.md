# Backend coverage

The acceptance threshold is **90% of executable application lines**, measured over
every PHP file in `src/`, including API resources, adapters, commands and fixtures.
Unloaded files count as uncovered. Coverage-ignore annotations are disabled.
Tests, dependencies, generated caches and migration scripts are outside `src/`.
This is a line threshold; it does not claim 90% branch or path coverage.

## Run the complete check

Use PHP 8.4 with Xdebug or PCOV on the host and the existing PostgreSQL test
templates. If templates need preparation after migrations or fixture changes,
run `make test-db`; never seed development databases for tests.

```sh
make coverage-check PARALLEL_WORKERS=4
```

This runs the architecture, unit, integration, functional and E2E suites together,
then checks `var/coverage/full/clover.xml`. Failed tests stop the command before
the threshold check. Each worker uses the test bootstrap's isolated PostgreSQL
clones. Do not run overlapping parallel suites with the same worker tokens.

The percentage uses the unrounded covered/total line ratio. The checker rejects
missing or invalid reports, empty metrics, duplicate or missing source files and
inconsistent totals. To inspect an already generated complete report:

```sh
php bin/check-coverage.php var/coverage/full/clover.xml 90
```

That inspection does not rerun tests or prove the report is fresh. A unit-only
report or an individual CI artifact is not the combined result.

## GitHub enforcement

The `CI` workflow adds **`Application Coverage (90% lines)`** alongside the
existing `Unit and Architecture Tests`, `Integration and Functional Tests` and
`End-to-End Flow Tests` checks. It runs on every pull request targeting `main` or
`develop`, and on manual runs with `run_e2e: true`.

All three test jobs must succeed. Each exports native PHPUnit coverage from the
same commit; `bin/merge-coverage.php` unions their execution data through the
installed `php-code-coverage` library, then `bin/check-coverage.php` enforces 90%.
No suite is rerun and overlapping covered lines are counted once. Both PHPUnit
configurations include all `src/` PHP files and disable coverage-ignore comments.
The E2E job keeps its separately seeded PostgreSQL databases.

Missing artifacts, invalid coverage, incomplete source inventories, failed,
cancelled or skipped test jobs and a result below 90% fail this check. The job
uses `always()` on eligible runs so an unsuccessful prerequisite reaches the
explicit failure step rather than silently skipping the required check.
Downloads use three exact artifact names from the current workflow run; no
earlier coverage is reused. All jobs use
the same Ubuntu checkout path, as required by PHPUnit's absolute source paths.
The merged Clover report is retained as `api-coverage-complete` for seven days.

The release/deploy `workflow_call` preflight and manual runs without `run_e2e`
retain their existing E2E skip and do not claim complete coverage. No new push
trigger or branch/release policy is introduced. These partial runs use the
distinct skipped check name `Application Coverage (not requested)`, so they
cannot satisfy or replace the required `Application Coverage (90% lines)` check
on the same commit.

Codecov receives only the merged report, with a 90% project target and no flag
carryforward. Its upload remains optional; service availability and tokens do
not affect the local threshold check.

After publishing the workflow, make **`Application Coverage (90% lines)`** a
required status check in the GitHub branch rules for both `main` and `develop`.
Workflow failure alone does not prohibit merging when branch protection allows
bypassing checks. Repository settings are separate and are not changed here.

Native `.php` reports are executable serialized artifacts. Only merge files
produced by the current trusted CI jobs or your own local test run.

## Interpretation

Use module and file details to select meaningful missing behavior: denial paths,
replay protection, invalid external input, repository filters and failure
recovery. Do not remove application files from the denominator or add assertions
that merely invoke methods to raise coverage. A passing line threshold complements
contract, concurrency, static analysis and architecture checks.

On Windows, uploads and fixtures may fail inside a restricted process even when
the PostgreSQL clones work. Run the authorized test process with access to the
repository's `var/storage`; do not weaken storage behavior or change system ACLs.
