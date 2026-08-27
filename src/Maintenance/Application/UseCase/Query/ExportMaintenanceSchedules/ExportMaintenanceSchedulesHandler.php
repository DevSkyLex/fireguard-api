<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules;

use Maintenance\Application\Contract\Export\{MaintenanceScheduleExportCandidate, MaintenanceScheduleExportRow};
use Maintenance\Application\Port\Outbound\Naming\{MaintenanceEquipmentNamingPort, MaintenanceFacilityNamingPort};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceExportTooLargeException, MaintenanceNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;

/**
 * UseCase ExportMaintenanceSchedulesHandler.
 *
 * Bounds the export before resolving a single display name: a cheap COUNT
 * against the same (cheap, indexed) filters {@see \Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules\ListMaintenanceSchedulesHandler}
 * applies for the `maintenance-schedule` resource, rejecting the request
 * with {@see MaintenanceExportTooLargeException} when it exceeds
 * {@see self::MAX_EXPORT_ROWS} — mirrors `Intervention\...\ExportInterventionsHandler`.
 * Under the cap, the matching rows are fetched once and the equipment
 * serial/facility display names are resolved in two bulk round trips (never
 * one query per row).
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ExportMaintenanceSchedulesHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant MAX_EXPORT_ROWS.
   *
   * Hard cap on the number of maintenance schedules a single export request
   * may match, mirroring `Intervention\...\ExportInterventionsHandler::MAX_EXPORT_ROWS`.
   *
   * @since 1.0.0
   *
   * @var int
   */
  public const int MAX_EXPORT_ROWS = 50_000;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRepositoryPort $schedules the schedule repository port
   * @param MaintenanceEquipmentNamingPort $equipmentNaming the equipment naming port
   * @param MaintenanceFacilityNamingPort $facilityNaming the facility naming port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   */
  public function __construct(
    private MaintenanceScheduleRepositoryPort $schedules,
    private MaintenanceEquipmentNamingPort $equipmentNaming,
    private MaintenanceFacilityNamingPort $facilityNaming,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ExportMaintenanceSchedulesQuery $query the query to handle
   *
   * @throws MaintenanceNotFoundException when the caller is outside the organization's scope
   * @throws MaintenanceAccessDeniedException when the caller lacks `organization.maintenance.read`
   * @throws MaintenanceExportTooLargeException when the filters match more than {@see self::MAX_EXPORT_ROWS} schedules
   *
   * @return ExportMaintenanceSchedulesResult the bounded, name-resolved export result
   */
  public function __invoke(ExportMaintenanceSchedulesQuery $query): ExportMaintenanceSchedulesResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.maintenance.read');
    if ($decision->isOutsideScope()) {
      throw MaintenanceNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new MaintenanceAccessDeniedException('Missing organization.maintenance.read permission.');
    }

    $total = $this->schedules->countForExport($query->organizationId, $query->facilityId, $query->equipmentType, $query->dueStatus);
    if ($total > self::MAX_EXPORT_ROWS) {
      throw MaintenanceExportTooLargeException::exceedsCap(matched: $total, maxRows: self::MAX_EXPORT_ROWS);
    }

    $candidates = $this->schedules->listExportCandidates($query->organizationId, $query->facilityId, $query->equipmentType, $query->dueStatus);

    $equipmentSerials = $this->equipmentNaming->findSerialNumbersByIds($this->uniqueEquipmentIds($candidates));
    $facilityNames = $this->facilityNaming->findNamesByIds($this->uniqueIds($candidates, static fn (MaintenanceScheduleExportCandidate $candidate): ?string => $candidate->facilityId));

    $rows = array_map(
      static fn (MaintenanceScheduleExportCandidate $candidate): MaintenanceScheduleExportRow => new MaintenanceScheduleExportRow(
        id: $candidate->id,
        equipmentId: $candidate->equipmentId,
        equipmentType: $candidate->equipmentType,
        equipmentSerial: $equipmentSerials[$candidate->equipmentId] ?? null,
        facilityId: $candidate->facilityId,
        facilityName: null === $candidate->facilityId ? null : ($facilityNames[$candidate->facilityId] ?? null),
        intervalOverride: $candidate->intervalOverride,
        lastInspectionClosedAt: $candidate->lastInspectionClosedAt,
        nextDueAt: $candidate->nextDueAt,
        dueStatus: $candidate->dueStatus,
        createdAt: $candidate->createdAt,
        updatedAt: $candidate->updatedAt,
      ),
      $candidates,
    );

    return new ExportMaintenanceSchedulesResult($rows, $total);
  }

  /**
   * Method uniqueEquipmentIds.
   *
   * @since 1.0.0
   *
   * @param list<MaintenanceScheduleExportCandidate> $candidates the candidates value
   *
   * @return list<string> the unique equipment identifiers
   */
  private function uniqueEquipmentIds(array $candidates): array
  {
    return array_values(array_unique(array_map(static fn (MaintenanceScheduleExportCandidate $candidate): string => $candidate->equipmentId, $candidates)));
  }

  /**
   * Method uniqueIds.
   *
   * @since 1.0.0
   *
   * @param list<MaintenanceScheduleExportCandidate> $candidates the candidates value
   * @param callable(MaintenanceScheduleExportCandidate): ?string $extract the field extractor
   *
   * @return list<string> the unique, non-null identifiers
   */
  private function uniqueIds(array $candidates, callable $extract): array
  {
    return array_values(array_unique(array_filter(array_map($extract, $candidates), static fn (?string $id): bool => null !== $id)));
  }
  // #endregion
}
