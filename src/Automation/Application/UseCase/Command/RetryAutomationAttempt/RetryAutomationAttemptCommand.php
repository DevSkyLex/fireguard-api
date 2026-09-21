<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\RetryAutomationAttempt;

use Shared\Application\Message\CommandMessage;

final readonly class RetryAutomationAttemptCommand implements CommandMessage
{
  public function __construct(public string $actorUserId, public string $organizationId, public string $runId, public string $attemptId)
  {
  }
}
