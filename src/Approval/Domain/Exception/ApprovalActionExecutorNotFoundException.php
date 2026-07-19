<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ApprovalActionExecutorNotFoundException.
 *
 * Raised when no tagged `approval.deferred_action_executor` adapter declares
 * support for a request's action type — a wiring defect (a new action type
 * was registered in the policy/catalog without a matching executor).
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalActionExecutorNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method forActionType.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $actionType the action type value
   *
   * @return self the exception instance
   */
  public static function forActionType(string $actionType): self
  {
    return new self(sprintf('No approval executor is registered for action type "%s".', $actionType));
  }
  // #endregion
}
