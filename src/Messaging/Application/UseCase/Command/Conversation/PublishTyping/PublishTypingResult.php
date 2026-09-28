<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Command\Conversation\PublishTyping;

use Shared\Application\Message\ResultMessage;

/** The acting member whose typing state was announced. */
final readonly class PublishTypingResult implements ResultMessage
{
  public function __construct(public string $memberId)
  {
  }
}
