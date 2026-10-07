<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Reporting;

use Procurement\Application\Contract\Reporting\ProcurementEconomicOverview;

/**
 * Class MaintenanceEconomicReport
 *
 * Full-scope exact aggregates retain excluded targets for reconciliation.
 *
 * @category Contract
 */
final readonly class MaintenanceEconomicReport
{
  /**
   * @param list<MaintenanceEconomicRow> $rows paginated allocated destinations
   * @param list<array{id:string,number:int,name:string,status:string,snapshotState:string}> $dossiers minimal retained source labels, bounded to 500
   * @param array{sourceCurrent:MaintenanceEconomicAmount,excludedCurrent:MaintenanceEconomicAmount,sourceFrozen:MaintenanceEconomicAmount,excludedFrozen:MaintenanceEconomicAmount,sourcePlanned:MaintenanceEconomicAmount,excludedPlanned:MaintenanceEconomicAmount,reconciled:bool} $reconciliation original dossier totals versus selected and excluded targets
   */
  public function __construct(public string $organizationId, public string $from, public string $to, public string $groupBy, public string $currency, public int $page, public int $itemsPerPage, public int $totalItems, public int $interventionCount, public int $publishedInterventionCount, public int $liveInterventionCount, public int $missingSnapshotCount, public array $rows, public MaintenanceEconomicRow $unallocated, public MaintenanceEconomicAmount $current, public MaintenanceEconomicAmount $frozen, public MaintenanceEconomicAmount $planned, public MaintenanceEconomicAmount $budget, public ?string $variance, public array $reconciliation, public ProcurementEconomicOverview $procurement, public array $dossiers)
  {
  }
}
