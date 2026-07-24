<?php

declare(strict_types=1);

namespace Approval\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception DeferredActionNoLongerApplicableException.
 *
 * Raised when an approved deferred action can no longer be re-executed
 * because its subject changed state since the request was created (e.g. the
 * non-conformity was reopened/closed, or the equipment was already
 * decommissioned or removed). The approve handler catches this, transitions
 * the request to `cancelled`, and lets the processor map it to HTTP 409.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DeferredActionNoLongerApplicableException extends RuntimeException
{
  // #region Methods
  /**
   * Method becauseSubjectChanged.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $reason the underlying conflict reason
   *
   * @return self the exception instance
   */
  public static function becauseSubjectChanged(string $reason): self
  {
    return new self(sprintf('The deferred action can no longer be applied: %s', $reason));
  }
  // #endregion
}
