# External integrations and webhooks

Read the owning module contract and, for authentication, signatures or sensitive data,
`SECURITY.md` and the [security checklist](../../fg-api-security-review/references/security-checklist.md).
Keep provider SDK and HTTP types behind an existing or narrowly justified Application port.

Bound connect/read deadlines and response sizes. Translate provider errors into stable
project failures; distinguish transient retryable failures from permanent validation or
authorization failures. Avoid logging credentials, signed payloads or personal data.
Retries require an idempotency contract and bounded backoff, not a blanket retry on every error.

Inbound webhooks verify the signature over the original body before trusting its contents,
validate replay/time-window behavior and persist deduplication with the business outcome.
Outbound side effects respect the module's after-commit/outbox guarantees. Do not assume a
network timeout proves the provider rejected the first attempt.

Test using doubles or local test transports: malformed/oversized responses, timeout, rate
limit, signature failure, duplicate delivery and unknown event types as applicable. Do not
call live providers, send webhooks or provision resources unless the assignment authorizes
those external actions. Report which integration behavior is simulated and which was observed.
