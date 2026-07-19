<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

/**
 * Port AssistantGenerationDispatcherPort.
 *
 * Enqueues the asynchronous generation of a pending assistant reply, once
 * the user message and the placeholder `pending` reply have both been
 * persisted by
 * {@see \Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\AskAssistantQuestionHandler}.
 * Bound to
 * {@see \Assistant\Infrastructure\Adapter\Messenger\MessengerAssistantGenerationDispatcherAdapter},
 * which hands a
 * {@see \Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand}
 * to the dedicated `assistant` Messenger transport
 * (`config/packages/messenger.yaml`), consumed by
 * {@see \Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyHandler},
 * which drives {@see \Assistant\Domain\Model\Message\AssistantMessage}'s
 * `pending -> streaming -> complete|failed` transitions.
 *
 * This exists instead of dispatching a generation command directly through
 * `Shared\Application\Port\Inbound\CommandBusPort`: that adapter requires a
 * `HandledStamp` on the returned envelope, unavailable for a message that
 * will be routed to the async `assistant` transport rather than handled
 * inline — the same reasoning backs `Automation\...\AutomationRuleQueuePort`
 * and `Webhook\Application\Port\Outbound\WebhookDeliveryQueuePort`.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AssistantGenerationDispatcherPort
{
  // #region Methods
  /**
   * Method enqueue.
   *
   * Enqueues the generation of a pending assistant reply. `$model` and
   * `$temperature` are the ONLY tenant-controlled generation inputs
   * (`$model` from the thread's own `AssistantThread::model()`, set once at
   * `StartAssistantThreadHandler` time and validated against
   * `OLLAMA_ALLOWED_MODELS`; `$temperature` supplied per question); when
   * either is null, the consuming handler falls back to operator defaults
   * (`OLLAMA_DEFAULT_MODEL`, a fixed default temperature) — never a
   * tenant-influenced value for anything else, and never the backend URL.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $threadId the owning thread identifier
   * @param string $userMessageId the triggering user message identifier
   * @param string $assistantMessageId the pending assistant reply identifier to fulfill
   * @param ?string $model the thread's tenant-selected model override, if any
   * @param ?float $temperature the tenant-selected sampling temperature, if any
   */
  public function enqueue(
    string $organizationId,
    string $threadId,
    string $userMessageId,
    string $assistantMessageId,
    ?string $model = null,
    ?float $temperature = null,
  ): void;
  // #endregion
}
