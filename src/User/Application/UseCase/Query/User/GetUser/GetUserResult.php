<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\GetUser;

use Shared\Application\Message\ResultMessage;
use User\Application\Contract\User\UserView;

/**
 * Result GetUserResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetUserResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetUserResult class.
   *
   * @since 1.0.0
   *
   * @param UserView|null $user the user view, or null if not found
   */
  public function __construct(
    public readonly ?UserView $user,
  ) {
  }
  // #endregion
}
