<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Compliance;

use Compliance\Application\Port\Outbound\MaintenanceComplianceStatisticsPort;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use Throwable;

/**
 * Adapter MaintenanceComplianceStatisticsAdapter.
 *
 * Implements the Compliance module's due-status statistics port by
 * querying `maintenance_schedules` directly, grouped by facility — the same
 * treatment `Equipment\Infrastructure\Adapter\Maintenance\EquipmentMaintenanceDirectoryAdapter`
 * gives a cross-module read model with no equivalent on the owning
 * repository's port. Reads the CURRENT `dueStatus`/`lastInspectionClosedAt`
 * columns only; periodicity is never recomputed here — that recompute
 * policy belongs to `Maintenance\Domain\Service\MaintenanceScheduleRecomputePolicy`
 * and the recurring sweep alone.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceComplianceStatisticsAdapter implements MaintenanceComplianceStatisticsPort
{
  // #region Constants
  /**
   * Constant UNASSIGNED_FACILITY_KEY.
   *
   * Must match `Compliance\Application\Service\ComplianceRegisterAggregator::UNASSIGNED_FACILITY_KEY`.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string UNASSIGNED_FACILITY_KEY = 'unassigned';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function dueStatusCountsByFacility(string $organizationId): array
  {
    $sql = <<<'SQL'
        SELECT
          COALESCE(facility_id, :unassignedKey) AS facility_key,
          due_status AS due_status,
          COUNT(id) AS due_status_count
        FROM maintenance_schedules
        WHERE organization_id = :organizationId
        GROUP BY facility_key, due_status
      SQL;

    /** @var list<array{facility_key: string, due_status: string, due_status_count: int|string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'unassignedKey' => self::UNASSIGNED_FACILITY_KEY,
      'organizationId' => $organizationId,
    ])->fetchAllAssociative();

    $counts = [];
    foreach ($rows as $row) {
      $facilityKey = $row['facility_key'];
      $counts[$facilityKey] ??= [
        MaintenanceDueStatus::UP_TO_DATE->value => 0,
        MaintenanceDueStatus::DUE_SOON->value => 0,
        MaintenanceDueStatus::OVERDUE->value => 0,
        MaintenanceDueStatus::UNSCHEDULED->value => 0,
      ];
      if (isset($counts[$facilityKey][$row['due_status']])) {
        $counts[$facilityKey][$row['due_status']] = (int) $row['due_status_count'];
      }
    }

    /** @var array<string, array{up_to_date: int, due_soon: int, overdue: int, unscheduled: int}> $counts */
    return $counts;
  }

  public function lastInspectionClosedAtByFacility(string $organizationId): array
  {
    $sql = <<<'SQL'
        SELECT
          COALESCE(facility_id, :unassignedKey) AS facility_key,
          MAX(last_inspection_closed_at) AS last_inspection_closed_at
        FROM maintenance_schedules
        WHERE organization_id = :organizationId
          AND last_inspection_closed_at IS NOT NULL
        GROUP BY facility_key
      SQL;

    /** @var list<array{facility_key: string, last_inspection_closed_at: string}> $rows */
    $rows = $this->entityManager->getConnection()->executeQuery($sql, [
      'unassignedKey' => self::UNASSIGNED_FACILITY_KEY,
      'organizationId' => $organizationId,
    ])->fetchAllAssociative();

    $dates = [];
    foreach ($rows as $row) {
      $formatted = $this->formatStorageTimestamp($row['last_inspection_closed_at']);
      if (null !== $formatted) {
        $dates[$row['facility_key']] = $formatted;
      }
    }

    return $dates;
  }

  /**
   * Method formatStorageTimestamp.
   *
   * Reinterprets a raw storage timestamp (no DBAL type conversion, since
   * this adapter reads via raw SQL) as UTC and formats it as ISO 8601.
   *
   * @since 1.0.0
   *
   * @param string $raw the raw stored timestamp value
   *
   * @return ?string the ISO 8601 datetime, or null when unparseable
   */
  private function formatStorageTimestamp(string $raw): ?string
  {
    try {
      $value = new DateTimeImmutable($raw, new DateTimeZone('UTC'));
    } catch (Throwable) {
      return null;
    }

    return '000000' === $value->format('u') ? $value->format('Y-m-d\\TH:i:sP') : $value->format('Y-m-d\\TH:i:s.uP');
  }
  // #endregion
}
