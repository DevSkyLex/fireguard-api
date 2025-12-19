<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\DomainException;

/**
 * Exception InvalidPasswordException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InvalidPasswordException extends DomainException
{
    // #region Methods
    /**
     * Method tooWeak.
     *
     * @static
     *
     * Creates an exception for a weak password.
     *
     * @since 1.0.0
     *
     * @return self the exception instance
     */
    public static function tooWeak(): self
    {
        return new self(
            message: 'Password is too weak. Must be at least 8 characters with uppercase, lowercase, number, and special character.'
        );
    }

    /**
     * Method incorrect.
     *
     * @static
     *
     * Creates an exception for an incorrect password.
     *
     * @since 1.0.0
     *
     * @return self the exception instance
     */
    public static function incorrect(): self
    {
        return new self(message: 'Incorrect password.');
    }
    // #endregion
}
