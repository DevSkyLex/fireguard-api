<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use Shared\Domain\Exception\EntityNotFoundException;

use function sprintf;

/**
 * Exception UserNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UserNotFoundException extends EntityNotFoundException
{
    // #region Methods
    /**
     * Method withId.
     *
     * @static
     *
     * Creates an exception for user not
     * found by ID.
     *
     * @since 1.0.0
     *
     * @param string $id the user ID
     *
     * @return self the exception instance
     */
    public static function withId(string $id): self
    {
        return new self(message: sprintf(
            'User with ID "%s" not found.',
            $id
        ));
    }

    /**
     * Method withUsername.
     *
     * @static
     *
     * Creates an exception for user not
     * found by username.
     *
     * @since 1.0.0
     *
     * @param string $username the username
     *
     * @return self the exception instance
     */
    public static function withUsername(string $username): self
    {
        return new self(message: sprintf(
            'User with username "%s" not found.',
            $username
        ));
    }

    /**
     * Method withEmail.
     *
     * @static
     *
     * Creates an exception for user not
     * found by email.
     *
     * @since 1.0.0
     *
     * @param string $email the email
     *
     * @return self the exception instance
     */
    public static function withEmail(string $email): self
    {
        return new self(message: sprintf(
            'User with email "%s" not found.',
            $email
        ));
    }
    // #endregion
}
