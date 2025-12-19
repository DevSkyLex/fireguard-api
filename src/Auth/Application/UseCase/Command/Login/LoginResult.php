<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Login;

use Shared\Application\Message\ResultMessage;

/**
 * Result LoginResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginResult implements ResultMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * LoginResult class.
     *
     * @since 1.0.0
     *
     * @param bool         $authenticated whether authentication succeeded
     * @param string|null  $userId        the authenticated user ID
     * @param string|null  $email         the authenticated user email
     * @param string|null  $accessToken   the access token
     * @param string|null  $refreshToken  the refresh token
     * @param string       $tokenType     the token type
     * @param int          $expiresIn     the token expiration time in seconds
     * @param list<string> $scopes        the granted scopes
     * @param string|null  $errorMessage  error message if authentication failed
     */
    public function __construct(
        public bool $authenticated,
        public ?string $userId = null,
        public ?string $email = null,
        public ?string $accessToken = null,
        public ?string $refreshToken = null,
        public string $tokenType = 'Bearer',
        public int $expiresIn = 0,
        public array $scopes = [],
        public ?string $errorMessage = null,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method failed.
     *
     * Creates a failed login result.
     *
     * @since 1.0.0
     *
     * @param string $message the error message
     *
     * @return self the failed result
     */
    public static function failed(string $message = 'Invalid credentials'): self
    {
        return new self(
            authenticated: false,
            errorMessage: $message,
        );
    }
    // #endregion
}
