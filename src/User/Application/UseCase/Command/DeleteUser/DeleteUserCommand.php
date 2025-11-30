<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\DeleteUser;

use Shared\Application\Message\CommandMessage;

/**
 * Command DeleteUserCommand
 * @final
 *
 * Command to delete a user.
 *
 * @category Command
 * @package User\Application\UseCase\Command\DeleteUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteUserCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * DeleteUserCommand class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $id The user ID.
   */
  public function __construct(
    public string $id
  ) {}
  //#endregion
}
