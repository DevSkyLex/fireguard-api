<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use function count;
use function explode;
use function is_string;
use function preg_replace;
use function str_repeat;
use function strlen;
use function substr;

/**
 * Enum OtpChannel.
 *
 * Represents the delivery channel for OTP.
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

  // #region Methods
  /**
   * Method requiresDelivery.
   *
   * Returns whether this channel requires active delivery.
   *
   * @since 1.0.0
   *
   * @return bool true if delivery is needed
   */
  public function requiresDelivery(): bool
  {
    return match ($this) {
      self::EMAIL, self::SMS => true,
      self::TOTP => false,
    };
  }

  /**
   * Method getLabel.
   *
   * Returns a human-readable label for this channel.
   *
   * @since 1.0.0
   *
   * @return string human-readable label
   */
  /**
   * Method mask.
   *
   * Masks a recipient for display, according to what this channel carries.
   *
   * Lives on the channel because masking *is* channel-specific, and because more
   * than one caller needs it: the aggregate renders its own recipient, and the
   * anti-enumeration decoy has to mask an address for which no OTP exists.
   * Duplicating the rules would let the two drift, and a decoy that masks
   * differently from a real challenge is not a decoy at all.
   *
   * @since 1.1.0
   *
   * @param string $recipient the raw recipient
   *
   * @return string the masked recipient
   */
  public function mask(string $recipient): string
  {
    return match ($this) {
      self::EMAIL => self::maskEmail(email: $recipient),
      self::SMS => self::maskPhone(phone: $recipient),
      self::TOTP => 'Authenticator App',
    };
  }

  public function getLabel(): string
  {
    return match ($this) {
      self::EMAIL => 'Email',
      self::SMS => 'SMS',
      self::TOTP => 'Authenticator App',
    };
  }

  /**
   * Method maskEmail.
   *
   * Masks an email address.
   *
   * @since 1.1.0
   *
   * @param string $email the email to mask
   *
   * @return string the masked email
   */
  private static function maskEmail(string $email): string
  {
    $parts = explode('@', $email);
    if (2 !== count($parts)) {
      return '***@***';
    }
    $local = $parts[0];
    $domain = $parts[1];
    $maskedLocal = strlen($local) <= 2
      ? str_repeat('*', strlen($local))
      : substr($local, 0, 2) . str_repeat('*', strlen($local) - 2);

    return $maskedLocal . '@' . $domain;
  }

  /**
   * Method maskPhone.
   *
   * Masks a phone number.
   *
   * @since 1.1.0
   *
   * @param string $phone the phone number to mask
   *
   * @return string the masked phone number
   */
  private static function maskPhone(string $phone): string
  {
    $digits = preg_replace('/[^0-9]/', '', $phone);
    $digits = is_string($digits) ? $digits : '';
    if (strlen($digits) < 4) {
      return '****';
    }

    return str_repeat('*', strlen($digits) - 4) . substr($digits, -4);
  }
  // #endregion
}
