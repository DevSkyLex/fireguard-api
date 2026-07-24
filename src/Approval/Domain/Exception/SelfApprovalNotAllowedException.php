<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

/**
 * Exception SelfApprovalNotAllowedException.
 *
 * Raised when a member attempts to decide on a request they themselves
 * requested, and the organization's approval policy does not allow
 * self-approval — the four-eyes invariant.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SelfApprovalNotAllowedException extends RuntimeException
{
  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function create(): self
  {
    return new self('The requester cannot decide on their own approval request.');
  }
  // #endregion
}
