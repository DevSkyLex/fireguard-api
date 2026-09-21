<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Adapter\Maintenance;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceInspectionHistoryPort;

use function is_string;

/** Closed inspections are immutable; updated_at is the closure instant exposed by CloseInspection. */
final readonly class InspectionMaintenanceHistoryAdapter implements MaintenanceInspectionHistoryPort
{
  public function __construct(private Connection $connection)
  {
  }

  public function latestClosedAt(string $organizationId, string $equipmentId): ?DateTimeImmutable
  {
    $value = $this->connection->fetchOne(
      "SELECT MAX(updated_at) FROM inspections WHERE organization_id = :organization AND equipment_id = :equipment AND record_status = 'published' AND status = 'closed'",
      ['organization' => $organizationId, 'equipment' => $equipmentId],
    );

    return is_string($value) ? new DateTimeImmutable($value, new DateTimeZone('UTC')) : null;
  }
}
