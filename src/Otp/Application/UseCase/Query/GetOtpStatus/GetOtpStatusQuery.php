<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\GetOtpStatus;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetOtpStatusQuery
 * @final
 *
 * Query to get OTP status.
 *
 * @category Query
 * @package Otp\Application\UseCase\Query\GetOtpStatus
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOtpStatusQuery implements QueryMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * GetOtpStatusQuery class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $otpId The OTP ID.
   */
  public function __construct(
    public readonly string $otpId,
  ) {}
  //#endregion
}
