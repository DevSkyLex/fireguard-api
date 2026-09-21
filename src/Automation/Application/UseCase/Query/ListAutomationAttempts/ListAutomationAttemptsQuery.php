<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Query\ListAutomationAttempts;

use Shared\Application\Message\QueryMessage;

final readonly class ListAutomationAttemptsQuery implements QueryMessage
{
  public function __construct(public string $actorUserId, public string $organizationId, public int $page = 1, public int $itemsPerPage = 30, public ?string $attemptId = null)
  {
  }
}
