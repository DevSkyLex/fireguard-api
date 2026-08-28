<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Exception EmailChangeNotAllowedException.
 *
 * Raised when a requested new email address cannot be used as the
 * account's sign-in address. The message is deliberately neutral: it
 * does not say whether the address is taken by another account or is
 * the caller's current address, so the endpoint adds no per-address
 * oracle beyond what public registration already exposes.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailChangeNotAllowedException extends DomainException
{
  // #region Methods
  /**
   * Method emailNotAvailable.
   *
   * @static
   *
   * Creates the neutral refusal for an address that cannot be used,
   * whatever the underlying reason (already registered, or identical
   * to the caller's current address).
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function emailNotAvailable(): self
  {
    return new self(message: 'This email address cannot be used.');
  }
  // #endregion
}
