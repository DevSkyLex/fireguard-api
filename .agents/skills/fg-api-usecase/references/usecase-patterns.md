# Use cases and durable asynchronous work

Read `AGENTS.md`, `ARCHITECTURE.md`, `.codex/rules/application.md` and the owning
`MODULE.md`. Mirror the closest sibling use case.

Create the message, Handler and typed Result together. Handlers inject Application ports,
coordinate business rules whose invariants remain in Domain, and persist through ports.
Queries do not mutate. Commands that replay safely return the prior result and do not emit
duplicate effects. Use the existing transaction port when durable writes form one invariant.

## Transactions, events and retry

Read the owning module contract and `src/Shared/MODULE.md` before changing async behavior.
For a transactional outbox, business writes and the outbox entry must commit on the same
owning connection in one transaction. Enqueue failure rolls back both. Delivery to consumers
happens after commit; moving the outbox insertion after commit would create a loss window.
Non-outbox side effects must not observe uncommitted state. Preserve explicitly documented
legacy synchronous paths rather than silently promising durable delivery for them.

Messenger and Scheduler orchestration needs bounded retries, classified permanent/transient
failures, an explicit failed destination, and idempotent consumers. Preserve attempt identity,
deduplication receipts and lease ownership across replay, timeout and worker crashes. An expired
worker must not finalize or release a newer attempt. Claiming, rereading under lock and receipt
persistence use the owning database; no transaction spans auth and main. Cross-database consumers
own their independent transaction and receipt. Never assert exactly-once external delivery from
an at-least-once queue without evidence from the downstream idempotency contract.

Test commit/rollback, enqueue failure, duplicate delivery, retry exhaustion, expired lease and
stale-worker behavior when applicable. Do not start real workers or external deliveries during
unit tests; use test transports and doubles, with PostgreSQL integration tests for atomicity.

Keep exceptions in Domain or SharedDomain where appropriate; Presentation maps them.
Cross-module collaboration uses an inbound/outbound port and Application contract, never
another module's Domain or Infrastructure class. Register new aliases and explicit entity
manager wiring in the owning module configuration.

Unit-test success, every failure, exact Result values, port arguments, event order and the
idempotent path. Validate with the focused tests, PHPStan, Deptrac and container lint.
