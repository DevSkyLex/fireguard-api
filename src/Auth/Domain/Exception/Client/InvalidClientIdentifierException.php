<?php

declare(strict_types=1);

namespace Auth\Domain\Exception\Client;

use Shared\Domain\Exception\InvalidValueException;

/**
 * Exception InvalidClientIdentifierException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidClientIdentifierException extends InvalidValueException
{
  // #region Methods
  /**
   * Method invalidPattern.
   *
   * @static
   *
   * Creates a new InvalidClientIdentifierException
   * for pattern mismatch.
   *
   * @since 1.0.0
   *
   * @param string $value the invalid client identifier value
   *
   * @return self the created InvalidClientIdentifierException
   */
  public static function invalidPattern(string $value): self
  {
    return new self(
      message: "Invalid client identifier: '{$value}'. Must be 3-128 characters long and contain only alphanumeric characters, dots, hyphens, and underscores. Must start with an alphanumeric character.",
    );
  }

  /**
   * Method empty.
   *
   * @static
   *
   * Creates a new InvalidClientIdentifierException
   * for empty value.
   *
   * @since 1.0.0
   *
   * @return self the created InvalidClientIdentifierException
   */
  public static function empty(): self
  {
    return new self(message: 'Client identifier cannot be empty.');
  }
  // #endregion
}
