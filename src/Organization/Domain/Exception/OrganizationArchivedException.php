<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationArchivedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationArchivedException extends RuntimeException
{
  // #region Methods
  /**
   * Method cannotSuspend.
   *
   * Creates an exception for a suspension attempt on an archived organization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotSuspend(): self
  {
    return new self('An archived organization cannot be suspended; restore it first (isActive: true).');
  }
  // #endregion
}
