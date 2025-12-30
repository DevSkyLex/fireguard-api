<?php

declare(strict_types=1);

namespace OAuth\Domain\ValueObject\Token;

use JsonSerializable;
use Shared\Domain\Exception\InvalidValueException;

use function array_key_exists;

/**
 * ValueObject TokenClaims.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenClaims implements JsonSerializable
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the TokenClaims class.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $claims the claims to set
   *
   * @throws InvalidValueException if the claims are invalid
   */
  public function __construct(private array $claims)
  {
    if ([] === $claims) {
      throw InvalidValueException::because(
        message: 'Token claims cannot be empty.',
      );
    }

    foreach ($claims as $key => $value) {
      if ('' === $key) {
        throw InvalidValueException::because(
          message: 'Token claim keys must be non-empty strings.',
        );
      }
    }
  }
  // #endregion

  // #region Methods
  /**
   * Method toArray.
   *
   * Returns the claims as an array.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the claims as an array
   */
  public function toArray(): array
  {
    return $this->claims;
  }

  /**
   * Method has.
   *
   * Checks if a claim exists.
   *
   * @since 1.0.0
   *
   * @param string $key the key to check
   *
   * @return bool true if the key exists, false otherwise
   */
  public function has(string $key): bool
  {
    return array_key_exists($key, $this->claims);
  }

  /**
   * Method get.
   *
   * Returns the value of a claim.
   *
   * @since 1.0.0
   *
   * @param string $key the key to get
   * @param mixed $default the default value to return if the key does not exist
   *
   * @return mixed the value of the key, or the default value if the key does not exist
   */
  public function get(string $key, mixed $default = null): mixed
  {
    return $this->claims[$key] ?? $default;
  }

  /**
   * Method jsonSerialize.
   *
   * Returns the claims as an array.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the claims as an array
   */
  public function jsonSerialize(): array
  {
    return $this->claims;
  }
  // #endregion
}
