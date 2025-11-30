<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\GetUser;

use Shared\Application\Message\ResultMessage;
use User\Domain\Model\User;

/**
 * Result GetUserResult
 * @final
 *
 * Result of getting a user.
 *
 * @category Result
 * @package User\Application\UseCase\Query\GetUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the GetUserResult class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param User|null $user The user, or null if not found.
   */
  public function __construct(
    public readonly ?User $user
  ) {}
  //#endregion
}
