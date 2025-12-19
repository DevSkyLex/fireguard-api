<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use Shared\Domain\Exception\InvalidValueException;

/**
 * Exception InvalidScopeException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidScopeException extends InvalidValueException
{
    // #region Methods
    /**
     * Method invalidFormat.
     *
     * @static
     *
     * Creates a new InvalidScopeException for invalid scope format.
     *
     * @since 1.0.0
     *
     * @param string $value the invalid scope value
     *
     * @return self the created InvalidScopeException
     */
    public static function invalidFormat(string $value): self
    {
        return new self(
            message: "Invalid scope format: '{$value}'. Scopes must contain only alphanumeric characters, dots, hyphens, underscores, and colons."
        );
    }

    /**
     * Method empty.
     *
     * @static
     *
     * Creates a new InvalidScopeException for empty scope.
     *
     * @since 1.0.0
     *
     * @return self the created InvalidScopeException
     */
    public static function empty(): self
    {
        return new self(message: 'Scope cannot be empty.');
    }
    // #endregion
}
