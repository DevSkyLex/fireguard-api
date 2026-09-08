<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Schedule;

use DateTimeImmutable;
use Maintenance\Application\Contract\Export\MaintenanceScheduleExportCandidate;
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleSnapshot, MaintenanceScheduleView};

/**
 * Port MaintenanceScheduleRepositoryPort.
 *
 * Persists and reads maintenance schedules. Schedules are record-level
 * entities (no domain aggregate), the same treatment
 * `InterventionLabelPort`/`InterventionTemplatePort` give their record-level
 * entities: the recompute POLICY lives in
 * {@see \Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy} and
 * the application services around it, while this port only persists the
 * resulting state.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface MaintenanceScheduleRepositoryPort
{
  // #region Methods
  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param string $id the maintenance schedule identifier
   *
   * @return ?MaintenanceScheduleView the schedule view, or null when not found
   */
  public function findById(string $id): ?MaintenanceScheduleView;

  /**
   * Method findByOrganizationAndEquipment.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   *
   * @return ?MaintenanceScheduleView the schedule view, or null when not found
   */
  public function findByOrganizationAndEquipment(string $organizationId, string $equipmentId): ?MaintenanceScheduleView;

  /**
   * Method list.
   *
   * Lists an organization's maintenance schedules with optional filters,
   * ordered by next due date (earliest first, nulls last).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   * @param ?DateTimeImmutable $dueBefore optional upper bound on the next due date
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   *
   * @return MaintenanceSchedulePage the schedule page result
   */
  public function list(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    ?string $dueStatus,
    ?DateTimeImmutable $dueBefore,
    int $page,
    int $itemsPerPage,
  ): MaintenanceSchedulePage;

  /**
   * Method listDueForCampaign.
   *
   * Lists `due_soon`/`overdue` schedules for an organization matching the
   * campaign filters, unpaginated (bounded by the organization's own
   * equipment volume).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param DateTimeImmutable $dueBefore the upper bound on the next due date
   *
   * @return list<MaintenanceScheduleView> the matching schedules
   */
  public function listDueForCampaign(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    DateTimeImmutable $dueBefore,
  ): array;

  /**
   * Method pageForSweep.
   *
   * Lists a page of every schedule across every organization, ordered by
   * id, for the recurring sweep to recompute in bounded batches.
   *
   * @since 1.0.0
   *
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   *
   * @return MaintenanceSchedulePage the schedule page result
   */
  public function pageForSweep(int $limit, int $offset): MaintenanceSchedulePage;

  /**
   * Method save.
   *
   * Creates or updates a maintenance schedule from a full snapshot.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleSnapshot $snapshot the schedule snapshot
   *
   * @return MaintenanceScheduleView the persisted schedule view
   */
  public function save(MaintenanceScheduleSnapshot $snapshot): MaintenanceScheduleView;

  /**
   * Method removeByOrganizationAndEquipment.
   *
   * Removes the schedule for a decommissioned (or otherwise no longer
   * trackable) piece of equipment. A no-op when no schedule exists.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   */
  public function removeByOrganizationAndEquipment(string $organizationId, string $equipmentId): void;

  /**
   * Method countForExport.
   *
   * Counts an organization's maintenance schedules matching the given
   * (cheap, indexed) filters, without fetching a single row. Backs the CSV
   * export's row cap, mirroring `InterventionWorkflowGatewayPort::countInterventions()`.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   *
   * @return int the matching schedule count
   */
  public function countForExport(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    ?string $dueStatus,
  ): int;

  /**
   * Method listExportCandidates.
   *
   * Lists every schedule matching the given organization and filters, in the
   * CSV export's stable order (`updatedAt` DESC, `id` ASC), as lightweight
   * {@see MaintenanceScheduleExportCandidate} rows. Callers must first bound
   * the result with {@see self::countForExport()}.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   *
   * @return list<MaintenanceScheduleExportCandidate> the matching schedule rows
   */
  public function listExportCandidates(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    ?string $dueStatus,
  ): array;
  // #endregion
}
