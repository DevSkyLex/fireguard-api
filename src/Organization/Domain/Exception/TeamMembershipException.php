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
 * Mapped to **409**, arbitrated 2026-08-26. Both arms are state conflicts: the
 * member exists and the caller is entitled, the team's or the membership's
 * current state is what forbids the addition.
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
