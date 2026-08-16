<?php

declare(strict_types=1);

namespace Facility\Application\Port\Outbound;

use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{FacilityMetadataFieldId, FacilityOrganizationId};

/**
 * Port FacilityMetadataFieldRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface FacilityMetadataFieldRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a facility metadata field definition.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataField $field the metadata field aggregate
   */
  public function save(FacilityMetadataField $field): void;

  /**
   * Method delete.
   *
   * Deletes a facility metadata field definition. Does not touch any
   * facility's existing `metadata` values.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataFieldId $id the metadata field identifier
   */
  public function delete(FacilityMetadataFieldId $id): void;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param FacilityMetadataFieldId $id the metadata field identifier
   *
   * @return ?FacilityMetadataField the metadata field when found
   */
  public function findById(FacilityMetadataFieldId $id): ?FacilityMetadataField;

  /**
   * Method findByOrganizationIdAndKey.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param string $key the machine key
   *
   * @return ?FacilityMetadataField the metadata field when found
   */
  public function findByOrganizationIdAndKey(FacilityOrganizationId $organizationId, string $key): ?FacilityMetadataField;

  /**
   * Method findByOrganizationId.
   *
   * Lists every metadata field definition for an organization, ordered by
   * label for a stable form-schema listing.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return list<FacilityMetadataField> the metadata field definitions
   */
  public function findByOrganizationId(FacilityOrganizationId $organizationId): array;

  /**
   * Method countByOrganizationId.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return int the number of metadata field definitions
   */
  public function countByOrganizationId(FacilityOrganizationId $organizationId): int;
  // #endregion
}
