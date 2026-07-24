<?php

declare(strict_types=1);

namespace User\Application\UseCase\Query\User\GetCurrentUserProfile;

use Shared\Application\Message\ResultMessage;
use User\Application\Contract\User\UserView;

/**
 * Result GetCurrentUserProfileResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCurrentUserProfileResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the GetCurrentUserProfileResult class.
   *
   * @since 1.0.0
   *
   * @param UserView $user the current user view
   * @param list<string> $roles the current user role names
   * @param list<string> $permissions the current user permission names
   * @param bool $totpEnabled whether the user has an active TOTP (authenticator app) enrollment
   */
  public function __construct(
    public UserView $user,
    public array $roles,
    public array $permissions,
    public bool $totpEnabled = false,
  ) {
  }
  // #endregion
}
