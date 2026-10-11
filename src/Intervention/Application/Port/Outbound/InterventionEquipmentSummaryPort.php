<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

/** Grouped organization-scoped equipment display facts.
 * @category Port
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionEquipmentSummaryPort
{
  /** @param list<string> $equipmentIds
   * @return array<string, array{type: string, serialNumber: ?string}>
   */
  public function summaries(string $organizationId, array $equipmentIds): array;
}
