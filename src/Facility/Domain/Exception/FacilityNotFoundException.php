<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception FacilityNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing facility identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the facility identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Facility with ID "%s" not found.', $id));
  }

  /**
   * Method forOrganizationScope.
   *
   * Creates an exception for an organization outside the caller's scope —
   * deliberately the same 404 a missing facility returns, so the response
   * never confirms the organization exists. Mirrors
   * {@see \Intervention\Domain\Exception\InterventionNotFoundException::forOrganizationScope()}.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the exception instance
   */
  public static function forOrganizationScope(string $organizationId): self
  {
    return new self(sprintf('Organization with ID "%s" not found.', $organizationId));
  }
  // #endregion
}
