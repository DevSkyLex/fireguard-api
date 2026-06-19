<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception PlanKeyAlreadyExistsException.
 *
 * Raised when creating or updating a plan with a key already used by another
 * plan. Mapped to HTTP 409 at the API boundary.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanKeyAlreadyExistsException extends RuntimeException
{
  // #region Methods
  /**
   * Method withKey.
   *
   * Creates an exception for a duplicate plan key.
   *
   * @since 1.0.0
   *
   * @param string $key the conflicting plan key
   *
   * @return self the exception instance
   */
  public static function withKey(string $key): self
  {
    return new self(sprintf('Plan with key "%s" already exists.', $key));
  }
  // #endregion
}
