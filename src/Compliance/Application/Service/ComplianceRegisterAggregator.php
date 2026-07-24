<?php

declare(strict_types=1);

namespace Compliance\Application\Service;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\Port\Outbound\{ComplianceFacilityDirectoryPort, EquipmentComplianceStatisticsPort, InspectionComplianceStatisticsPort, MaintenanceComplianceStatisticsPort};

/**
 * Service ComplianceRegisterAggregator.
 *
 * Assembles the compliance register for an organization by fanning out to
 * the four cross-module statistics ports (mirrors
 * `GetOrganizationDashboardHandler`'s statistics-port fan-out pattern) and
 * grading each facility with {@see ComplianceStatusPolicy}. Equipment
 * up-to-date-vs-overdue status is READ from the Maintenance module's
 * current schedule `dueStatus` — periodicity is never recomputed here.
 *
 * Facility attribution is canonicalized to the equipment's facility
 * (`maintenance_schedules.facility_id` / `equipment.facility_id`);
 * non-conformities are attributed via their inspection's `facilityId`.
 * Equipment/inspections with no facility bucket into a synthetic
 * `unassigned` pseudo-facility so nothing silently vanishes from the
 * register.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ComplianceRegisterAggregator
{
  // #region Constants
  /**
   * Constant UNASSIGNED_FACILITY_KEY.
   *
   * Synthetic bucket key for equipment/inspections with no assigned
   * facility. Must match the constant used by every provider-hosted
   * statistics adapter.
   *
   * @since 1.0.0
   *
   * @var string
   */
  public const string UNASSIGNED_FACILITY_KEY = 'unassigned';

  /**
   * Constant UNASSIGNED_FACILITY_LABEL.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string UNASSIGNED_FACILITY_LABEL = 'Unassigned';

  /**
   * Constant MAX_PATH_DEPTH.
   *
   * Guards `buildPath()` against a corrupted parent cycle.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_PATH_DEPTH = 32;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param ComplianceFacilityDirectoryPort $facilityDirectory the facility directory port
   * @param MaintenanceComplianceStatisticsPort $maintenanceStatistics the maintenance due-status statistics port
   * @param InspectionComplianceStatisticsPort $inspectionStatistics the non-conformity statistics port
   * @param EquipmentComplianceStatisticsPort $equipmentStatistics the equipment inventory statistics port
   */
  public function __construct(
    private ComplianceFacilityDirectoryPort $facilityDirectory,
    private MaintenanceComplianceStatisticsPort $maintenanceStatistics,
    private InspectionComplianceStatisticsPort $inspectionStatistics,
    private EquipmentComplianceStatisticsPort $equipmentStatistics,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method buildFacilityViews.
   *
   * Assembles one {@see FacilityComplianceView} per organization facility,
   * plus a synthetic `unassigned` entry when unattributed equipment or
   * non-conformities exist.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   *
   * @return list<FacilityComplianceView> the assembled facility compliance views
   */
  public function buildFacilityViews(string $organizationId): array
  {
    $facilities = $this->facilityDirectory->listFacilities($organizationId);
    $dueStatusCounts = $this->maintenanceStatistics->dueStatusCountsByFacility($organizationId);
    $lastInspectionDates = $this->maintenanceStatistics->lastInspectionClosedAtByFacility($organizationId);
    $nonConformitiesBySeverity = $this->inspectionStatistics->openNonConformitiesBySeverityByFacility($organizationId);
    $equipmentInventory = $this->equipmentStatistics->equipmentInventoryByFacility($organizationId);

    $namesById = [];
    $parentById = [];
    foreach ($facilities as $facility) {
      $namesById[$facility['id']] = $facility['name'];
      $parentById[$facility['id']] = $facility['parentFacilityId'];
    }

    $views = [];
    foreach ($facilities as $facility) {
      $views[] = $this->buildView(
        facilityId: $facility['id'],
        name: $facility['name'],
        type: $facility['type'],
        parentFacilityId: $facility['parentFacilityId'],
        namesById: $namesById,
        parentById: $parentById,
        dueStatusCounts: $dueStatusCounts,
        lastInspectionDates: $lastInspectionDates,
        nonConformitiesBySeverity: $nonConformitiesBySeverity,
        equipmentInventory: $equipmentInventory,
      );
    }

    if ($this->hasUnassignedData($dueStatusCounts, $nonConformitiesBySeverity, $equipmentInventory)) {
      $views[] = $this->buildView(
        facilityId: self::UNASSIGNED_FACILITY_KEY,
        name: self::UNASSIGNED_FACILITY_LABEL,
        type: self::UNASSIGNED_FACILITY_KEY,
        parentFacilityId: null,
        namesById: $namesById,
        parentById: $parentById,
        dueStatusCounts: $dueStatusCounts,
        lastInspectionDates: $lastInspectionDates,
        nonConformitiesBySeverity: $nonConformitiesBySeverity,
        equipmentInventory: $equipmentInventory,
      );
    }

    return $views;
  }

  /**
   * @param array<string, string> $namesById
   * @param array<string, ?string> $parentById
   * @param array<string, array{up_to_date: int, due_soon: int, overdue: int, unscheduled: int}> $dueStatusCounts
   * @param array<string, string> $lastInspectionDates
   * @param array<string, array{low: int, medium: int, high: int, critical: int}> $nonConformitiesBySeverity
   * @param array<string, array{total: int, active: int}> $equipmentInventory
   */
  private function buildView(
    string $facilityId,
    string $name,
    string $type,
    ?string $parentFacilityId,
    array $namesById,
    array $parentById,
    array $dueStatusCounts,
    array $lastInspectionDates,
    array $nonConformitiesBySeverity,
    array $equipmentInventory,
  ): FacilityComplianceView {
    $due = $dueStatusCounts[$facilityId] ?? ['up_to_date' => 0, 'due_soon' => 0, 'overdue' => 0, 'unscheduled' => 0];
    $nonConformities = $nonConformitiesBySeverity[$facilityId] ?? ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
    $inventory = $equipmentInventory[$facilityId] ?? ['total' => 0, 'active' => 0];
    $trackedEquipmentCount = $due['up_to_date'] + $due['due_soon'] + $due['overdue'];

    return new FacilityComplianceView(
      facilityId: $facilityId,
      name: $name,
      type: $type,
      parentFacilityId: $parentFacilityId,
      path: self::UNASSIGNED_FACILITY_KEY === $facilityId ? $name : $this->buildPath($facilityId, $namesById, $parentById),
      status: ComplianceStatusPolicy::grade(
        overdueEquipmentCount: $due['overdue'],
        dueSoonEquipmentCount: $due['due_soon'],
        trackedEquipmentCount: $trackedEquipmentCount,
        openHighNonConformityCount: $nonConformities['high'],
        openCriticalNonConformityCount: $nonConformities['critical'],
      ),
      totalEquipmentCount: $inventory['total'],
      activeEquipmentCount: $inventory['active'],
      upToDateEquipmentCount: $due['up_to_date'],
      dueSoonEquipmentCount: $due['due_soon'],
      overdueEquipmentCount: $due['overdue'],
      unscheduledEquipmentCount: $due['unscheduled'],
      openLowNonConformityCount: $nonConformities['low'],
      openMediumNonConformityCount: $nonConformities['medium'],
      openHighNonConformityCount: $nonConformities['high'],
      openCriticalNonConformityCount: $nonConformities['critical'],
      lastInspectionAt: $lastInspectionDates[$facilityId] ?? null,
    );
  }

  /**
   * Method buildPath.
   *
   * Walks the parent chain to build a `Root / Child / Grandchild`
   * breadcrumb, guarding against a corrupted parent cycle.
   *
   * @since 1.0.0
   *
   * @param array<string, string> $namesById
   * @param array<string, ?string> $parentById
   */
  private function buildPath(string $facilityId, array $namesById, array $parentById, int $depth = 0): string
  {
    $name = $namesById[$facilityId] ?? $facilityId;

    if ($depth >= self::MAX_PATH_DEPTH) {
      return $name;
    }

    $parentId = $parentById[$facilityId] ?? null;
    if (null === $parentId || !isset($namesById[$parentId])) {
      return $name;
    }

    return $this->buildPath($parentId, $namesById, $parentById, $depth + 1) . ' / ' . $name;
  }

  /**
   * @param array<string, array{up_to_date: int, due_soon: int, overdue: int, unscheduled: int}> $dueStatusCounts
   * @param array<string, array{low: int, medium: int, high: int, critical: int}> $nonConformitiesBySeverity
   * @param array<string, array{total: int, active: int}> $equipmentInventory
   */
  private function hasUnassignedData(array $dueStatusCounts, array $nonConformitiesBySeverity, array $equipmentInventory): bool
  {
    return isset($dueStatusCounts[self::UNASSIGNED_FACILITY_KEY])
      || isset($nonConformitiesBySeverity[self::UNASSIGNED_FACILITY_KEY])
      || isset($equipmentInventory[self::UNASSIGNED_FACILITY_KEY]);
  }
  // #endregion
}
