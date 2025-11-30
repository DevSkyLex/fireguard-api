<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\CreateUser;

use Shared\Application\Message\CommandMessage;

/**
 * Command CreateUserCommand
 * @final
 *
 * Command to create a new user.
 *
 * @category Command
 * @package User\Application\UseCase\Command\CreateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateUserCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * RegisterUserCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $username The username.
   * @param string $email The email.
   * @param string $password The plain text password.
   * @param string $firstName The first name.
   * @param string $lastName The last name.
   * @param string|null $avatarUrl The avatar URL.
   * @param string|null $tenantId The tenant ID (for multi-tenant).
   */
  public function __construct(
    public readonly string $username,
    public readonly string $email,
    public readonly string $password,
    public readonly string $firstName,
    public readonly string $lastName,
    public readonly ?string $avatarUrl = null,
    public readonly ?string $tenantId = null,
  ) {
  }
  //#endregion
}
