<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\RetryAutomationAttempt;

use Automation\Application\Contract\Run\AutomationAttemptView;
use Shared\Application\Message\ResultMessage;

final readonly class RetryAutomationAttemptResult implements ResultMessage
{
  public function __construct(public AutomationAttemptView $attempt)
  {
  }
}
