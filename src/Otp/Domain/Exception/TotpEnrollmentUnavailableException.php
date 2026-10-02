<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Exception TotpEnrollmentUnavailableException.
 *
 * @category Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TotpEnrollmentUnavailableException extends DomainException
{
  // #region Methods
  /**
   * Method forAccount.
   *
   * @access public
   *
   * @return self the neutral enrollment refusal for an unavailable account
   */
  public static function forAccount(): self
  {
    return new self('Authenticator enrollment is unavailable for this account.');
  }
  // #endregion
}
