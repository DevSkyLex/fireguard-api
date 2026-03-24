<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use Shared\Application\Contract\Sorting\{SortDirection, Sorting};

/**
 * Port EquipmentRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface EquipmentRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an equipment aggregate.
   *
   * @since 1.0.0
   *
   * @param Equipment $equipment the equipment aggregate
   */
  public function save(Equipment $equipment): void;

  /**
   * Method findById.
   *
   * Finds an equipment by identifier.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   *
   * @return ?Equipment the equipment aggregate when found
   */
  public function findById(EquipmentId $id): ?Equipment;

  /**
   * Method findByOrganizationId.
   *
   * Lists equipment for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   * @param ?string $brand optional brand filter
   * @param ?string $model optional model filter
   * @param ?string $subType optional subtype filter
   * @param ?string $search optional text search applied before pagination
   * @param Sorting $sorting requested sorting applied before pagination
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<Equipment> the equipment list
   */
  public function findByOrganizationId(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId = null,
    ?string $type = null,
    ?string $status = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $subType = null,
    ?string $search = null,
    Sorting $sorting = new Sorting('createdAt', SortDirection::ASC),
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Method countByOrganizationId.
   *
   * Counts equipment for an organization with optional filters.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $type optional type filter
   * @param ?string $status optional status filter
   * @param ?string $brand optional brand filter
   * @param ?string $model optional model filter
   * @param ?string $subType optional subtype filter
   * @param ?string $search optional text search applied before counting
   *
   * @return int the total count
   */
  public function countByOrganizationId(
    EquipmentOrganizationId $organizationId,
    ?string $facilityId = null,
    ?string $type = null,
    ?string $status = null,
    ?string $brand = null,
    ?string $model = null,
    ?string $subType = null,
    ?string $search = null,
  ): int;

  // #endregion
}
