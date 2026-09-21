<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt;

use Shared\Application\Message\CommandMessage;

/** Command ControlAssistantAttemptCommand. Only the requesting account can control its private reply. */
final readonly class ControlAssistantAttemptCommand implements CommandMessage
{
  public function __construct(public string $actorUserId, public string $organizationId, public string $threadId, public string $messageId, public string $attemptId, public bool $retry)
  {
  }
}
