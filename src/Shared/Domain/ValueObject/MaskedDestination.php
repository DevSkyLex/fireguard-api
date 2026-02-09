<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use function str_repeat;
use function strlen;
use function strpos;
use function substr;

/**
 * ValueObject MaskedDestination.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaskedDestination
{
  // #region Methods
  /**
   * Method maskEmail.
   *
   * Masks an email address for privacy.
   *
   * Examples:
   * - john.doe@example.com -> j***e@e****e.com
   *
   * - a@b.c -> a***@b.c
   *
   * @since 1.0.0
   *
   * @param string $email the email to mask
   *
   * @return string the masked email
   */
  public static function maskEmail(string $email): string
  {
    $atPos = strpos($email, '@');
    if (false === $atPos || $atPos < 1) {
      return '***';
    }

    $localPart = substr($email, 0, $atPos);
    $domainPart = substr($email, $atPos + 1);

    // Mask local part: keep first and last character
    $maskedLocal = self::maskString($localPart);

    // Mask domain: keep first and last part
    $dotPos = strpos($domainPart, '.');
    if (false !== $dotPos && $dotPos > 0) {
      $domainName = substr($domainPart, 0, $dotPos);
      $domainExt = substr($domainPart, $dotPos);
      $maskedDomain = self::maskString($domainName) . $domainExt;
    } else {
      $maskedDomain = self::maskString($domainPart);
    }

    return $maskedLocal . '@' . $maskedDomain;
  }

  /**
   * Method maskPhone.
   *
   * Masks a phone number for privacy.
   *
   * Examples:
   * - +33612345678 -> +336****5678
   * - 0612345678 -> 06****5678
   *
   * @since 1.0.0
   *
   * @param string $phone the phone number to mask
   *
   * @return string the masked phone number
   */
  public static function maskPhone(string $phone): string
  {
    $length = strlen($phone);

    if ($length < 4) {
      return '***';
    }

    // Keep first 3 and last 4 characters
    $keepStart = 3;
    $keepEnd = 4;

    if ($length <= $keepStart + $keepEnd) {
      return substr($phone, 0, 2) . '***';
    }

    $start = substr($phone, 0, $keepStart);
    $end = substr($phone, -$keepEnd);
    $maskLength = $length - $keepStart - $keepEnd;

    return $start . str_repeat('*', $maskLength) . $end;
  }

  /**
   * Method maskString.
   *
   * Masks a string keeping first and last character.
   *
   * @param string $str the string to mask
   *
   * @return string the masked string
   */
  private static function maskString(string $str): string
  {
    $length = strlen($str);

    if ($length <= 2) {
      return $str . '***';
    }

    return substr($str, 0, 1) . str_repeat('*', $length - 2) . substr($str, -1);
  }
  // #endregion
}
