<?php

declare(strict_types=1);

namespace Otp\Infrastructure\Adapter\Notifier;

use Otp\Application\Port\Outbound\Totp\TotpServicePort;
use Otp\Domain\ValueObject\TotpSecret;

use function bindec;
use function chr;
use function decbin;
use function floor;
use function hash_equals;
use function hash_hmac;
use function ord;
use function pack;
use function preg_match;
use function rtrim;
use function str_pad;
use function str_split;
use function strlen;
use function strpos;
use function strtoupper;
use function time;

use const STR_PAD_LEFT;

/**
 * Adapter TotpAdapter.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpAdapter implements TotpServicePort
{
  // #region Constants
  /**
   * Constant PERIOD.
   *
   * TOTP period in seconds.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int PERIOD = 30;

  /**
   * Constant DIGITS.
   *
   * Number of digits in TOTP code.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DIGITS = 6;

  /**
   * Constant WINDOW.
   *
   * Time window for code validation
   * (allows 1 period before/after).
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int WINDOW = 1;
  // #endregion

  // #region Methods
  /**
   * Method generateSecret
   * {@inheritDoc}
   */
  public function generateSecret(): TotpSecret
  {
    return TotpSecret::generate();
  }

  public function verify(string $code, TotpSecret $secret): bool
  {
    // Validate code format
    if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
      return false;
    }

    $timestamp = time();
    $secretBytes = $this->base32Decode($secret->secret);

    // Check current and adjacent time windows
    for ($offset = -self::WINDOW; $offset <= self::WINDOW; ++$offset) {
      $checkTime = $timestamp + ($offset * self::PERIOD);
      $expectedCode = $this->generateCode($secretBytes, $checkTime);

      if (hash_equals($expectedCode, $code)) {
        return true;
      }
    }

    return false;
  }

  public function getProvisioningUri(
    TotpSecret $secret,
    string $accountName,
    string $issuer = 'FireGuard Auth',
  ): string {
    return $secret->getProvisioningUri($accountName, $issuer);
  }

  /**
   * Method generateCode.
   *
   * Generates a TOTP code for a given timestamp.
   *
   * @param string $secret the decoded secret bytes
   * @param int $timestamp the timestamp
   *
   * @return string the TOTP code
   */
  private function generateCode(string $secret, int $timestamp): string
  {
    $counter = (int) floor($timestamp / self::PERIOD);
    $counterBytes = pack('J', $counter); // 8-byte big-endian

    $hash = hash_hmac('sha1', $counterBytes, $secret, true);
    $offset = ord($hash[19]) & 0x0F;

    $binary = (
      ((ord($hash[$offset]) & 0x7F) << 24) |
      ((ord($hash[$offset + 1]) & 0xFF) << 16) |
      ((ord($hash[$offset + 2]) & 0xFF) << 8) |
      (ord($hash[$offset + 3]) & 0xFF)
    );

    $otp = $binary % (10 ** self::DIGITS);

    return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
  }

  /**
   * Method base32Decode.
   *
   * Decodes a base32 string to bytes.
   *
   * @param string $input the base32 string
   *
   * @return string the decoded bytes
   */
  private function base32Decode(string $input): string
  {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(rtrim($input, '='));

    $buffer = '';
    foreach (str_split($input) as $char) {
      $value = strpos($alphabet, $char);
      if (false === $value) {
        continue;
      }
      $buffer .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }

    $result = '';
    foreach (str_split($buffer, 8) as $byte) {
      if (8 === strlen($byte)) {
        $result .= chr((int) bindec($byte));
      }
    }

    return $result;
  }
  // #endregion
}
