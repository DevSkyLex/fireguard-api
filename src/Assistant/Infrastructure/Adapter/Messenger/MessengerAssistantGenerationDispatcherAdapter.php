<?php

declare(strict_types=1);

namespace Assistant\Infrastructure\Adapter\Messenger;

use Assistant\Application\Port\Outbound\AssistantGenerationDispatcherPort;
use Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter MessengerAssistantGenerationDispatcherAdapter.
 *
 * Mirrors `Webhook\Infrastructure\Adapter\Messenger\MessengerWebhookDeliveryQueueAdapter`:
 * dispatches directly onto the raw Symfony Messenger bus (routed to the
 * dedicated `assistant` transport by `config/packages/messenger.yaml`)
 * rather than through `CommandBusPort`, which expects a `HandledStamp` that
 * an async-routed dispatch never produces. Replaces L2.1's
 * `NullAssistantGenerationDispatcherAdapter` no-op stub.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessengerAssistantGenerationDispatcherAdapter implements AssistantGenerationDispatcherPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessageBusInterface $messageBus the message bus value
   */
  public function __construct(
    private MessageBusInterface $messageBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method enqueue.
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
  ): void {
    $this->messageBus->dispatch(new GenerateAssistantReplyCommand(
      organizationId: $organizationId,
      threadId: $threadId,
      userMessageId: $userMessageId,
      assistantMessageId: $assistantMessageId,
      model: $model,
      temperature: $temperature,
    ));
  }
  // #endregion
}
