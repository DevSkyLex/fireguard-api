<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Exception EmailChangeRequestNotFoundException.
 *
 * Raised when an email change confirmation token does not resolve to a
 * usable pending request. The message is identical for an unknown, an
 * expired and an already-used token, so the confirm endpoint does not
 * reveal which check failed.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailChangeRequestNotFoundException extends DomainException
{
  // #region Methods
  /**
   * Method invalidToken.
   *
   * @static
   *
   * Creates the neutral refusal for a token that is unknown, expired
   * or already used.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function invalidToken(): self
  {
    return new self(message: 'Invalid or expired email change token.');
  }
  // #endregion
}
