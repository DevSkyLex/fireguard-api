<?php

declare(strict_types=1);

namespace Auth\Domain\Exception;

use Exception;

use function sprintf;

/**
 * Exception ValidationException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ValidationException extends Exception
{
    // #region Methods
    /**
     * Method invalidGrantType.
     *
     * Creates an exception for invalid grant type.
     *
     * @since 1.0.0
     *
     * @param string $grantType the invalid grant type
     *
     * @return self the exception
     */
    public static function invalidGrantType(string $grantType): self
    {
        return new self(
            message: sprintf('Unsupported grant type: %s', $grantType),
            code: 400
        );
    }

    /**
     * Method missingField.
     *
     * Creates an exception for missing required field.
     *
     * @since 1.0.0
     *
     * @param string $fieldName the missing field name
     *
     * @return self the exception
     */
    public static function missingField(string $fieldName): self
    {
        return new self(
            message: sprintf('The %s field is required', $fieldName),
            code: 400
        );
    }

    /**
     * Method invalidField.
     *
     * Creates an exception for invalid field value.
     *
     * @since 1.0.0
     *
     * @param string $fieldName the field name
     * @param string $reason    the reason
     *
     * @return self the exception
     */
    public static function invalidField(string $fieldName, string $reason): self
    {
        return new self(
            message: sprintf('Invalid %s: %s', $fieldName, $reason),
            code: 400
        );
    }
    // #endregion
}
