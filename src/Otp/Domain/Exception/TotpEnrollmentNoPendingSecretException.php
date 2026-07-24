<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception TotpEnrollmentNoPendingSecretException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentNoPendingSecretException extends DomainException
{
  // #region Methods
  /**
   * Method forUser.
   *
   * @static
   *
   * Creates a new exception for a user with no pending TOTP secret to confirm.
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
      message: sprintf('No pending TOTP enrollment for user "%s".', $userId),
    );
  }
  // #endregion
}
