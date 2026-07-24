# Assistant Module

## Overview

Assistant lets an organization member hold a private, AI-assisted
conversation ("thread") scoped to their own organization membership. A
thread belongs to exactly one member: **there is no shared/organization-wide
assistant thread in this design** — every use case asserts both the
organization AND the requesting actor before returning a thread, and a
mismatch on either resolves to a 404 (information hiding), never a 403.

Asking a question persists the user's message (created already `complete`)
and a placeholder `assistant`-authored reply (created `pending`) for the
same turn, then enqueues generation onto the dedicated `assistant` Messenger
transport. The consuming worker
(`GenerateAssistantReplyHandler`) calls Ollama with `stream: true`,
republishing every fragment to Mercure as it arrives, and drives the
message through `pending -> streaming -> complete|failed`.

## Status: L2.2 — business-context injection wired (on top of L2.3's Ollama pipeline)

Lot **L2.0** scaffolded this module's touchpoints (autoload, Doctrine
mapping, `main`-database tables). Lot **L2.1** built the full member-private
chat surface (domain aggregates, ports, use cases, Doctrine repositories,
the four API Platform endpoints) but stopped short of ever calling Ollama —
`AssistantGenerationDispatcherPort` was bound to a no-op stub
(`NullAssistantGenerationDispatcherAdapter`), so an assistant reply stayed
`pending` forever. **L2.3** replaced that stub: it added the Ollama HTTP
adapter (streaming), the async worker that consumes generation and drives
the message through `streaming` to `complete`/`failed`, a THIRD Mercure
topic scheme for live fragment streaming, and a subscription endpoint that
mints a scoped subscriber JWT. L2.3 left one seam open: business-context
injection into the prompt (`assistant.context_provider`), documented as an
empty hook.

**L2.2 (this lot, landing after L2.3) fills that seam.** It adds the
`assistant.context_provider` tagged-iterator port, the plain
`Application/Contract/Context` DTOs, `AssistantContextAssembler`, and three
launch adapters hosted in the OWNING modules (Compliance, Inspection,
Maintenance) — see "The business-context injection seam" below. Adding a
FOURTH context source (or a fifth, or a tenth) requires **zero edits to
this module**: only a new adapter, implementing `AssistantContextProviderPort`,
tagged `assistant.context_provider` in its own module's
`config/modules/<module>.yaml`.

## API Endpoints

| Method | Path | Description | Permission |
| --- | --- | --- | --- |
| GET | `/api/organizations/{organizationId}/assistant/threads` | List the requesting member's OWN threads, most recently active first | `organization.assistant.use` |
| POST | `/api/organizations/{organizationId}/assistant/threads` | Start a new thread (optional tenant `model` override, allowlist-validated) | `organization.assistant.use` |
| GET | `/api/organizations/{organizationId}/assistant/threads/{threadId}` | Get a thread together with a page of its messages, oldest first | `organization.assistant.use` |
| POST | `/api/organizations/{organizationId}/assistant/threads/{threadId}/messages` | Ask a question: persists the user message + a pending assistant reply, enqueues generation (optional per-question `temperature`) | `organization.assistant.use` |
| GET | `/api/organizations/{organizationId}/assistant/threads/{threadId}/subscription` | Mint a Mercure subscriber JWT scoped to ONE thread's generation stream | `organization.assistant.use` |

Every operation requires `ROLE_USER` at the resource level (API Platform
`security: "is_granted('ROLE_USER')"`); the `organization.assistant.use`
permission check is self-enforced in the application layer by each handler
through `OrganizationAuthorizationPort::assertGrantedPermissions()` —
processors/providers stay thin. `config/packages/security.yaml`'s existing
catch-all `{ path: ^/api, roles: ROLE_USER }` already covers every route
above; no security.yaml change was needed for this lot either.

