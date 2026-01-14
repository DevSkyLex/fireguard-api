<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\CreateUser;

use Shared\Application\Message\ResultMessage;

/**
 * Result CreateUserResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateUserResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the CreateUserResult class.
   *
   * @since 1.0.0
   *
   * @param string $userId the created user ID
   */
  public function __construct(
    public readonly string $userId,
  ) {
  }
  // #endregion
}
