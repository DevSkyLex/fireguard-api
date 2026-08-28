<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use InvalidArgumentException;

/**
 * Port FacilityValidationPort.
 *
 * Validates that a facility exists, belongs to the expected organization,
 * and is active. This abstracts the Facility module from the Equipment module,
 * maintaining proper module isolation in hexagonal architecture.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityValidationPort
{
  // #region Methods
  /**
   * Method assertFacilityIsAssignable.
   *
   * Verifies that the given facility exists, belongs to the specified
   * organization, and is in an active (non-archived) state.
   *
   * @since 1.0.0
   *
   * @param string $facilityId the facility identifier
   * @param string $organizationId the expected organization identifier
   *
   * @throws InvalidArgumentException when the facility is not found, belongs to another organization, or is archived
   */
  public function assertFacilityIsAssignable(string $facilityId, string $organizationId): void;

  /**
   * Method belongsToOrganization.
   *
   * Answers whether the facility exists and belongs to the organization, and
   * nothing more.
   *
   * Deliberately NOT `assertFacilityIsAssignable()`: that method additionally
   * rejects an archived facility and reports failure as an
   * `InvalidArgumentException` naming the id. The canonical equipment surface
   * has neither rule — it answers 422 "Facility must belong to the same
   * organization." and says nothing about archival — so calling the stricter
   * method there would change three things at once: the status, the message,
   * and which facilities are accepted.
   *
   * @since 1.1.0
   *
   * @param string $facilityId the facility identifier
   * @param string $organizationId the expected organization identifier
   *
   * @return bool true when the facility exists and belongs to that organization
   */
  public function belongsToOrganization(string $facilityId, string $organizationId): bool;

  /**
   * Method resolveIdByCode.
   *
   * Resolves a facility identifier from its organization-scoped unique
   * `code`. Archived facilities are excluded on purpose: a code that only
   * matches an archived facility resolves to `null`, exactly as the
   * Facility module resolves an import row's `parentCode`.
   *
   * @since 1.2.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $code the facility code to resolve
   *
   * @return ?string the facility identifier, or null when no active facility carries that code
   */
  public function resolveIdByCode(string $organizationId, string $code): ?string;
  // #endregion
}
