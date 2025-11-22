<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\RegisterUser;

/**
 * Result RegisterUserResult
 * @final
 *
 * Result of user registration.
 *
 * @category Result
 * @package User\Application\UseCase\Command\RegisterUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
use Shared\Application\Message\ResultMessage;

/**
 * Result RegisterUserResult
 * @final
 *
 * Result of user registration.
 *
 * @category Result
 * @package User\Application\UseCase\Command\RegisterUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterUserResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the RegisterUserResult class.
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
