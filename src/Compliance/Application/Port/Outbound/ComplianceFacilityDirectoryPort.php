<?php

declare(strict_types=1);

namespace Compliance\Application\Port\Outbound;

/**
 * Port ComplianceFacilityDirectoryPort.
 *
 * Provides the organization's facility directory (identity + tree shape) to
 * the Compliance module without coupling it to the Facility repository.
 * Implemented by an adapter hosted in the Facility module.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface ComplianceFacilityDirectoryPort
{
  // #region Methods
  /**
   * Method listFacilities.
   *
   * Lists every facility (active and archived) belonging to an organization,
   * bounded to a safe maximum for a single register generation.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return list<array{id: string, name: string, type: string, status: string, parentFacilityId: ?string}> the facility directory rows
   */
  public function listFacilities(string $organizationId): array;
  // #endregion
}
