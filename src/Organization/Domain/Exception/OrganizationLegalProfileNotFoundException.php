<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationLegalProfileNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationLegalProfileNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method forOrganization.
   *
   * Creates an exception for a missing legal profile.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the exception instance
   */
  public static function forOrganization(string $organizationId): self
  {
    return new self(sprintf('Legal profile for organization "%s" not found.', $organizationId));
  }
  // #endregion
}
