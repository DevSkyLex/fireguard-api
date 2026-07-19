<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception TotpEnrollmentMaxAttemptsException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentMaxAttemptsException extends DomainException
{
  // #region Methods
  /**
   * Method forUser.
   *
   * @static
   *
   * Creates a new exception for a user whose confirmation attempts are exhausted.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   *
   * @return self the created exception
   */
  public static function forUser(string $userId): self
  {
    return new self(
      message: sprintf('Maximum TOTP confirmation attempts exceeded for user "%s".', $userId),
    );
  }
  // #endregion
}
