<?php

declare(strict_types=1);

namespace Inspection\Application\Port\Outbound;

use Inspection\Application\Contract\Equipment\EquipmentInspectionSummary;

/**
 * Port EquipmentInspectionSummaryPort.
 *
 * @category Port
 */
interface EquipmentInspectionSummaryPort
{
  /**
   * Method find.
   *
   * @param string $organizationId the owning organization
   * @param string $equipmentId the equipment to summarize
   *
   * @return EquipmentInspectionSummary published inspection facts and findings
   */
  public function find(string $organizationId, string $equipmentId): EquipmentInspectionSummary;
}
