<?php

declare(strict_types=1);

namespace Automation\Application\Contract\Run;

/** Contract AutomationRetry. Trusted retained inputs for a newly reserved attempt. */
final readonly class AutomationRetry
{
  /**
   * @param array<string, mixed> $triggerPayload
   */
  public function __construct(public AutomationAttemptView $attempt, public array $triggerPayload)
  {
  }
}
