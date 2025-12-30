<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception\Token;

use Shared\Domain\Exception\InvalidValueException;

use function implode;

/**
 * Exception InvalidGrantTypeException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidGrantTypeException extends InvalidValueException
{
  // #region Methods
  /**
   * Method notAllowed.
   *
   * @static
   *
   * Creates a new InvalidGrantTypeException for
   * disallowed grant type.
   *
   * @since 1.0.0
   *
   * @param string $value the invalid grant type value
   * @param array<string> $allowed the list of allowed grant types
   *
   * @return self the created InvalidGrantTypeException
   */
  public static function notAllowed(string $value, array $allowed): self
  {
    $allowedList = implode(', ', $allowed);

    return new self(
      message: "Invalid grant type: '{$value}'. Allowed grant types are: {$allowedList}.",
    );
  }

  /**
   * Method empty.
   *
   * @static
   *
   * Creates a new InvalidGrantTypeException for empty grant type.
   *
   * @since 1.0.0
   *
   * @return self the created InvalidGrantTypeException
   */
  public static function empty(): self
  {
    return new self(message: 'Grant type cannot be empty.');
  }
  // #endregion
}
