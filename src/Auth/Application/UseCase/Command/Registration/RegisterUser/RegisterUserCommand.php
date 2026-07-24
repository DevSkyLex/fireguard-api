<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\RegisterUser;

use Shared\Application\Message\CommandMessage;

/**
 * Command RegisterUserCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterUserCommand implements CommandMessage
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
   * @param string $email the prospective user's email
   * @param string $password the plain text password
   * @param string $firstName the first name
   * @param string $lastName the last name
   * @param string|null $ipAddress the client IP address
   */
  public function __construct(
    public readonly string $email,
    public readonly string $password,
    public readonly string $firstName,
    public readonly string $lastName,
    public readonly ?string $ipAddress = null,
  ) {
  }
  // #endregion
}
