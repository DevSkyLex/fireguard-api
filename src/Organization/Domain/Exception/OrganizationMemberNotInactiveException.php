<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationMemberNotInactiveException.
 *
 * Raised when a state-changing operation requires a member to currently be
 * INACTIVE (e.g. reactivation) and it is not — the member is either already
 * active or does not exist at all in that state.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMemberNotInactiveException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a member that is already active.
   *
   * @since 1.0.0
   *
   * @param string $id the member identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Organization member with ID "%s" is already active.', $id));
  }
  // #endregion
}
