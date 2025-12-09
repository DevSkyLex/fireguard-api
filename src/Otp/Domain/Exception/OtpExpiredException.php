<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use Otp\Domain\ValueObject\OtpId;
use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception OtpExpiredException
 * @final
 *
 * Thrown when attempting to verify an expired OTP.
 *
 * @category Exception
 * @package Otp\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpExpiredException extends DomainException
{
  //#region Methods
  /**
   * Method create
   * @static
   *
   * Creates a new exception for an expired OTP.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param OtpId $id The OTP ID.
   *
   * @return self The created exception.
   */
  public static function create(OtpId $id): self
  {
    return new self(
      message: sprintf('OTP "%s" has expired.', $id->value)
    );
  }
  //#endregion
}
