# Organization access and isolation

Organization owns contextual membership, policy and business permissions; identity remains auth-owned. Cross-database context travels through published application contracts.

**Authoritative references:** [Organization](../../src/Organization/MODULE.md) · [Authorization](../../src/Authorization/MODULE.md) · [Security](../../SECURITY.md).

## Contextual authorization

Authenticate first, then require the organization/member/permission appropriate to
the operation. Item, collection and total queries use the same scope. Cross-tenant
or cross-organization identifiers cannot turn a denied item into a disclosed record.
Published capability projections help the frontend compose actions; mutation-time
checks remain authoritative.

## Admission and discovery

Organization's module contract defines domain verification, invitation, admission
and policy-version behavior. Keep personal identity and organization membership
separate. Discovery eligibility does not redefine ordinary registration or explicit
invitation rules. Uploaded lists/resources are maintained under their documented
provenance and licenses.

## Public seams

Business modules consume Organization application ports/contracts rather than its
Doctrine records. Workload owns capacity, Billing owns Stripe projection and the
owning operational modules enforce quotas transactionally. No ORM join or transaction
spans auth and main. Denial-path tests cover missing access and hidden records.
