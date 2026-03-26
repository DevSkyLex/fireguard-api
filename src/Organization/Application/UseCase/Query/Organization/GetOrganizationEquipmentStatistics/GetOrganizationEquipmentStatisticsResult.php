<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationEquipmentStatistics;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetOrganizationEquipmentStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationEquipmentStatisticsResult implements ResultMessage
{
  /**
   * @param array<string, int> $countsByType map of equipment type => count
   */
  public function __construct(
    public int $totalCount,
    public int $inStockCount,
    public int $operationalCount,
    public int $underMaintenanceCount,
    public int $decommissionedCount,
    public array $countsByType,
  ) {
  }
}
