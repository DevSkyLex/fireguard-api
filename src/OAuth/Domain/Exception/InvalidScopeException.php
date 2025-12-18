<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use Shared\Domain\Exception\InvalidValueException;

/**
 * Exception InvalidScopeException
 * @final
 *
 * It is thrown when an invalid OAuth 2.0 scope is provided.
 *
 * @category Exception
 * @package OAuth\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidScopeException extends InvalidValueException
{
  //#region Methods
  /**
   * Method invalidFormat
   * @static
   *
   * Creates a new InvalidScopeException for invalid scope format.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The invalid scope value.
   *
   * @return self The created InvalidScopeException.
   */
  public static function invalidFormat(string $value): self
  {
    return new self(
      message: "Invalid scope format: '{$value}'. Scopes must contain only alphanumeric characters, dots, hyphens, underscores, and colons."
    );
  }

  /**
   * Method empty
   * @static
   *
   * Creates a new InvalidScopeException for empty scope.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The created InvalidScopeException.
   */
  public static function empty(): self
  {
    return new self(message: 'Scope cannot be empty.');
  }
  //#endregion
}
