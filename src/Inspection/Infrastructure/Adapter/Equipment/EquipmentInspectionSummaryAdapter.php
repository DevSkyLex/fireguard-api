<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Equipment;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Inspection\Application\Contract\Equipment\EquipmentInspectionSummary;
use Inspection\Application\Port\Outbound\EquipmentInspectionSummaryPort;

use function array_sum;

/**
 * Adapter EquipmentInspectionSummaryAdapter.
 *
 * Summarizes published inspection-owned data on the main connection.
 *
 * @category Adapter
 */
final readonly class EquipmentInspectionSummaryAdapter implements EquipmentInspectionSummaryPort
{
  /**
   * Method __construct.
   *
   * @param Connection $connection the explicit main connection
   */
  public function __construct(private Connection $connection)
  {
  }

  /**
   * Method find.
   *
   * @param string $organizationId the owning organization
   * @param string $equipmentId the equipment to summarize
   *
   * @return EquipmentInspectionSummary published facts, excluding intervention drafts
   */
  public function find(string $organizationId, string $equipmentId): EquipmentInspectionSummary
  {
    $parameters = ['organization' => $organizationId, 'equipment' => $equipmentId];
    /** @var list<array{severity:string,count:int|string}> $rows */
    $rows = $this->connection->fetchAllAssociative(
      "SELECT n.severity, COUNT(*) AS count FROM non_conformities n INNER JOIN inspections i ON i.id = n.inspection_id WHERE i.organization_id = :organization AND i.equipment_id = :equipment AND i.record_status = 'published' AND n.status IN ('open', 'in_progress') GROUP BY n.severity",
      $parameters,
    );
    $severity = ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0];
    foreach ($rows as $row) {
      $key = (string) $row['severity'];
      if (isset($severity[$key])) {
        $severity[$key] = (int) $row['count'];
      }
    }
    /** @var array{id:string,performed_at:string,result:string}|false $last */
    $last = $this->connection->fetchAssociative(
      "SELECT id, performed_at, result FROM inspections WHERE organization_id = :organization AND equipment_id = :equipment AND record_status = 'published' AND status = 'closed' ORDER BY performed_at DESC, id DESC LIMIT 1",
      $parameters,
    );

    return new EquipmentInspectionSummary(
      equipmentId: $equipmentId,
      openAnomalies: array_sum($severity),
      bySeverity: $severity,
      lastInspectionId: false !== $last ? (string) $last['id'] : null,
      lastInspectionPerformedAt: false !== $last ? new DateTimeImmutable((string) $last['performed_at'], new DateTimeZone('UTC'))->format('c') : null,
      lastInspectionResult: false !== $last ? (string) $last['result'] : null,
    );
  }
}
