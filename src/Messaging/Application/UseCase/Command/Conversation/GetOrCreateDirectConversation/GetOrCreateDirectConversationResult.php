<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation;

use Messaging\Application\Contract\Conversation\ConversationView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrCreateDirectConversationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrCreateDirectConversationResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ConversationView $conversation the direct conversation view
   */
  public function __construct(
    public ConversationView $conversation,
  ) {
  }
}
