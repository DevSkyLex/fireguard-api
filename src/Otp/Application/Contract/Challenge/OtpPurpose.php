<?php

declare(strict_types=1);

namespace Otp\Application\Contract\Challenge;

/**
 * Enum OtpPurpose.
 *
 * Contract enum for OTP purposes across modules.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OtpPurpose: string
{
  // #region Cases
  /**
   * Case LOGIN.
   *
   * OTP for login verification (2FA).
   */
  case LOGIN = 'login';

  /**
   * Case PASSWORD_RESET.
   *
   * OTP for password reset verification.
   */
  case PASSWORD_RESET = 'password_reset';

  /**
   * Case EMAIL_VERIFICATION.
   *
   * OTP for email address verification.
   */
  case EMAIL_VERIFICATION = 'email_verification';

  /**
   * Case PHONE_VERIFICATION.
   *
   * OTP for phone number verification.
   */
  case PHONE_VERIFICATION = 'phone_verification';

  /**
   * Case SENSITIVE_OPERATION.
   *
   * OTP for sensitive operations (transfer, deletion, etc.).
   */
  case SENSITIVE_OPERATION = 'sensitive_operation';

  /**
   * Case TRANSACTION_APPROVAL.
   *
   * OTP for transaction approval.
   */
  case TRANSACTION_APPROVAL = 'transaction_approval';
  // #endregion
}
