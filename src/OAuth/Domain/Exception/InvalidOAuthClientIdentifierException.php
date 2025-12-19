<?php

declare(strict_types=1);

namespace OAuth\Domain\Exception;

use Shared\Domain\Exception\InvalidValueException;

/**
 * Exception InvalidOAuthClientIdentifierException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidOAuthClientIdentifierException extends InvalidValueException
{
    // #region Methods
    /**
     * Method invalidPattern.
     *
     * @static
     *
     * Creates a new InvalidOAuthClientIdentifierException
     * for pattern mismatch.
     *
     * @since 1.0.0
     *
     * @param string $value the invalid client identifier value
     *
     * @return self the created InvalidOAuthClientIdentifierException
     */
    public static function invalidPattern(string $value): self
    {
        return new self(
            message: "Invalid OAuth client identifier: '{$value}'. Must be 3-128 characters long and contain only alphanumeric characters, dots, hyphens, and underscores. Must start with an alphanumeric character."
        );
    }

    /**
     * Method empty.
     *
     * @static
     *
     * Creates a new InvalidOAuthClientIdentifierException
     * for empty value.
     *
     * @since 1.0.0
     *
     * @return self the created InvalidOAuthClientIdentifierException
     */
    public static function empty(): self
    {
        return new self(message: 'OAuth client identifier cannot be empty.');
    }
    // #endregion
}
