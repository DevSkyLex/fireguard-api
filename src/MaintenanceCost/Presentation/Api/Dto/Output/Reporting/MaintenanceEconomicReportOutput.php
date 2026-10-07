<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Dto\Output\Reporting;

use MaintenanceCost\Application\Contract\Reporting\{MaintenanceEconomicAmount, MaintenanceEconomicReport, MaintenanceEconomicRow};
use Procurement\Application\Contract\Reporting\ProcurementEconomicAmount;
use Symfony\Component\Serializer\Attribute\Groups;

use function array_map;

/**
 * Class MaintenanceEconomicReportOutput
 *
 * Presents exact totals, explicit unallocated values and paginated allocation rows.
 *
 * @category Output
 */
final class MaintenanceEconomicReportOutput
{
  #[Groups(['maintenance_economic:read'])]
  public string $id = '';

  #[Groups(['maintenance_economic:read'])]
  public string $organizationId = '';

  #[Groups(['maintenance_economic:read'])]
  public string $from = '';

  #[Groups(['maintenance_economic:read'])]
  public string $to = '';

  #[Groups(['maintenance_economic:read'])]
  public string $groupBy = '';

  #[Groups(['maintenance_economic:read'])]
  public string $currency = '';

  #[Groups(['maintenance_economic:read'])]
  public int $page = 1;

  #[Groups(['maintenance_economic:read'])]
  public int $itemsPerPage = 30;

  #[Groups(['maintenance_economic:read'])]
  public int $totalItems = 0;

  #[Groups(['maintenance_economic:read'])]
  public int $interventionCount = 0;

  #[Groups(['maintenance_economic:read'])]
  public int $publishedInterventionCount = 0;

  #[Groups(['maintenance_economic:read'])]
  public int $liveInterventionCount = 0;

  #[Groups(['maintenance_economic:read'])]
  public int $missingSnapshotCount = 0;

  /**
   * @var list<array<string,mixed>>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $rows = [];

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $unallocated = [];

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $current = [];

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $frozen = [];

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $planned = [];

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $budget = [];

  #[Groups(['maintenance_economic:read'])]
  public ?string $variance = null;

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $reconciliation = [];

  /**
   * @var array<string,mixed>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $procurement = [];

  /**
   * @var list<array{id:string,number:int,name:string,status:string,snapshotState:string}>
   */
  #[Groups(['maintenance_economic:read'])]
  public array $dossiers = [];

  public static function fromReport(MaintenanceEconomicReport $report): self
  {
    $output = new self();
    $output->id = $report->organizationId;
    foreach (['organizationId', 'from', 'to', 'groupBy', 'currency', 'page', 'itemsPerPage', 'totalItems', 'interventionCount', 'publishedInterventionCount', 'liveInterventionCount', 'missingSnapshotCount', 'variance'] as $field) {
      $output->{$field} = $report->{$field};
    }
    $output->rows = array_map(static fn (MaintenanceEconomicRow $row): array => $row->toArray(), $report->rows);
    $output->dossiers = $report->dossiers;
    $output->unallocated = $report->unallocated->toArray();
    foreach (['current', 'frozen', 'planned', 'budget'] as $field) {
      $output->{$field} = $report->{$field}->toArray();
    }
    foreach ($report->reconciliation as $key => $value) {
      $output->reconciliation[$key] = $value instanceof MaintenanceEconomicAmount ? $value->toArray() : $value;
    }
    $overview = $report->procurement;
    $output->procurement = ['organizationId' => $overview->organizationId, 'currency' => $overview->currency, 'from' => $overview->from, 'to' => $overview->to, 'basis' => $overview->basis, 'receiptScope' => $overview->receiptScope, 'orderCount' => $overview->orderCount, 'receiptCount' => $overview->receiptCount, 'pendingIndividualizationCount' => $overview->pendingIndividualizationCount];
    foreach (['ordered', 'received', 'outstanding', 'returned'] as $field) {
      /** @var ProcurementEconomicAmount $amount */
      $amount = $overview->{$field};
      $output->procurement[$field] = ['total' => $amount->total, 'knownTotal' => $amount->knownTotal, 'complete' => $amount->complete];
    }

    return $output;
  }
}
