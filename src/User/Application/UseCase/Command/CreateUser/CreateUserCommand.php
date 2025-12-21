<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\CreateUser;

use Shared\Application\Message\CommandMessage;

/**
 * Command CreateUserCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateUserCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RegisterUserCommand class.
   *
   * @since 1.0.0
   *
   * @param string $username the username
   * @param string $email the email
   * @param string $password the plain text password
   * @param string $firstName the first name
   * @param string $lastName the last name
   * @param string|null $avatarUrl the avatar URL
   * @param string|null $tenantId the tenant ID (for multi-tenant)
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
  // #endregion
}
