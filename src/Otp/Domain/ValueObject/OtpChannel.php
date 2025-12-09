<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

/**
 * Enum OtpChannel
 *
 * Represents the delivery channel for OTP.
 *
 * @category ValueObject
 * @package Otp\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OtpChannel: string
{
  //#region Cases
  /**
   * Case EMAIL
   *
   * OTP sent via email.
   */
  case EMAIL = 'email';

  /**
   * Case SMS
   *
   * OTP sent via SMS.
   */
  case SMS = 'sms';

  /**
   * Case TOTP
   *
   * Time-based OTP (authenticator app).
   */
  case TOTP = 'totp';
  //#endregion

  //#region Methods
  /**
   * Method requiresDelivery
   *
   * Returns whether this channel requires active delivery.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if delivery is needed.
   */
  public function requiresDelivery(): bool
  {
    return match ($this) {
      self::EMAIL, self::SMS => true,
      self::TOTP => false,
    };
  }

  /**
   * Method getLabel
   *
   * Returns a human-readable label for this channel.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string Human-readable label.
   */
  public function getLabel(): string
  {
    return match ($this) {
      self::EMAIL => 'Email',
      self::SMS => 'SMS',
      self::TOTP => 'Authenticator App',
    };
  }
  //#endregion
}
