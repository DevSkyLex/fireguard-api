<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\AuthenticateUser;

/**
 * Result AuthenticateUserResult.
 *
 * @final
 *
 * Result of user authentication.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
use Shared\Application\Message\ResultMessage;

/**
 * Result AuthenticateUserResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AuthenticateUserResult implements ResultMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the AuthenticateUserResult class.
     *
     * @since 1.0.0
     *
     * @param bool        $authenticated whether authentication was successful
     * @param string|null $userId        the user ID if authenticated
     * @param string|null $email         the user email if authenticated
     * @param string|null $fullName      the user's full name if authenticated
     */
    public function __construct(
        public readonly bool $authenticated,
        public readonly ?string $userId = null,
        public readonly ?string $email = null,
        public readonly ?string $fullName = null,
    ) {
    }
    // #endregion
}
