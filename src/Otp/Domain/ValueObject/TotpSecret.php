<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function bindec;
use function decbin;
use function ord;
use function preg_match;
use function random_bytes;
use function rawurlencode;
use function sprintf;
use function str_pad;
use function str_split;

use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

/**
 * ValueObject TotpSecret.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TotpSecret
{
  // #region Constants
  /**
   * Constant SECRET_LENGTH.
   *
   * Length of the secret in bytes.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int SECRET_LENGTH = 20;

  /**
   * Constant BASE32_ALPHABET.
   *
   * Base32 encoding alphabet.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the TotpSecret class.
   *
   * @since 1.0.0
   *
   * @param string $secret the base32-encoded secret
   *
   * @throws InvalidValueException if secret is invalid
   */
  public function __construct(
    public string $secret,
  ) {
    if (!preg_match('/^[A-Z2-7]+=*$/', $secret)) {
      throw InvalidValueException::because(
        message: 'Invalid TOTP secret format.',
      );
    }
  }

  /**
   * Method __toString.
   *
   * Returns the secret string.
   *
   * @since 1.0.0
   *
   * @return string the secret
   */
  public function __toString(): string
  {
    return $this->secret;
  }
  // #endregion

  // #region Methods
  /**
   * Method generate.
   *
   * @static
   *
   * Generates a new random TOTP secret.
   *
   * @since 1.0.0
   *
   * @return self the generated secret
   */
  public static function generate(): self
  {
    $randomBytes = random_bytes(self::SECRET_LENGTH);
    $secret = self::base32Encode($randomBytes);

    return new self(secret: $secret);
  }

  /**
   * Method getProvisioningUri.
   *
   * Returns the provisioning URI for QR code generation.
   *
   * @since 1.0.0
   *
   * @param string $accountName the account name/email
   * @param string $issuer The issuer name (e.g., "FireGuard Auth").
   *
   * @return string the otpauth:// URI
   */
  public function getProvisioningUri(string $accountName, string $issuer = 'FireGuard Auth'): string
  {
    return sprintf(
      'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
      rawurlencode($issuer),
      rawurlencode($accountName),
      $this->secret,
      rawurlencode($issuer),
    );
  }

  /**
   * Method base32Encode.
   *
   * @static
   *
   * Encodes bytes to base32.
   *
   * @since 1.0.0
   *
   * @param string $data the data to encode
   *
   * @return string the base32-encoded string
   */
  private static function base32Encode(string $data): string
  {
    $binary = '';
    foreach (str_split($data) as $char) {
      $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    $result = '';
    $chunks = str_split($binary, 5);

    foreach ($chunks as $chunk) {
      $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
      $index = (int) bindec($chunk);
      $result .= self::BASE32_ALPHABET[$index];
    }

    return $result;
  }
  // #endregion
}