The subscription endpoint re-runs `GetAssistantThreadHandler` (through the
query bus, `messagesItemsPerPage: 1`) before ever minting a token — the JWT
is only issued after the exact same authorization a normal `GET` on the
thread would apply (mirrors `Messaging`'s `GetMessagingSubscriptionProvider`
/ `Notification`'s `GetMercureSubscriptionProvider`).

**Deferred org-level gate:** endpoints are gated on the
`organization.assistant.use` permission ONLY. `OrganizationSettings.assistant.enabled`
is still **not checked** — unchanged since L2.1, see "Deferred cross-module
work" below.

## Architecture

- **Domain** (`src/Assistant/Domain`):
  - `Model/Thread/AssistantThread` — `start()` now accepts an optional
    tenant-selected `?string $model` (added as the LAST parameter so every
    existing positional-argument call site stays valid).
  - `Model/Message/AssistantMessage` — unchanged state machine (built in
    L2.1, first actually driven end-to-end by this lot).
  - `Service/AssistantModelPolicy` (**new**) — the `OLLAMA_ALLOWED_MODELS`
    allowlist gate; empty allowlist denies everything.
  - `Event/Message/AssistantReplyGeneratedEvent` (**new**) — dispatched by
    `GenerateAssistantReplyHandler` on a successful `complete` outcome only
    (a `failed` outcome is observable via the message's own status/error
    code and the handler's log entry, no event).
  - `Exception/AssistantValidationException::modelNotAllowed()` (**new**
    factory).
- **Application** (`src/Assistant/Application`):
  - `Contract/Generation/AssistantGenerationOutcome` — the outcome
    of one streamed generation attempt (mirrors `Webhook\Application\Contract\Http\WebhookHttpResponse`);
    never thrown, always returned.
  - `Contract/Context/{AssistantContextScope, AssistantContextBudget,
    AssistantContextFragment}` (**new**, L2.2) — see "The business-context
    injection seam" above.
  - Outbound ports: `AssistantGenerationClientPort` (the Ollama
    streaming call), `AssistantRealtimePublisherPort` (the Mercure
    fan-out), `AssistantGenerationDispatcherPort` (signature extended with
    `?model`/`?temperature`, bound to a real adapter),
    `AssistantContextProviderPort` (**new**, L2.2, tagged-iterator seam —
    NOT a single alias; see the ports table below),
    `Organization\AssistantOrganizationSettingsPort` (now bound, L2.2 —
    see "Deferred cross-module work").
  - `Service/AssistantPromptBuilder` (`build()`'s signature extended with
    `organizationId`/`AssistantContextScope`/`bool $includeBusinessContext`,
    L2.2) — see "The business-context injection seam" above.
  - `Service/AssistantContextAssembler` (**new**, L2.2) — see "The
    business-context injection seam" above.
  - Use cases: `UseCase/Command/Message/GenerateAssistantReply` —
    the async worker (constructor now also takes
    `AssistantOrganizationSettingsPort`, L2.2); `StartAssistantThread`/`AskAssistantQuestion`
    carry `model`/`temperature` through to the dispatcher.
- **Infrastructure** (`src/Assistant/Infrastructure`):
  - `Adapter/Http/OllamaGenerationClientAdapter` (**new**) — calls Ollama's
    `/api/chat` with `stream: true`, parses the NDJSON streamed response via
    `symfony/http-client`'s chunked iteration, never throws.
  - `Adapter/Messenger/MessengerAssistantGenerationDispatcherAdapter`
    (**new**, replaces `NullAssistantGenerationDispatcherAdapter`, now
    deleted) — dispatches `GenerateAssistantReplyCommand` onto the raw
    `@messenger.default_bus` (never `CommandBusPort` — that port requires a
    `HandledStamp` an async dispatch never produces; mirrors
    `MessengerWebhookDeliveryQueueAdapter`).
  - `Adapter/Realtime/MercureAssistantRealtimePublisherAdapter` (**new**) —
    see "The Mercure topic scheme" above.
  - **L2.2 added NO adapter under `src/Assistant/Infrastructure`.** All three
    launch context providers, AND `AssistantOrganizationSettingsPort`'s
    adapter, live in their OWNING modules (`Compliance`/`Inspection`/
    `Maintenance`/`Organization`) — exactly the point of the seam: Assistant
    only ever depends on its own port + the `Application\Contract\Context`
    DTOs, never on a provider's concrete class.
- **Presentation** (`src/Assistant/Presentation`):
  - `Validator/ValidAssistantModel/` (**new**) — the UX-only allowlist
    check, delegating to `AssistantModelPolicy` (mirrors
    `Webhook\Presentation\Api\Validator\ValidWebhookUrl`).
  - `Dto/Output/AssistantThreadSubscriptionOutput` (**new**).
  - `Provider/GetAssistantThreadSubscriptionProvider` (**new**).
  - `StartAssistantThreadInput::$model`, `AskAssistantQuestionInput::$temperature`
    (**new** properties).

### Ports & adapters (`config/modules/assistant.yaml`)

| Port | Adapter | Hosted in | Status |
| --- | --- | --- | --- |
| `AssistantThreadRepositoryPort` | `AssistantThreadRepository` | Assistant | bound |
| `AssistantMessageRepositoryPort` | `AssistantMessageRepository` | Assistant | bound |
| `AssistantGenerationDispatcherPort` | `MessengerAssistantGenerationDispatcherAdapter` | Assistant | bound (L2.3, replaces the L2.1 stub) |
| `AssistantGenerationClientPort` | `OllamaGenerationClientAdapter` | Assistant | bound (L2.3) |
| `AssistantRealtimePublisherPort` | `MercureAssistantRealtimePublisherAdapter` | Assistant | bound (L2.3) |
| `Organization\AssistantOrganizationSettingsPort` | `OrganizationAssistantSettingsAdapter` | Organization | bound (L2.2) |
| `AssistantContextProviderPort` (tagged `assistant.context_provider`, fan-out — not a single alias) | `ComplianceAssistantContextProviderAdapter` (priority 30) | Compliance | bound (L2.2) |
| ″ | `InspectionAssistantContextProviderAdapter` (priority 20) | Inspection | bound (L2.2) |
| ″ | `MaintenanceAssistantContextProviderAdapter` (priority 10) | Maintenance | bound (L2.2) |

## The message status state machine (the crux)

`Assistant\Domain\ValueObject\AssistantMessageStatus` (`pending` |
`streaming` | `complete` | `failed`) is enforced by
`Assistant\Domain\Model\Message\AssistantMessage`, which is the only place
allowed to mutate it (`canTransitionTo()` is the single source of truth):

```
PENDING --> STREAMING --> COMPLETE
   |                          ^
   `------> FAILED <----------'
```

- A `user`-authored message is `AssistantMessage::askUser()` — status
  `complete` immediately.
- An `assistant`-authored reply is `AssistantMessage::pendingReply()` —
  status `pending`, body empty, the moment generation is enqueued.
- `pending -> streaming` (`markStreaming()`): called by
  `GenerateAssistantReplyHandler` the moment the FIRST content fragment
  arrives from Ollama — never called preemptively before any token exists.
- `streaming -> complete` (`markComplete()`): **REPLACES** the current body
  (never appends).
- `pending -> failed` / `streaming -> failed` (`markFailed()`): legal from
  either — the backend can be unreachable before any token (`pending`), or
  fail mid-reply (`streaming`).
- `complete`/`failed` are terminal.

### The retry-safety contract (both layers)

The `assistant` Messenger transport's `retry_strategy.max_retries: 1` means a
transient failure in `GenerateAssistantReplyHandler` can run the SAME
generation command a second time. Two independent guarantees make that safe:

1. **Persistence layer** — `GenerateAssistantReplyHandler` checks
   `$message->status()->isTerminal()` first and no-ops if already
   `complete`/`failed` (mirrors `DeliverWebhookHandler`). If the message is
   already `streaming` (a previous attempt crashed after the first token but
   before settling), the handler does **not** re-call `markStreaming()` —
   that would throw (`AssistantMessageIllegalStatusTransitionException`,
   since the state machine only allows `pending -> streaming`) — it simply
   restarts generation and lets the eventual `markComplete()`/`markFailed()`
   REPLACE the row.
2. **Mercure layer** — every published fragment
   (`AssistantRealtimePublisherPort::publishGenerationEvent()`) carries the
   **FULL accumulated reply body so far**, never an incremental delta. A
   retry that restarts streaming from token 1 republishes a growing sequence
   of snapshots; the client only ever REPLACES its displayed text with the
   latest event, so replayed fragments can, at worst, repeat frames already
   rendered — never producing user-visible duplicated content. This is what
   actually defuses the failure mode called out for this lot: *"a
   partially-streamed reply that Messenger retries republishes its
   fragments and the user sees the answer twice."*

Ollama being unreachable, timing out, returning a non-2xx status, or
returning an empty response are all reported through
`AssistantGenerationOutcome` (never an unhandled exception from the port),
and settle the message `failed` with a stable `errorCode` (`ollama_unreachable`,
`ollama_timeout`, `ollama_http_error`, `ollama_stream_error`,
`ollama_empty_response`, plus `assistant_thread_not_found` and
`ollama_model_not_configured` for the handler's own guard clauses).

## The operator-vs-tenant configuration boundary

- **Operator-only** (never influenced by any tenant/organization value):
  `OLLAMA_BASE_URL` (the Ollama server address — the same trust class as
  `MAILER_DSN`), `OLLAMA_HTTP_TIMEOUT`, `OLLAMA_DEFAULT_MODEL`. These are
  read only via `#[Autowire('%env(...)%')]` in
  `OllamaGenerationClientAdapter`/`GenerateAssistantReplyHandler`. No
  organization setting, DTO, or command has ever been able to reach any of
  these values.
- **Tenant-controlled** (the ONLY two generation inputs a member can steer):
  - `model` — `StartAssistantThreadInput::$model`, set once at thread
    creation, persisted on `AssistantThread`, and re-used for every
    generation on that thread unless a question overrides it. Validated
    against `OLLAMA_ALLOWED_MODELS` (`Assistant\Domain\Service\AssistantModelPolicy`)
    at BOTH the API boundary (`ValidAssistantModelValidator`, UX-only) and
    the authoritative handler gate (`StartAssistantThreadHandler` re-runs
    `AssistantModelPolicy::assertAllowed()`) — mirrors
    `Webhook\Domain\Service\WebhookUrlPolicy`'s dual-gate pattern. **An empty
    allowlist denies every tenant-supplied model** rather than permitting
    any — the operator must explicitly opt in.
  - `temperature` — `AskAssistantQuestionInput::$temperature`, optional,
    per-question, never persisted, range-validated `0.0`-`2.0`.
- Deliberately **not** reused: `Webhook\Domain\Service\WebhookUrlPolicy`. It
  is a private-IP SSRF denylist built for a TENANT-supplied URL (a webhook
  subscription's target), and would incorrectly reject `http://localhost:11434`.
  More fundamentally, no URL policy belongs on the Ollama call at all: the
  backend address is operator deployment config, not tenant input.

## The Mercure topic scheme (a THIRD scheme, never reused)

`Assistant\Infrastructure\Adapter\Realtime\MercureAssistantRealtimePublisherAdapter::topic()`
builds `/organizations/{organizationId}/assistant/threads/{threadId}` —
deliberately never a wildcard, and deliberately its OWN scheme, distinct
from:

- Messaging's `/organizations/{organizationId}/conversations/{conversationId}`
- Notification's `/users/{userId}/notifications`

An already-issued subscriber JWT for either of those topics must never be
able to read an assistant generation stream, so this module owns its own
topic namespace and its own subscription-minting endpoint
(`GetAssistantThreadSubscriptionProvider`), rather than extending an
existing one.

Published event payload (JSON):

```json
{
  "messageId": "...",
  "status": "streaming|complete|failed",
  "body": "the FULL accumulated reply text so far",
  "tokenCount": null,
  "errorCode": null
}
```

## Deferred cross-module work

1. **`settings.assistant.enabled` gate.** `Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort::isEnabledFor()`
   is bound (L2.2, to `Organization\Infrastructure\Adapter\Assistant\OrganizationAssistantSettingsAdapter`)
   but still **not called** by any handler/processor: every Assistant
   endpoint still gates on the `organization.assistant.use` permission ONLY.
   Only the port's OTHER method, `includeBusinessContextFor()`, is consumed
   so far (by `GenerateAssistantReplyHandler`, for the business-context seam
   below).
2. **Member identity.** `memberId` is still the authenticated user's id, not
   a resolved `OrganizationMemberId`.

## The business-context injection seam (`assistant.context_provider`, L2.2)

`Assistant\Application\Service\AssistantPromptBuilder::build()` assembles
the chat message list sent to Ollama: a fixed system prompt, then
`buildContextBlocks()`, then the thread's own completed transcript, oldest
first. `buildContextBlocks()` now delegates to `AssistantContextAssembler`
when the CALLER indicates business context is wanted — it is a private
method with no external callers of its own, so this stayed additive.

**Why server-side pre-injection, not LLM tool-calling.** The mockup's
assistant suggestions ("List the open non-conformities", "What's blocking
the campaign?") require truthful, current organization data the thread
transcript alone can never contain. Tool-calling would let a small local
model hallucinate a tool invocation or its arguments; pre-injecting a
deterministic, server-rendered text block is auditable (the exact bytes sent
to Ollama are reconstructable from `organizationId` + `includeBusinessContext`
alone) and immune to that failure mode.

**The seam, end to end:**

1. `Assistant\Application\Port\Outbound\AssistantContextProviderPort` —
   `supports(organizationId, scope): bool` (a cheap readiness/permission
   check) + `provide(organizationId, scope, budget): AssistantContextFragment`
   (the actual fetch+render). A direct clone of
   `Messaging\Application\Port\Outbound\MessagingSubjectResolverPort`'s
   hosting convention (each provider module owns one adapter under its own
   `Infrastructure/Adapter/Assistant/`, tagged in its own `config/modules/
   <module>.yaml`) — but a FAN-OUT, not a routed-to-one lookup: every
   `supports()`-true provider contributes, mirroring
   `Notification\Application\Port\Outbound\InboxSourceProviderPort`/`InboxAggregator`.
2. `Application\Contract\Context\{AssistantContextScope, AssistantContextBudget,
   AssistantContextFragment}` — plain DTOs, deliberately never a provider
   module's Domain object. `AssistantContextScope` carries `actorUserId` (the
   thread's OWNING member — see "Member identity" above) and `threadId`.
3. `Assistant\Application\Service\AssistantContextAssembler` — the tagged-
   iterator consumer (`!tagged_iterator assistant.context_provider`,
   `config/modules/assistant.yaml`). Ordering is an explicit `priority`
   attribute on each adapter's OWN tag registration (Compliance `30` >
   Inspection `20` > Maintenance `10` — higher runs, and renders, first) —
   the assembler itself never reorders, so its own unit test
   (`AssistantContextAssemblerTest`) can pin ordering by simply constructing
   it with an array literal, without booting the DI container.
4. **The two hard rules (both enforced in the assembler, not trusted from a
   provider):**
   - **Budget.** Each provider receives the REMAINING character budget as a
     soft hint (`AssistantContextBudget::$remainingCharacters`), but
     `AssistantContextAssembler::truncate()` ALWAYS hard-caps whatever text
     comes back to what is actually left — a provider that ignores the hint
     entirely can never blow the model's context window or push the real
     conversation out of the prompt. Once the running budget hits zero, no
     further provider is even called.
   - **Permission.** A context block is not a bypass: every launch adapter
     checks the asking member's OWN permission for the underlying data
     (`organization.compliance.read`+dependencies,
     `organization.inspection.read`, `organization.maintenance.read`) in
     `supports()`, so a denied actor's assembled prompt simply omits that
     provider's block, exactly like the equivalent JSON endpoint would deny
     them.
   - **Resilience.** `AssistantContextAssembler` wraps every provider call
     (`supports()` + `provide()`) in a single `try`/`catch (Throwable)`: a
     failing or slow provider degrades to "contributed nothing", logged at
     `error`, never failing the question.
5. **The org-level opt-in gate.** `GenerateAssistantReplyHandler` resolves
   `AssistantOrganizationSettingsPort::includeBusinessContextFor($organizationId)`
   (fail-closed to `false` on any exception) BEFORE calling
   `AssistantPromptBuilder::build()`; when `false`, `buildContextBlocks()`
   returns `[]` without ever touching the assembler — an organization that
   has not opted in triggers ZERO `assistant.context_provider` calls, not
   merely an empty result from them.
6. **The three launch adapters** (each hosted in the OWNING module, per this
   repo's cross-module convention — see the house rule "a cross-module
   adapter lives in the PROVIDER module"):
   - `Compliance\Infrastructure\Adapter\Assistant\ComplianceAssistantContextProviderAdapter`
     — the organization-wide compliance summary (status rollup, per-status
     facility counts, tracked-equipment breakdown, open-non-conformity
     counts by severity), reusing `ComplianceRegisterAggregator`/
     `ComplianceStatusPolicy` verbatim (Compliance's own services — the SAME
     aggregate the `GET /compliance` endpoint serves). Priority `30`.
   - `Inspection\Infrastructure\Adapter\Assistant\InspectionAssistantContextProviderAdapter`
     — the organization's open non-conformities (feeds "List the open
     non-conformities"): the exact total from
     `NonConformityRepositoryPort::countOverviewByOrganizationId()`
     (already-tested), plus up to 8 rows (description, severity, status,
     due date) from a DEDICATED DQL query directly against
     `NonConformityRecord` (no equivalent exists on the repository port),
     most severe first. Priority `20`. **The only launch adapter with new
     non-trivial DQL** — covered by a REAL integration test
     (`tests/Integration/Inspection/.../InspectionAssistantContextProviderAdapterTest`)
     that executes it against the database, per this repo's "a test that
     mocks a QueryBuilder never parses the DQL" rule.
   - `Maintenance\Infrastructure\Adapter\Assistant\MaintenanceAssistantContextProviderAdapter`
     — upcoming due dates (feeds "What's blocking the campaign?"): overdue +
     due-soon totals and up to 5 rows of each, soonest first, reusing
     `MaintenanceScheduleRepositoryPort::list()` verbatim — no new DQL, so no
     dedicated integration test was needed for it.
   - All three: never throw (an internal failure returns
     `AssistantContextFragment::empty()`), and all three are organization-
     scoped by construction (every underlying query/aggregate is already
     `organizationId`-filtered).

## Persistence

Unchanged from L2.0/L2.1 — no migration was added by this lot either. See
the previous lots' notes; tables are `assistant_threads`/`assistant_messages`
(**main** database), migration `Version20260718124213`.

## Configuration

- Service wiring: `config/modules/assistant.yaml` — a dedicated
  `assistant.http_client` factory service (mirrors `webhook.http_client`;
  there is no `framework.http_client` block in this project), the Ollama
  adapter, the Messenger dispatcher adapter (`$messageBus: '@messenger.default_bus'`),
  the Mercure adapter (`$hub: '@mercure.hub.default'`),
  `AssistantModelPolicy` (`$allowedModels: '%env(csv:OLLAMA_ALLOWED_MODELS)%'`),
  the `AssistantOrganizationSettingsPort` alias to Organization's adapter
  (L2.2), and `AssistantContextAssembler`'s `$providers: !tagged_iterator
  assistant.context_provider` (L2.2) — the three provider adapters
  themselves are registered+tagged in THEIR OWN
  `config/modules/{compliance,inspection,maintenance}.yaml`, never here.
- Doctrine mapping (main entity manager): `config/packages/doctrine.yaml`
  (unchanged, from L2.0).
- Messenger: `config/packages/messenger.yaml`'s `assistant` transport (from
  L2.1) now has a routing entry —
  `Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand: assistant`
  — and its first real consumer, `GenerateAssistantReplyHandler`. Transport
  config unchanged: `max_retries: 1`, `failure_transport: failed`.
- `.env.example`: the `###> assistant ###` block (from L2.1) already
  declares all four variables this lot reads — `OLLAMA_BASE_URL`,
  `OLLAMA_HTTP_TIMEOUT`, `OLLAMA_DEFAULT_MODEL`, `OLLAMA_ALLOWED_MODELS` — no
  new variable was introduced.
  - `.env` and `.env.test` still need these four keys mirrored in by hand
    (guarded as secret/environment files in this sandbox); `bin/console lint:container`
    does not require actual values (env vars are resolved lazily, not at
    compile time, confirmed against this project's `ContainerLintCommand`
    usage without `--resolve-env-vars`) and none of this lot's own tests
    boot the full container, so this is safe to defer, but a real worker
    deployment needs real values.

## Testing

- Unit: `tests/Unit/Assistant` — everything from L2.1, plus (L2.3):
  `Domain/Service/AssistantModelPolicyTest`, `Application/Service/AssistantPromptBuilderTest`,
  `Application/UseCase/Command/Message/GenerateAssistantReply/GenerateAssistantReplyHandlerTest`
  (the retry-replaces-not-appends crux, both at the persistence layer and
  the Mercure-fragment layer), `Infrastructure/Adapter/Http/OllamaGenerationClientAdapterTest`
  (against `symfony/http-client`'s `MockHttpClient`/`MockResponse` — no
  running Ollama required), `Infrastructure/Adapter/Messenger/MessengerAssistantGenerationDispatcherAdapterTest`,
  `Infrastructure/Adapter/Realtime/MercureAssistantRealtimePublisherAdapterTest`,
  `Presentation/Api/Validator/ValidAssistantModel/ValidAssistantModelValidatorTest`
  (a model outside `OLLAMA_ALLOWED_MODELS` is rejected at the boundary),
  `Presentation/Api/Provider/GetAssistantThreadSubscriptionProviderTest`.
  `NullAssistantGenerationDispatcherAdapterTest` was **deleted** along with
  the stub it tested. Plus (L2.2): `Application/Service/AssistantContextAssemblerTest`
  (ordering-by-array-order, hard budget truncation regardless of what a
  provider returns, stop-calling-once-exhausted, resilience — a throwing
  provider degrades to "contributed nothing" and is logged),
  `Application/Service/AssistantPromptBuilderTest` (extended: the assembler
  is never even called when `$includeBusinessContext` is `false`; its
  blocks are inserted as `system` messages, before the transcript, when
  `true`), `Application/UseCase/Command/Message/GenerateAssistantReply/GenerateAssistantReplyHandlerTest`
  (extended: two new cases pin the `includeBusinessContextFor()` gate on/off
  around a real `AssistantContextAssembler` + a fake always-supporting
  provider).
- Integration: `tests/Integration/Assistant` — unchanged from L2.1
  (`AssistantThreadRepositoryTest`, `AssistantMessageRepositoryTest`). The
  L2.2 context-provider adapters' own tests live in THEIR OWNING modules —
  see `src/Compliance/MODULE.md`, `src/Inspection/MODULE.md` (the one with
  a real DQL-executing integration test),
  `src/Maintenance/MODULE.md`, and `src/Organization/MODULE.md`
  (`OrganizationAssistantSettingsAdapterTest`, unit-only — no new DQL).
- Functional: `tests/Functional/Api/AssistantThreadApiTest.php` — added an
  authentication-required smoke test for the new subscription endpoint
  (L2.3; unchanged by L2.2 — the business-context seam is exercised through
  the async worker, not a new HTTP surface).
- Run module tests: `php -d memory_limit=1G vendor/bin/phpunit --no-coverage tests/Unit/Assistant tests/Integration/Assistant`
## Error Codes

| Exception / error code | HTTP / meaning |
| --- | --- |
| `Organization\Domain\Exception\OrganizationAccessDeniedException` | 403 Forbidden |
| `AssistantThreadNotFoundException` (also another organization's/member's thread) | 404 Not Found |
| `AssistantMessageIllegalStatusTransitionException` | 409 Conflict |
| `AssistantValidationException` (blank question body, or a model outside `OLLAMA_ALLOWED_MODELS`) | 422 Unprocessable Entity |
| `InvalidArgumentException` | 400 Bad Request |
| `ollama_unreachable` / `ollama_timeout` / `ollama_http_error` / `ollama_stream_error` / `ollama_empty_response` | `AssistantMessage.errorCode`, message settles `failed` |
| `assistant_thread_not_found` / `ollama_model_not_configured` | `AssistantMessage.errorCode` (worker-side guard clauses), message settles `failed` |

