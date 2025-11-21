<?php

declare(strict_types=1);

namespace Shared\Domain\Exception;

/**
 * Exception InvalidOAuthClientIdentifierException
 * @final
 *
 * It is thrown when an invalid OAuth 2.0 client identifier is provided.
 *
 * @category Exception
 * @package Shared\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidOAuthClientIdentifierException extends InvalidValueException
{
  //#region Methods
  /**
   * Method invalidPattern
   * @static
   *
   * Creates a new InvalidOAuthClientIdentifierException for pattern mismatch.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The invalid client identifier value.
   *
   * @return self The created InvalidOAuthClientIdentifierException.
   */
  public static function invalidPattern(string $value): self
  {
    return new self(
      message: "Invalid OAuth client identifier: '{$value}'. Must be 3-128 characters long and contain only alphanumeric characters, dots, hyphens, and underscores. Must start with an alphanumeric character."
    );
  }

  /**
   * Method empty
   * @static
   *
   * Creates a new InvalidOAuthClientIdentifierException for empty value.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The created InvalidOAuthClientIdentifierException.
   */
  public static function empty(): self
  {
    return new self(message: 'OAuth client identifier cannot be empty.');
  }
  //#endregion
}
