<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\GetConversation;

use Messaging\Application\Contract\Conversation\ConversationView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetConversationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetConversationResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ConversationView $conversation the conversation view
   * @param ?string $subjectLabel the resolved subject display label
   * @param int $unreadCount the acting member's unread count for this conversation
   * @param bool $isFavorite whether the acting member favorited this conversation (L1.5)
   */
  public function __construct(
    public ConversationView $conversation,
    public ?string $subjectLabel,
    public int $unreadCount,
    public bool $isFavorite = false,
  ) {
  }
}
