<?php

declare(strict_types=1);

namespace Automation\Application\UseCase\Command\RetryAutomationAttempt;

use Automation\Application\Contract\Run\AutomationAttemptView;
use Shared\Application\Message\ResultMessage;

/**
 * Class RetryAutomationAttemptResult
 *
 * Carries the result of the RetryAutomationAttemptResult operation.
 *
 * @category UseCase
 */
final readonly class RetryAutomationAttemptResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the RetryAutomationAttemptResult dependencies and state.
   *
   * @access public
   *
   * @param AutomationAttemptView $attempt the attempt
   *
   * @return void
   */
  public function __construct(public AutomationAttemptView $attempt)
  {
  }
  // #endregion
}
