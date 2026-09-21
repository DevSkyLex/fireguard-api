<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Query\ListAutomationAttempts;

use Automation\Application\Contract\Run\AutomationAttemptView;
use Shared\Application\Message\ResultMessage;

final readonly class ListAutomationAttemptsResult implements ResultMessage
{
  /**
   * @param list<AutomationAttemptView> $attempts
   */
  public function __construct(public array $attempts, public int $total, public bool $enabled, public bool $canManage)
  {
  }
}
