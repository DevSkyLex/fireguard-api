<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation;

use Messaging\Application\Contract\Conversation\ConversationView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrCreateConversationResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrCreateConversationResult implements ResultMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ConversationView $conversation the conversation view
   * @param ?string $subjectLabel the resolved subject display label
   */
  public function __construct(
    public ConversationView $conversation,
    public ?string $subjectLabel,
  ) {
  }
}
