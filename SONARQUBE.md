# SonarQube API

The hosted Community Build uses one project per analyzed branch. Private
projects `fireguard-api-main` and `fireguard-api-develop` exist on the VPS instance
(`https://sonarqube.valentin-fortin.pro/`). Each repository branch is analyzed
as the project's main branch; do not configure `sonar.branch.name`. The legacy
`Fireguard-SSO` project remains separate pending provenance confirmation.

Use the built-in **Sonar way** quality profile and quality gate on both projects.
The CI's independent 90% executable-line requirement remains in force. The
SonarQube scanner imports the same run's complete Clover report from
`var/coverage/full/clover.xml`, which merges unit, architecture, integration,
functional and E2E execution data. A push to `main` or `develop` and a manually
started CI on those branches run the scan; pull requests, tags and other
branches do not send an analysis or receive a Sonar token.

This backport introduces the complete-source 90% gate on `main`, which did not
previously merge all five suites into one report. Its first CI run may expose a
historical coverage gap and block the SonarQube scan. Promote the normal test
backlog needed to meet the threshold; do not weaken the gate or mark deployment
ready to bypass that result.

These GitHub repository settings were provisioned on 2026-09-22:

| Setting | Value |
| --- | --- |
| Variable `SONAR_HOST_URL` | `https://sonarqube.valentin-fortin.pro/` |
| Secret `SONAR_TOKEN_MAIN` | Analysis token scoped to `fireguard-api-main` |
| Secret `SONAR_TOKEN_DEVELOP` | Analysis token scoped to `fireguard-api-develop` |
| Variable `SONAR_READY_MAIN` | `true` only after validating the main baseline |
| Variable `SONAR_READY_DEVELOP` | `true` only after validating the develop baseline |

The two project-scoped analysis tokens expire on **2026-12-21**. Rotate each
token before that date and replace only its matching GitHub secret. Never
commit or display a token.

Both readiness variables are currently `false`. The four initial scans across
the front and API, their new-code baselines, and the first validated gates are
still pending; neither API deployment branch is activated. The scanner and
quality-gate action fail a branch CI if the report, connection, token, analysis
or gate fails. Deployment refuses that branch until its readiness variable is
exactly `true`. This permits the initial analyses to be inspected without
allowing an automatic VPS deployment. Once the first scan of each project is
correct (scope, complete coverage and quality gate), set that scan as the
project's **new code baseline** in SonarQube, resolve any issues preventing
the intended gate, and rerun CI for a fresh valid result before enabling its
readiness variable. Baseline and issue triage are independent per project.

The deployment verifier requires a successful push or manual CI for the exact
repository, branch and SHA, including the named SonarQube gate job. A manual
CI cannot start an automatic deployment. A completed push CI routes a new
deployment run to its branch so GitHub enforces that environment's branch
restriction; the branch run rechecks the same CI run ID and current branch
tip. A manual VPS deployment rebuilds the selected branch's commit after
verifying its eligible CI. For a rollback, enter an existing API image tag or
digest into `image_ref`; the workflow resolves it to an immutable digest,
checks its repository and source-revision labels, then
requires a successful eligible CI for that SHA on the selected branch. A
`develop` validation cannot authorize production. Existing images lacking
those labels or predating this check cannot be rolled back through this flow.
See [DEPLOYMENT.md](DEPLOYMENT.md) for environment settings and fixture handling.

For a local Docker Compose scanner, `make sonar-up` then `make sonar-scan`
continues to target a separate local SonarQube server. Its default key is
`fireguard-api-develop` on that server; CI passes the explicit hosted project
key for its checked-out branch.
