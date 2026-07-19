<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ApproverNotAuthorizedException.
 *
 * Raised when the deciding member does not satisfy the organization's
 * configured minimum approver role for the action type.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApproverNotAuthorizedException extends RuntimeException
{
  // #region Methods
  /**
   * Method belowMinimumRole.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $minRole the minimum approver role required
   *
   * @return self the exception instance
   */
  public static function belowMinimumRole(string $minRole): self
  {
    return new self(sprintf('Deciding this request requires at least the "%s" role.', $minRole));
  }
  // #endregion
}
