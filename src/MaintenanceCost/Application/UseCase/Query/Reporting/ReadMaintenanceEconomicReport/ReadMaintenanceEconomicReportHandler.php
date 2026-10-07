<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Reporting\ReadMaintenanceEconomicReport;

use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use MaintenanceCost\Application\Port\Inbound\{MaintenanceCostReadPort, MaintenanceCurrencyPort};
use MaintenanceCost\Application\Service\{MaintenanceCostAccessGuard, MaintenanceEconomicAggregator};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\ValueObject\MaintenanceEconomicWindow;
use Procurement\Application\Contract\Reporting\ProcurementEconomicOverviewUnavailable;
use Procurement\Application\Port\Inbound\ProcurementEconomicOverviewPort;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\ValueObject\Uuid;

use function count;
use function in_array;

/**
 * Class ReadMaintenanceEconomicReportHandler
 *
 * Authorizes finance before requesting bounded operational and resource facts.
 *
 * @category UseCase
 */
final readonly class ReadMaintenanceEconomicReportHandler implements QueryHandler
{
  public function __construct(private MaintenanceCostAccessGuard $access, private InterventionPublicationFactsPort $work, private MaintenanceCostReadPort $costs, private MaintenanceCurrencyPort $currencies, private ProcurementEconomicOverviewPort $procurement, private MaintenanceEconomicAggregator $aggregator)
  {
  }

  public function __invoke(ReadMaintenanceEconomicReportQuery $query): ReadMaintenanceEconomicReportResult
  {
    $this->access->assertAccess($query->actorId, $query->organizationId, false);
    $window = MaintenanceEconomicWindow::fromDates($query->from, $query->to);
    if (!in_array($query->groupBy, ['equipment', 'site', 'customer'], true) || $query->page < 1 || $query->page > 100000 || $query->itemsPerPage < 1 || $query->itemsPerPage > 100) {
      throw MaintenanceCostException::invalid('Use equipment, site or customer grouping and a valid page of at most 100 rows.');
    }
    foreach ([$query->siteId, $query->customerId, $query->equipmentId] as $identifier) {
      if (null !== $identifier) {
        new Uuid($identifier);
      }
    }
    // Financial direct-material targets need not exist among operational tasks.
    $contexts = $this->work->economicWindow($query->organizationId, $window->from, $window->to, null, null, null, 501);
    if ($contexts->totalItems > 500) {
      throw MaintenanceCostException::invalid('The report exceeds 500 interventions; narrow its date window.');
    }
    if (count($contexts->items) !== $contexts->totalItems) {
      throw MaintenanceCostException::conflict('The bounded source window is incomplete; no partial economic total can be returned.');
    }
    $views = [];
    $factCount = 0;
    foreach ($contexts->items as $context) {
      $view = $this->costs->view($query->organizationId, $context->id);
      $factCount += count($view->current->items) + count($view->frozen?->totals->items ?? []) + count($view->planning->resources);
      if ($factCount > 50000) {
        throw MaintenanceCostException::invalid('The report exceeds 50000 contributions; narrow its dates or target filters.');
      }
      $views[$context->id] = $view;
    }

    try {
      $procurement = $this->procurement->overview($query->organizationId, $window->from, $window->to);
    } catch (ProcurementEconomicOverviewUnavailable $error) {
      throw MaintenanceCostException::invalid($error->getMessage());
    }
    $currency = $this->currencies->forOrganization($query->organizationId);
    if ($procurement->currency !== $currency) {
      throw MaintenanceCostException::conflict('Procurement uses another organization currency.');
    }

    return new ReadMaintenanceEconomicReportResult($this->aggregator->report($query, $currency, $contexts->items, $views, $procurement));
  }
}
