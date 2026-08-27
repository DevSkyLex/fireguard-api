<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationOwnershipUnchangedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOwnershipUnchangedException extends RuntimeException
{
  // #region Methods
  /**
   * Method withOwnerUserId.
   *
   * Creates an exception for an ownership transfer targeting the
   * organization's current owner.
   *
   * @since 1.0.0
   *
   * @param string $ownerUserId the user ID already owning the organization
   *
   * @return self the exception instance
   */
  public static function withOwnerUserId(string $ownerUserId): self
  {
    return new self(sprintf('User "%s" already owns this organization.', $ownerUserId));
  }
  // #endregion
}
