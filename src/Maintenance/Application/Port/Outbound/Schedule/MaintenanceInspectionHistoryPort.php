<?php

declare(strict_types=1);

namespace Maintenance\Application\Port\Outbound\Schedule;

use DateTimeImmutable;

/** Published closure history used to recover a missed immediate synchronization. */
interface MaintenanceInspectionHistoryPort
{
  /**
   * Method latestClosedAt.
   *
   * Finds the latest closed inspection timestamp for an organization's equipment.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   *
   * @return DateTimeImmutable|null the latest closure time, when recorded
   */
  public function latestClosedAt(string $organizationId, string $equipmentId): ?DateTimeImmutable;
}
