<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\ArchiveConversation;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase ArchiveConversationCommand.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveConversationCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation id value
   * @param bool $isArchived the requested archived flag
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public bool $isArchived,
  ) {
  }
}
