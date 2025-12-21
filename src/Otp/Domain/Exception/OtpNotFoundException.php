<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

use function sprintf;

/**
 * Exception OtpNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpNotFoundException extends EntityNotFoundException
{
  // #region Methods
  /**
   * Method create.
   *
   * @static
   *
   * Creates a new exception for OTP not found.
   *
   * @since 1.0.0
   *
   * @param string $id the OTP ID
   *
   * @return self the created exception
   */
  public static function create(string $id): self
  {
    return new self(
      message: sprintf('Otp with ID "%s" not found.', $id)
    );
  }
  // #endregion
}
