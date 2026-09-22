# Security checklist

Read `AGENTS.md`, `SECURITY.md`, `.codex/workflow.md` and the affected module contract.
Apply this checklist to auth, OAuth/OIDC, sessions, OTP/MFA, RBAC, tenant scoping, audit,
secrets and Stripe changes:

- verify bearer signatures before trusting claims; rotate and revoke sessions correctly;
- enforce exact redirect URI allowlists, PKCE and state handling;
- scope every business query to tenant/organization and fail closed on missing context;
- distinguish 403 from non-disclosing 404 and test both;
- rate-limit login, registration, reset and OTP; use single-use values and constant-time checks;
- protect cookie flags and session fixation boundaries;
- preserve audit actor and chain integrity without logging secrets;
- verify Stripe signatures before parsing and make event replay idempotent.

For an authentication review, concentrate on OAuth/OIDC redirect and issuer/audience validation,
signature-before-claims ordering, session fixation/rotation/revocation, token replay and MFA
challenge lifecycle. Claims are untrusted until their signature and required context are verified.

For an authorization review, trace permissions and tenant/organization/object ownership through
Resource, handler and repository, including list, bulk, export and background paths. Check missing
context, stale membership, cross-organization identifiers and direct object access. UI visibility
is not authorization. Keep the scopes distinct; escalate an observed overlapping risk with evidence.

Trace each control from Resource/security configuration through handler and repository.
Every finding states an attacker path, impact, fix and regression test. Inspect relevant denial
test results, dependency audits, firewall/config inspection and static-gate evidence. Read-only
reviewers request fresh checks from the parent when commands would write state. State what could
not be verified. Fixes require a separate assigned implementation scope.
