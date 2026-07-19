<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\FavoriteConversation;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase FavoriteConversationCommand.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FavoriteConversationCommand implements CommandMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation (or channel) id value
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
  ) {
  }
}
