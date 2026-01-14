<?php

declare(strict_types=1);

namespace User\Application\UseCase\Command\User\UpdateUser;

use Shared\Application\Message\ResultMessage;

/**
 * Result UpdateUserResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateUserResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateUserResult class.
   *
   * @since 1.0.0
   *
   * @param string $userId the updated user ID
   */
  public function __construct(
    public readonly string $userId,
  ) {
  }
  // #endregion
}
