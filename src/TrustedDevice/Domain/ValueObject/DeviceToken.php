<?php

declare(strict_types=1);

namespace TrustedDevice\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function bin2hex;
use function hash;
use function hash_equals;
use function random_bytes;

/**
 * ValueObject DeviceToken.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeviceToken
{
  // #region Constants
  private const int TOKEN_BYTES = 32;
  // #endregion

  // #region Properties
  private ?string $plainToken;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @param string      $hash  the token hash
   * @param string|null $plain the plain token (only available at generation)
   */
  private function __construct(
    public string $hash,
    ?string $plain = null,
  ) {
    $this->plainToken = $plain;
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method generate.
   *
   * @static
   *
   * Generates a new secure device token.
   */
  public static function generate(): self
  {
    $plain = bin2hex(random_bytes(self::TOKEN_BYTES));
    $hash = hash('sha256', $plain);

    return new self(hash: $hash, plain: $plain);
  }

  /**
   * Method fromHash.
   *
   * @static
   *
   * Creates a DeviceToken from an existing hash.
   *
   * @param string $hash the token hash
   */
  public static function fromHash(string $hash): self
  {
    return new self(hash: $hash);
  }
  // #endregion

  // #region Methods
  /**
   * Method plain.
   *
   * Returns the plain token.
   *
   * @throws InvalidValueException if plain token is not available
   */
  public function plain(): string
  {
    if (null === $this->plainToken) {
      throw InvalidValueException::because(
        message: 'Plain token is only available at generation time.'
      );
    }

    return $this->plainToken;
  }

  /**
   * Method verify.
   *
   * Verifies a plain token against this hash.
   *
   * @param string $plainToken the plain token to verify
   *
   * @return bool true if valid
   */
  public function verify(string $plainToken): bool
  {
    return hash_equals($this->hash, hash('sha256', $plainToken));
  }
  // #endregion
}
