<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Reporting\ListMaintenanceEconomicDossiers;

use Intervention\Application\Port\Inbound\InterventionPublicationFactsPort;
use MaintenanceCost\Application\Service\{MaintenanceCostAccessGuard, MaintenanceEconomicDirectory};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Domain\ValueObject\MaintenanceEconomicWindow;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\ValueObject\Uuid;

use function mb_strlen;

/**
 * Class ListMaintenanceEconomicDossiersHandler
 *
 * Protects minimal dossier identities with the dedicated financial read scope.
 *
 * @category UseCase
 */
final readonly class ListMaintenanceEconomicDossiersHandler implements QueryHandler
{
  public function __construct(private MaintenanceCostAccessGuard $access, private InterventionPublicationFactsPort $work, private MaintenanceEconomicDirectory $directory)
  {
  }

  public function __invoke(ListMaintenanceEconomicDossiersQuery $query): ListMaintenanceEconomicDossiersResult
  {
    $this->access->assertAccess($query->actorId, $query->organizationId, false);
    if ($query->page < 1 || $query->page > 100000 || $query->itemsPerPage < 1 || $query->itemsPerPage > 100 || (null !== $query->search && mb_strlen($query->search) > 160) || (null === $query->from) !== (null === $query->to)) {
      throw MaintenanceCostException::invalid('Use valid pagination, a search of at most 160 characters and either both dates or neither.');
    }
    $window = null !== $query->from && null !== $query->to ? MaintenanceEconomicWindow::fromDates($query->from, $query->to) : null;
    foreach ([$query->siteId, $query->customerId, $query->equipmentId] as $identifier) {
      if (null !== $identifier) {
        new Uuid($identifier);
      }
    }

    $financialIds = $this->directory->matchingIds($query->organizationId, $query->siteId, $query->customerId, $query->equipmentId);
    $page = $this->work->economicPage($query->organizationId, $query->page, $query->itemsPerPage, $query->search, $window?->from, $window?->to, $query->siteId, $query->customerId, $query->equipmentId, $financialIds);
    $equipment = [];
    foreach ($page->items as $context) {
      $equipment[$context->id] = $this->directory->equipment($query->organizationId, $context->id);
    }

    return new ListMaintenanceEconomicDossiersResult($page, $equipment);
  }
}
