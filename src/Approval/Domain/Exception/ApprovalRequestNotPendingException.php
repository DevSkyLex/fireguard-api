<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ApprovalRequestNotPendingException.
 *
 * Raised when a decision (approve/reject) or the expiry sweep targets a
 * request that has already left the `pending` status — the compare-and-set
 * guard that makes a decision (and its downstream execution) idempotent
 * against a duplicate or racing attempt.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ApprovalRequestNotPendingException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $id the approval request identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Approval request with ID "%s" is no longer pending.', $id));
  }
  // #endregion
}
