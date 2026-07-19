<?php

declare(strict_types=1);

namespace Equipment\Application\Port\Outbound;

use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};

/**
 * Port MaintenanceLogRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceLogRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists a maintenance log entry.
   *
   * @since 1.0.0
   *
   * @param EquipmentMaintenanceLog $log the log entry
   */
  public function save(EquipmentMaintenanceLog $log): void;

  /**
   * Method findOpenByEquipmentId.
   *
   * Finds the most recent open (ongoing) log entry for an equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentId $equipmentId the equipment identifier
   *
   * @return ?EquipmentMaintenanceLog the open log entry when found
   */
  public function findOpenByEquipmentId(EquipmentId $equipmentId): ?EquipmentMaintenanceLog;

  /**
   * Method findByEquipmentId.
   *
   * Lists all maintenance log entries for an equipment ordered by startedAt descending.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param EquipmentId $equipmentId the equipment identifier
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<EquipmentMaintenanceLog> the log entries
   */
  public function findByEquipmentId(
    EquipmentOrganizationId $organizationId,
    EquipmentId $equipmentId,
    int $limit = 20,
    int $offset = 0,
  ): array;

  /**
   * Method countByEquipmentId.
   *
   * Counts all maintenance log entries for an equipment.
   *
   * @since 1.0.0
   *
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param EquipmentId $equipmentId the equipment identifier
   *
   * @return int the total count
   */
  public function countByEquipmentId(EquipmentOrganizationId $organizationId, EquipmentId $equipmentId): int;

  /**
   * Method appendInterventionServiceEntry.
   *
   * Idempotently appends a completed, point-in-time service entry
   * synthesized from a published intervention. A raw insert guarded by a
   * unique constraint on `$dedupKey`: a duplicate (at-least-once event
   * redelivery, or a later publication re-reading an already-applied
   * change) is a routine no-op, never an error.
   *
   * @since 1.0.0
   *
   * @param EquipmentMaintenanceLog $entry the service entry to append
   * @param string $dedupKey the idempotency key guarding the insert
   */
  public function appendInterventionServiceEntry(EquipmentMaintenanceLog $entry, string $dedupKey): void;
  // #endregion
}
