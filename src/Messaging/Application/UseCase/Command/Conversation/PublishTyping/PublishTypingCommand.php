<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\PublishTyping;

use Shared\Application\Message\CommandMessage;

/** Announces a temporary typing state without storing draft content. */
final readonly class PublishTypingCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $conversationId, public bool $active)
  {
  }
}
