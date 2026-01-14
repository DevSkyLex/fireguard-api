<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Challenge;

/**
 * Enum OtpChannel.
 *
 * Contract enum for OTP delivery channels across modules.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OtpChannel: string
{
  // #region Cases
  /**
   * Case EMAIL.
   *
   * OTP sent via email.
   */
  case EMAIL = 'email';

  /**
   * Case SMS.
   *
   * OTP sent via SMS.
   */
  case SMS = 'sms';

  /**
   * Case TOTP.
   *
   * Time-based OTP (authenticator app).
   */
  case TOTP = 'totp';
  // #endregion
}
