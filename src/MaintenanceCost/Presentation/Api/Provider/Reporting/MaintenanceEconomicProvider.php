<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Provider\Reporting;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Intervention\Application\Contract\Publication\InterventionEconomicContext;
use MaintenanceCost\Application\UseCase\Query\Reporting\ListMaintenanceEconomicDossiers\{ListMaintenanceEconomicDossiersQuery, ListMaintenanceEconomicDossiersResult};
use MaintenanceCost\Application\UseCase\Query\Reporting\ReadMaintenanceEconomicReport\{ReadMaintenanceEconomicReportQuery, ReadMaintenanceEconomicReportResult};
use MaintenanceCost\Presentation\Api\Dto\Output\Reporting\{MaintenanceEconomicDossierOutput, MaintenanceEconomicReportOutput};
use MaintenanceCost\Presentation\Api\Operation\Reporting\MaintenanceEconomicOperations;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function is_string;

/**
 * Class MaintenanceEconomicProvider
 *
 * Translates transport filters into authorized finance queries.
 *
 * @category Provider
 *
 * @implements ProviderInterface<MaintenanceEconomicDossierOutput|MaintenanceEconomicReportOutput>
 */
final readonly class MaintenanceEconomicProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables
   * @param array<string,mixed> $context
   *
   * @return MaintenanceEconomicReportOutput|TraversablePaginator<MaintenanceEconomicDossierOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): MaintenanceEconomicReportOutput|TraversablePaginator
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = is_string($uriVariables['organizationId'] ?? null) ? $uriVariables['organizationId'] : '';
    $request = $this->requests->getCurrentRequest();
    $page = $request?->query->getInt('page', 1) ?? 1;
    $limit = $request?->query->getInt('itemsPerPage', 30) ?? 30;
    $siteId = $this->filter('siteId');
    $customerId = $this->filter('customerId');
    $equipmentId = $this->filter('equipmentId');
    if (MaintenanceEconomicOperations::DOSSIERS === $operation->getName()) {
      /** @var ListMaintenanceEconomicDossiersResult $result */
      $result = $this->queries->ask(new ListMaintenanceEconomicDossiersQuery($actor, $organizationId, $page, $limit, $this->filter('search'), $this->filter('from'), $this->filter('to'), $siteId, $customerId, $equipmentId));
      $directory = $result->page;

      return new TraversablePaginator(new ArrayIterator(array_map(static fn (InterventionEconomicContext $context): MaintenanceEconomicDossierOutput => MaintenanceEconomicDossierOutput::fromContext($context, $result->equipment[$context->id] ?? []), $directory->items)), (float) $directory->page, (float) $directory->itemsPerPage, (float) $directory->totalItems);
    }
    /** @var ReadMaintenanceEconomicReportResult $result */
    $result = $this->queries->ask(new ReadMaintenanceEconomicReportQuery($actor, $organizationId, $this->filter('from') ?? '', $this->filter('to') ?? '', $this->filter('groupBy') ?? 'equipment', $siteId, $customerId, $equipmentId, $page, $limit));

    return MaintenanceEconomicReportOutput::fromReport($result->report);
  }

  private function filter(string $name): ?string
  {
    $value = $this->requests->getCurrentRequest()?->query->getString($name, '') ?? '';

    return '' === $value ? null : $value;
  }
}
