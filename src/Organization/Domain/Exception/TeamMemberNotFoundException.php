<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception TeamMemberNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TeamMemberNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a member not belonging to a team.
   *
   * @since 1.0.0
   *
   * @param string $memberId the member identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $memberId): self
  {
    return new self(sprintf('Member with ID "%s" is not part of this team.', $memberId));
  }
  // #endregion
}
