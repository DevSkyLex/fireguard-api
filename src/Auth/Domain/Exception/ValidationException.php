<?php

declare(strict_types=1);

namespace Auth\Domain\Exception;

use Exception;

/**
 * Exception ValidationException
 * @final
 *
 * Exception for validation errors in the Auth domain.
 *
 * @category Exception
 * @package Auth\Domain\Exception
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidationException extends Exception
{
  //#region Methods
  /**
   * Method invalidGrantType
   *
   * Creates an exception for invalid grant type.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $grantType The invalid grant type.
   *
   * @return self The exception.
   */
  public static function invalidGrantType(string $grantType): self
  {
    return new self(
      message: sprintf('Unsupported grant type: %s', $grantType),
      code: 400
    );
  }

  /**
   * Method missingField
   *
   * Creates an exception for missing required field.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $fieldName The missing field name.
   *
   * @return self The exception.
   */
  public static function missingField(string $fieldName): self
  {
    return new self(
      message: sprintf('The %s field is required', $fieldName),
      code: 400
    );
  }

  /**
   * Method invalidField
   *
   * Creates an exception for invalid field value.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $fieldName The field name.
   * @param string $reason The reason.
   *
   * @return self The exception.
   */
  public static function invalidField(string $fieldName, string $reason): self
  {
    return new self(
      message: sprintf('Invalid %s: %s', $fieldName, $reason),
      code: 400
    );
  }
  //#endregion
}
