<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Application\Contract\Export\EquipmentExportCandidate;
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
   * Method findPublishedById.
   *
   * Finds PUBLISHED equipment by identifier. Draft intervention
   * scratchpads are invisible to this lookup: the lifecycle commands
   * (commission, maintenance, decommission) must not act on — nor
   * audit — records that only exist inside an unpublished intervention.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $id the equipment identifier
   *
   * @return ?Equipment the published equipment aggregate when found
   */
  public function findPublishedById(EquipmentId $id): ?Equipment;

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

  /**
   * Counts dashboard overview metrics for equipment in one query.
   *
   * @return array{total: int, in_stock: int, operational: int, under_maintenance: int, decommissioned: int}
   */
  public function countOverviewByOrganizationId(
    EquipmentOrganizationId $organizationId,
    ?string $type = null,
    ?string $status = null,
  ): array;

  /**
   * Counts equipment grouped by status for an organization.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   *
   * @return array<string, int> map of status => count
   */
  public function countByStatusForOrganizationId(EquipmentOrganizationId $organizationId): array;

  /**
   * Counts equipment grouped by type for an organization.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   *
   * @return array<string, int> map of type => count
   */
  public function countByTypeForOrganizationId(EquipmentOrganizationId $organizationId): array;

  /**
   * Counts equipment creations grouped by creation day for an organization.
   *
   * @return array<string, int> map of YYYY-MM-DD => count
   */
  public function countByCreatedDayForOrganizationId(
    EquipmentOrganizationId $organizationId,
    string $createdAtFrom,
    string $createdAtTo,
    ?string $timeZone = null,
    ?string $type = null,
    ?string $status = null,
  ): array;

  /**
   * Method countEquipments.
   *
   * Counts every published equipment item for an organization, with no
   * filters — backs the CSV export's row cap, mirroring
   * `Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort::countInterventions()`.
   * Deliberately unfiltered: the export scopes to the whole organization, not
   * to the list endpoint's current filter selection.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   *
   * @return int the matching equipment count
   */
  public function countEquipments(EquipmentOrganizationId $organizationId): int;

  /**
   * Method listEquipmentExportCandidates.
   *
   * Lists every published equipment item for an organization, in the CSV
   * export's stable order (`updatedAt` DESC, `id` ASC), as lightweight
   * {@see EquipmentExportCandidate} rows. Callers must first bound the result
   * with {@see self::countEquipments()}. Mirrors
   * `Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort::listInterventionExportCandidates()`.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   *
   * @return list<EquipmentExportCandidate> the matching equipment rows
   */
  public function listEquipmentExportCandidates(EquipmentOrganizationId $organizationId): array;

  // #endregion
}
