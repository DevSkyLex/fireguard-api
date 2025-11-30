<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\UpdateUser;

use Shared\Application\Message\CommandMessage;

/**
 * Command UpdateUserCommand
 * @final
 *
 * Command to update a user's profile.
 *
 * @category Command
 * @package User\Application\UseCase\Command\UpdateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateUserCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initialize the command with the user ID
   * and optional profile data.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $id The user ID.
   * @param string|null $firstName The first name.
   * @param string|null $lastName The last name.
   * @param string|null $avatarUrl The avatar URL.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?string $firstName = null,
    public readonly ?string $lastName = null,
    public readonly ?string $avatarUrl = null,
  ) {}
  //#endregion
}
