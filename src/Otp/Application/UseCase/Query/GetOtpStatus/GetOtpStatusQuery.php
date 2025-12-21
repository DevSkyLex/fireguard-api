<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\GetOtpStatus;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetOtpStatusQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOtpStatusQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOtpStatusQuery class.
   *
   * @since 1.0.0
   *
   * @param string $otpId the OTP ID
   */
  public function __construct(
    public readonly string $otpId,
  ) {
  }
  // #endregion
}
