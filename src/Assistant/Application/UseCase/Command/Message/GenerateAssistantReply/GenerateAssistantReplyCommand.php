<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\GenerateAssistantReply;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase GenerateAssistantReplyCommand.
 *
 * Routed to the dedicated `assistant` Messenger transport
 * (`config/packages/messenger.yaml`) — dispatched onto the raw
 * `messenger.default_bus` by
 * {@see \Assistant\Infrastructure\Adapter\Messenger\MessengerAssistantGenerationDispatcherAdapter},
 * never through `Shared\Application\Port\Inbound\CommandBusPort` (see
 * {@see \Assistant\Application\Port\Outbound\AssistantGenerationDispatcherPort}'s
 * docblock).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateAssistantReplyCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
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
  public function __construct(
    public string $organizationId,
    public string $threadId,
    public string $userMessageId,
    public string $assistantMessageId,
    public ?string $model = null,
    public ?float $temperature = null,
  ) {
  }
  // #endregion
}
