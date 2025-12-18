<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use Shared\Domain\Exception\InvalidValueException;

/**
 * Exception InvalidGrantTypeException
 * @final
 *
 * It is thrown when an invalid OAuth 2.0 grant type is provided.
 *
 * @category Exception
 * @package OAuth\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidGrantTypeException extends InvalidValueException
{
  //#region Methods
  /**
   * Method notAllowed
   * @static
   *
   * Creates a new InvalidGrantTypeException for 
   * disallowed grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The invalid grant type value.
   * @param array<string> $allowed The list of allowed grant types.
   *
   * @return self The created InvalidGrantTypeException.
   */
  public static function notAllowed(string $value, array $allowed): self
  {
    $allowedList = implode(', ', $allowed);
    return new self(
      message: "Invalid grant type: '{$value}'. Allowed grant types are: {$allowedList}."
    );
  }

  /**
   * Method empty
   * @static
   *
   * Creates a new InvalidGrantTypeException for empty grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @return self The created InvalidGrantTypeException.
   */
  public static function empty(): self
  {
    return new self(message: 'Grant type cannot be empty.');
  }
  //#endregion
}
