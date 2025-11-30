<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\DeleteUser;

use Shared\Application\Message\ResultMessage;

/**
 * Result DeleteUserResult
 * @final
 *
 * Result of user deletion.
 *
 * @category Result
 * @package User\Application\UseCase\Command\DeleteUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteUserResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * DeleteUserResult class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The deleted user ID.
   */
  public function __construct(
    public readonly string $userId,
  ) {}
  //#endregion
}
