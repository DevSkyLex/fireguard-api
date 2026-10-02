<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Exception TotpEnrollmentAlreadyActiveException.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentAlreadyActiveException extends DomainException
{
  // #region Methods
  /**
   * Method forUser.
   *
   * Refuses replacement until the current factor has been disabled with its proof of possession.
   *
   * @since 1.0.0
   *
   * @return self the created exception
   */
  public static function forUser(): self
  {
    return new self('Disable the current authenticator with its current code before starting a new enrollment.');
  }
  // #endregion
}
