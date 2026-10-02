<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Maintenance;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{ArrayParameterType, Connection};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceInspectionHistoryPort;

use function is_string;

/** Closed inspections are immutable; updated_at is the closure instant exposed by CloseInspection. */
final readonly class InspectionMaintenanceHistoryAdapter implements MaintenanceInspectionHistoryPort
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the main database connection to read inspection closure history for maintenance calculations.
   *
   * @access public
   *
   * @param Connection $connection the main database connection
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method latestClosedAt.
   *
   * Reads the latest closure timestamp from published closed inspections for the equipment.
   *
   * @access public
   *
   * @param string $organizationId the owning organization identifier
   * @param string $equipmentId the equipment identifier
   *
   * @return DateTimeImmutable|null the latest closure time when a matching inspection exists
   */
  public function latestClosedAt(string $organizationId, string $equipmentId): ?DateTimeImmutable
  {
    $value = $this->connection->fetchOne(
      "SELECT MAX(updated_at) FROM inspections WHERE organization_id = :organization AND equipment_id = :equipment AND record_status = 'published' AND status = 'closed'",
      ['organization' => $organizationId, 'equipment' => $equipmentId],
    );

    return is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : null;
  }

  /**
   * @param list<string> $equipmentIds
   *
   * @return array<string, DateTimeImmutable>
   */
  public function latestClosedAtForEquipment(string $organizationId, array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }
    /** @var list<array{equipment_id: string, closed_at: string}> $rows */
    $rows = $this->connection->fetchAllAssociative(
      "SELECT equipment_id, MAX(updated_at) AS closed_at FROM inspections WHERE organization_id = :organization
       AND equipment_id IN (:equipment) AND record_status = 'published' AND status = 'closed' GROUP BY equipment_id",
      ['organization' => $organizationId, 'equipment' => $equipmentIds],
      ['equipment' => ArrayParameterType::STRING],
    );
    $dates = [];
    foreach ($rows as $row) {
      $dates[(string) $row['equipment_id']] = new DateTimeImmutable((string) $row['closed_at'], new DateTimeZone('UTC'));
    }

    return $dates;
  }
  // #endregion
}
