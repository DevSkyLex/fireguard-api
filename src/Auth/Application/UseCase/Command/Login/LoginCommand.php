<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Login;

use Shared\Application\Message\CommandMessage;

/**
 * Command LoginCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginCommand implements CommandMessage
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the
     * LoginCommand class.
     *
     * @since 1.0.0
     *
     * @param string      $email      the user's email
     * @param string      $password   the user's password
     * @param bool        $rememberMe whether to extend token lifetime
     * @param string|null $ipAddress  the client IP address
     */
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $rememberMe = false,
        public readonly ?string $ipAddress = null,
    ) {
    }
    // #endregion
}
