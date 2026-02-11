<?php

declare(strict_types=1);

namespace Organization\Application\Port\Outbound;

use Organization\Domain\Model\OrganizationLegalProfile\OrganizationLegalProfile;
use Organization\Domain\ValueObject\OrganizationId;

/**
 * Port OrganizationLegalProfileRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface OrganizationLegalProfileRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an organization legal profile aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationLegalProfile $profile the legal profile aggregate
   */
  public function save(OrganizationLegalProfile $profile): void;

  /**
   * Method findByOrganizationId.
   *
   * Finds the legal profile of an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return ?OrganizationLegalProfile the legal profile when found
   */
  public function findByOrganizationId(OrganizationId $organizationId): ?OrganizationLegalProfile;

  /**
   * Method deleteByOrganizationId.
   *
   * Deletes the legal profile associated with an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   */
  public function deleteByOrganizationId(OrganizationId $organizationId): void;
  // #endregion
}
