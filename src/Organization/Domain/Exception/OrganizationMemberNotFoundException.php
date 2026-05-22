<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationMemberNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationMemberNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing member identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the member identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Organization member with ID "%s" not found.', $id));
  }

  /**
   * Creates an exception for a missing membership resolved by user and organization.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   * @param string $organizationId the organization identifier
   *
   * @return self the exception instance
   */
  public static function forUserInOrganization(string $userId, string $organizationId): self
  {
    return new self(sprintf(
      'Organization member for user "%s" in organization "%s" not found.',
      $userId,
      $organizationId,
    ));
  }
  // #endregion
}
