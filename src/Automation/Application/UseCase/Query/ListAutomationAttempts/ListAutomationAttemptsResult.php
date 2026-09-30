<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Query\ListAutomationAttempts;

use Automation\Application\Contract\Run\AutomationAttemptView;
use Shared\Application\Message\ResultMessage;

/**
 * Class ListAutomationAttemptsResult
 *
 * Carries an organization's automation attempts and the list's count and access state.
 *
 * @category Result
 */
final readonly class ListAutomationAttemptsResult implements ResultMessage
{
  // #region Constructor
  /**
   * @param list<AutomationAttemptView> $attempts
   */
  public function __construct(public array $attempts, public int $total, public bool $enabled, public bool $canManage)
  {
  }
  // #endregion
}
