<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\UpdateUser;

use Shared\Application\Message\ResultMessage;

/**
 * Result UpdateUserResult
 * @final
 *
 * Result of user update.
 *
 * @category Result
 * @package User\Application\UseCase\Command\UpdateUser
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateUserResult implements ResultMessage
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes a new instance of the 
   * UpdateUserResult class.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The updated user ID.
   */
  public function __construct(
    
    public readonly string $userId,
  ) {}
  //#endregion
}
