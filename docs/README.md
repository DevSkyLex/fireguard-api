# Documentation

Start with the [repository README](../README.md). This index separates normative
contracts from explanations and environment-specific operational information.

## Authoritative references

- [Architecture](../ARCHITECTURE.md): dependencies, ownership and construction rules.
- [Deployment](../DEPLOYMENT.md): delivery and runtime configuration contract.
- [Coverage](../COVERAGE.md): complete-suite acceptance and its limitations.
- [SonarQube](../SONARQUBE.md): analysis identity and deployment readiness.
- [Security](../SECURITY.md) and [Operations](../OPERATIONS.md): security invariants and operational procedures.

## Guides and operations

- [System overview](architecture/system-overview.md)
- [Patterns and examples](architecture/patterns-and-examples.md)
- [Local development](guides/local-development.md)
- [Organization and access](guides/organization-and-access.md)
- [Testing](guides/testing.md)
- [Current installation](operations/current-installation.md)
- [Module development](guides/module-development.md)
- [Authentication](guides/authentication.md)
- [Messaging](guides/messaging.md)
- [Interventions](guides/interventions.md)
- [Async processing](guides/async-processing.md)
- [Migrations and backups](operations/migrations-and-backups.md)
- [Workers and scheduler](operations/workers-and-scheduler.md)
- [Monitoring and troubleshooting](operations/monitoring-and-troubleshooting.md)
- [Security runbooks](operations/security-runbooks.md)

## Owner contracts

- [Approval](../src/Approval/MODULE.md)
- [Assistant](../src/Assistant/MODULE.md)
- [Audit](../src/Audit/MODULE.md)
- [Auth](../src/Auth/MODULE.md)
- [Authorization](../src/Authorization/MODULE.md)
- [Automation](../src/Automation/MODULE.md)
- [Billing](../src/Billing/MODULE.md)
- [Calendar](../src/Calendar/MODULE.md)
- [Compliance](../src/Compliance/MODULE.md)
- [Equipment](../src/Equipment/MODULE.md)
- [Facility](../src/Facility/MODULE.md)
- [Import](../src/Import/MODULE.md)
- [Inspection](../src/Inspection/MODULE.md)
- [Intervention](../src/Intervention/MODULE.md)
- [Maintenance](../src/Maintenance/MODULE.md)
- [Messaging](../src/Messaging/MODULE.md)
- [Notification](../src/Notification/MODULE.md)
- [OAuth](../src/OAuth/MODULE.md)
- [Onboarding](../src/Onboarding/MODULE.md)
- [Organization](../src/Organization/MODULE.md)
- [Otp](../src/Otp/MODULE.md)
- [Session](../src/Session/MODULE.md)
- [Shared](../src/Shared/MODULE.md)
- [Tenant](../src/Tenant/MODULE.md)
- [TrustedDevice](../src/TrustedDevice/MODULE.md)
- [User](../src/User/MODULE.md)
- [Webhook](../src/Webhook/MODULE.md)
- [Workload](../src/Workload/MODULE.md)

## Documentation conventions

Write in English. State what a document governs and link to its authoritative
owner. Preserve public names, security requirements and contextual invariants.
Use `example.com`, `<organization-id>`, `<module>` and configured variables for
illustrations; keep real installation values in the installation appendix.

Keep dependency versions in manifests, command inventories in the Makefile or
package scripts, and HTTP schemas in the generated OpenAPI contract. Label
historical observations with their date and scope. A historical successful run
does not establish current health. Keep examples and implementation inventories
separate from the owning feature/module contract.

Preserve linked headings when moving an explanation: leave a short rule and a
link at the original heading. Place Mermaid blocks in the document owning the
relationship. Explain arrow direction and include a prose equivalent. Rendered
SVGs are review artifacts; the Markdown block remains the diagram source.

## Validate documentation

From `.github/documentation` run:

Dependencies are isolated and locked in [the tooling manifest](../.github/documentation/package.json),
including the [official Mermaid CLI release](https://github.com/mermaid-js/mermaid-cli/releases/tag/11.17.0).

```sh
npm ci
npm test
npm run check
```

The isolated Node.js 22 tools check project Markdown in the root, `src/`, `e2e/`
and `docs/`. Agent instructions, installed skills and dependency trees are outside
this scope. Local targets, images, references and Markdown fragments are checked;
code examples and comments are omitted. External URLs require editorial review.
All Mermaid blocks are rendered. A broken link, unknown heading or invalid diagram
fails the check; tests verify those failure gates using actual fixtures.

`npm run check -- --output-dir <directory>` retains source diagrams, SVGs and
JSON results in the chosen directory; otherwise an OS temporary directory is used.
CI retains its `documentation-diagrams` artifact for seven days. Review diagram
labels and layout as well as parser success. This check complements the existing
application tests, architecture, coverage and deployment gates.

Current npm versions may require explicit approval of Puppeteer's installation
script. If browser installation is pending, run its official installer from this
directory with `node node_modules/puppeteer/install.mjs`, then rerun the checks.
