<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

/**
 * Enum OtpPurpose
 *
 * Represents the purpose/context for OTP verification.
 *
 * @category ValueObject
 * @package Otp\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OtpPurpose: string
{
  //#region Cases
  /**
   * Case LOGIN
   *
   * OTP for login verification (2FA).
   */
  case LOGIN = 'login';

  /**
   * Case PASSWORD_RESET
   *
   * OTP for password reset verification.
   */
  case PASSWORD_RESET = 'password_reset';

  /**
   * Case EMAIL_VERIFICATION
   *
   * OTP for email address verification.
   */
  case EMAIL_VERIFICATION = 'email_verification';

  /**
   * Case PHONE_VERIFICATION
   *
   * OTP for phone number verification.
   */
  case PHONE_VERIFICATION = 'phone_verification';

  /**
   * Case SENSITIVE_OPERATION
   *
   * OTP for sensitive operations (transfer, deletion, etc.).
   */
  case SENSITIVE_OPERATION = 'sensitive_operation';

  /**
   * Case TRANSACTION_APPROVAL
   *
   * OTP for transaction approval.
   */
  case TRANSACTION_APPROVAL = 'transaction_approval';
  //#endregion

  //#region Methods
  /**
   * Method getDefaultTtlSeconds
   *
   * Returns the default TTL for this purpose.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int TTL in seconds.
   */
  public function getDefaultTtlSeconds(): int
  {
    return match ($this) {
      self::LOGIN => 300,                    // 5 minutes
      self::PASSWORD_RESET => 900,           // 15 minutes
      self::EMAIL_VERIFICATION => 3600,      // 1 hour
      self::PHONE_VERIFICATION => 300,       // 5 minutes
      self::SENSITIVE_OPERATION => 180,      // 3 minutes
      self::TRANSACTION_APPROVAL => 120,     // 2 minutes
    };
  }

  /**
   * Method getDefaultMaxAttempts
   *
   * Returns the default max attempts for this purpose.
   *
   * @access public
   * @since 1.0.0
   *
   * @return int Max attempts.
   */
  public function getDefaultMaxAttempts(): int
  {
    return match ($this) {
      self::LOGIN => 5,
      self::PASSWORD_RESET => 5,
      self::EMAIL_VERIFICATION => 10,
      self::PHONE_VERIFICATION => 5,
      self::SENSITIVE_OPERATION => 3,
      self::TRANSACTION_APPROVAL => 3,
    };
  }

  /**
   * Method getLabel
   *
   * Returns a human-readable label for this purpose.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string Human-readable label.
   */
  public function getLabel(): string
  {
    return match ($this) {
      self::LOGIN => 'Login 2FA',
      self::PASSWORD_RESET => 'Password Reset',
      self::EMAIL_VERIFICATION => 'Email Verification',
      self::PHONE_VERIFICATION => 'Phone Verification',
      self::SENSITIVE_OPERATION => 'Sensitive Operation',
      self::TRANSACTION_APPROVAL => 'Transaction Approval',
    };
  }
  //#endregion
}
