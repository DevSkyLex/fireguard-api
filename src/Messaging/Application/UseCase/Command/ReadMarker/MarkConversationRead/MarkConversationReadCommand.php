<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase MarkConversationReadCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MarkConversationReadCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation id value
   * @param ?string $lastReadMessageId the last message the member has read, if provided
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public ?string $lastReadMessageId = null,
  ) {
  }
}
