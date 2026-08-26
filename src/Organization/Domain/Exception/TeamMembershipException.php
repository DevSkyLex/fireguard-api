<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception TeamMembershipException.
 *
 * Raised when a member cannot be added to a team: they are not active in the
 * organization, or they are already on the team. Distinct from
 * `TeamMemberNotFoundException`, which means no such membership row exists.
 *
 * Mapped to 400 — what the `InvalidArgumentException` it replaces already
 * answered. "Already part of this team" is a conflict and would read better as
 * 409; that is a contract decision, deliberately not taken here.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TeamMembershipException extends RuntimeException
{
  // #region Methods
  /**
   * Method memberNotActive.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function memberNotActive(): self
  {
    return new self('Member is not active in this organization.');
  }

  /**
   * Method alreadyOnTheTeam.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function alreadyOnTheTeam(): self
  {
    return new self('Member is already part of this team.');
  }
  // #endregion
}
