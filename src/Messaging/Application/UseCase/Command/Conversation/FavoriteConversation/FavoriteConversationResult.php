<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\FavoriteConversation;

use Messaging\Application\Contract\Conversation\ConversationView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase FavoriteConversationResult.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FavoriteConversationResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param ConversationView $conversation the conversation view
   */
  public function __construct(public ConversationView $conversation)
  {
  }
}
