<?php

declare(strict_types=1);

namespace Equipment\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception EquipmentNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing equipment identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the equipment identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Equipment with ID "%s" not found.', $id));
  }

  /**
   * Method forOrganizationScope.
   *
   * Builds the exception for an organization the caller is not an active
   * member of, on a route whose scope the caller supplied directly (the
   * export) rather than reaching through a record identifier. Deliberately
   * the SAME class, and therefore the same 404, that an unknown identifier
   * produces: a distinct status here would tell an outsider which
   * organization identifiers are real. Mirrors
   * `Intervention\Domain\Exception\InterventionNotFoundException::forOrganizationScope()`.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return self the with organization scope result
   */
  public static function forOrganizationScope(string $organizationId): self
  {
    return new self(sprintf('Organization with ID "%s" not found.', $organizationId));
  }
  // #endregion
}
