<?php

declare(strict_types=1);

namespace Auth\Domain\Exception;

use Shared\Domain\Exception\DomainException;

use function sprintf;

/**
 * Exception AuthorizationException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AuthorizationException extends DomainException
{
    // #region Factory Methods
    /**
     * Method invalidClient.
     *
     * @static
     *
     * Creates an exception for invalid client credentials.
     *
     * @since 1.0.0
     *
     * @return self the exception
     */
    public static function invalidClient(): self
    {
        return new self(message: 'Invalid client credentials.');
    }

    /**
     * Method invalidGrant.
     *
     * @static
     *
     * Creates an exception for invalid grant.
     *
     * @since 1.0.0
     *
     * @param string $reason the reason
     *
     * @return self the exception
     */
    public static function invalidGrant(string $reason): self
    {
        return new self(message: sprintf('Invalid grant: %s', $reason));
    }

    /**
     * Method invalidScope.
     *
     * @static
     *
     * Creates an exception for invalid scope.
     *
     * @since 1.0.0
     *
     * @return self the exception
     */
    public static function invalidScope(): self
    {
        return new self(message: 'Invalid scope requested.');
    }

    /**
     * Method serverError.
     *
     * @static
     *
     * Creates an exception for server error.
     *
     * @since 1.0.0
     *
     * @param string $message the error message
     *
     * @return self the exception
     */
    public static function serverError(string $message): self
    {
        return new self(message: sprintf('Authorization server error: %s', $message));
    }
    // #endregion
}
