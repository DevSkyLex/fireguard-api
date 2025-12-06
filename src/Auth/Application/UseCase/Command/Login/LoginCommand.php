<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Login;

use Shared\Application\Message\CommandMessage;

/**
 * Command LoginCommand
 * @final
 *
 * Command to authenticate a user with email and password.
 *
 * @category Command
 * @package Auth\Application\UseCase\Command\Login
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * LoginCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $email The user's email.
   * @param string $password The user's password.
   * @param bool $rememberMe Whether to extend token lifetime.
   * @param string|null $ipAddress The client IP address.
   */
  public function __construct(
    public string $email,
    public string $password,
    public bool $rememberMe = false,
    public ?string $ipAddress = null,
  ) {}
  //#endregion
}
