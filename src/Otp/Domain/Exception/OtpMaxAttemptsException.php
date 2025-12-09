<?php

declare(strict_types=1);

namespace Otp\Domain\Exception;

use Otp\Domain\ValueObject\OtpId;
use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception OtpMaxAttemptsException
 * @final
 *
 * Thrown when maximum verification attempts exceeded.
 *
 * @category Exception
 * @package Otp\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OtpMaxAttemptsException extends DomainException
{
  //#region Methods
  /**
   * Method create
   * @static
   *
   * Creates a new exception for max attempts exceeded.
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
      message: sprintf('Maximum verification attempts exceeded for OTP "%s".', $id->value)
    );
  }
  //#endregion
}
