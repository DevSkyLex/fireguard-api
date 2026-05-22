<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\GetCurrentUserProfile;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetCurrentUserProfileQuery.
 *
 * @category Query
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCurrentUserProfileQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetCurrentUserProfileQuery class.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user ID
   */
  public function __construct(
    public string $userId,
  ) {
  }
  // #endregion
}
