<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\ArchiveConversation;

use Messaging\Application\Contract\Conversation\ConversationView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ArchiveConversationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ArchiveConversationResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ConversationView $conversation the conversation view
   */
  public function __construct(public ConversationView $conversation)
  {
  }
}
