<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\GetConversation;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetConversationQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetConversationQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation id value
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
  ) {
  }
}
