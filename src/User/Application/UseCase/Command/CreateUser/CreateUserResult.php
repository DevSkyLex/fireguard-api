<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\CreateUser;

use Shared\Application\Message\ResultMessage;

/**
 * Result CreateUserResult
 * @final
 *
 * Result of user creation.
 *
 * @category Result
 * @package User\Application\UseCase\Command\CreateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateUserResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the CreateUserResult class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The created user ID.
   */
  public function __construct(
    public readonly string $userId,
  ) {
  }
  //#endregion
}
