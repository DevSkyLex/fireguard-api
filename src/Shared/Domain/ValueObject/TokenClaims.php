<?php

declare(strict_types=1);

namespace Shared\Domain\ValueObject;

use JsonSerializable;
use Serializable;
use Stringable;
use Shared\Domain\Exception\InvalidValueException;

/**
 * ValueObject TokenClaims
 * @final
 *
 * It represents a set of claims
 * for a token.
 *
 * @category ValueObject
 * @package Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenClaims implements JsonSerializable
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the TokenClaims class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param array<string, mixed> $claims The claims to set.
   *
   * @throws InvalidValueException If the claims are invalid.
   */
  public function __construct(private array $claims)
  {
    if ($claims === []) throw InvalidValueException::because(
      message: 'Token claims cannot be empty.'
    );

    foreach ($claims as $key => $_value) {
      if (!is_string($key) || $key === '') {
        throw InvalidValueException::because(
          message: 'Token claim keys must be non-empty strings.'
        );
      }
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method toArray
   * @method toArray(): array<string, mixed>
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The claims as an array.
   */
  public function toArray(): array
  {
    return $this->claims;
  }

  /**
   * Method has
   * @method has(): bool
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The key to check.
   *
   * @return bool True if the key exists, false otherwise.
   */
  public function has(string $key): bool
  {
    return array_key_exists($key, $this->claims);
  }

  /**
   * Method get
   * @method get(): mixed
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $key The key to get.
   * @param mixed $default The default value to return if the key does not exist.
   *
   * @return mixed The value of the key, or the default value if the key does not exist.
   */
  public function get(string $key, mixed $default = null): mixed
  {
    return $this->claims[$key] ?? $default;
  }

  /**
   * Method jsonSerialize
   * @method jsonSerialize(): array<string, mixed>
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The claims as an array.
   */
  public function jsonSerialize(): array
  {
    return $this->claims;
  }
  //#endregion
}
