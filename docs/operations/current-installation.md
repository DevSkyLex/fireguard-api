# Current installation

This appendix records the non-secret installation identity documented in the
repository at baseline `49de0f5273e0fe3c98d81099c78b4d18beb7e739` (reviewed 2026-09-28). It is an
installation record, not evidence of live health or a replacement for GitHub
environment variables. Verify configured values before operational changes.

## Deployment identity

| GitHub environment | Branch    | API                                     | Mercure                                     | Mailpit                                  | VPS directory                          | Docker project              | Volume prefix        |
| ------------------ | --------- | --------------------------------------- | ------------------------------------------- | ---------------------------------------- | -------------------------------------- | --------------------------- | -------------------- |
| `production`       | `main`    | `api.fireguard.valentin-fortin.pro`     | `mercure.fireguard.valentin-fortin.pro`     | —                                        | `/srv/apps/fireguard/production/back`  | `fireguard-production-back` | `back`               |
| `development`      | `develop` | `dev.api.fireguard.valentin-fortin.pro` | `dev.mercure.fireguard.valentin-fortin.pro` | `dev.mail.fireguard.valentin-fortin.pro` | `/srv/apps/fireguard/development/back` | `fireguard-dev-back`        | `fireguard-dev-back` |

GitHub environment variables and secrets configure deployment; the workflow and
Compose/playbook definitions remain authoritative. Preserve existing directories,
projects, containers and volume identities when changing delivery tooling.

Production's `back` volume prefix retains volumes predating the explicit project
name; changing it needs a deliberate volume migration. Development has separate
volumes, keys and databases. The development Mailpit surface is
`https://dev.mail.fireguard.valentin-fortin.pro`, protected by Basic Auth and noindex.

The geocoding adapter's documented identifying User-Agent includes the installation
contact `contact@valentin-fortin.pro`; that identifier is code-owned, not a new
configuration variable introduced by this documentation revision.

## SonarQube installation

- Host: `https://sonarqube.valentin-fortin.pro/`.
- Projects: `fireguard-api-main` and `fireguard-api-develop`, one Community Build project
  per Git branch, with independent analysis histories.
- Matching secrets: `SONAR_TOKEN_MAIN`, `SONAR_TOKEN_DEVELOP`.
- Readiness variables: `SONAR_READY_MAIN`, `SONAR_READY_DEVELOP`.

The prior deployment record dated 2026-09-22 stated a token expiry of 2026-12-21.
Treat that as historical metadata: verify current token expiry in SonarQube and
rotate each matching GitHub secret according to operational policy. No token
value is recorded here. [SONARQUBE.md](../../SONARQUBE.md) defines the gate contract.

## Operational ownership

The operator's private configuration owns credentials, backups, incident contacts,
retention and service objectives. Follow [deployment](../../DEPLOYMENT.md) for
image/rollback validation. Review installation records when those identities change;
ordinary examples in guides use reserved domains and configured variables.
