<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Provider\Rate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use MaintenanceCost\Application\UseCase\Query\Rate\ListMaintenanceRates\{ListMaintenanceRatesQuery, ListMaintenanceRatesResult};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Presentation\Api\Dto\Output\Rate\MaintenanceRateOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function is_string;

/** @implements ProviderInterface<MaintenanceRateOutput> */
final readonly class MaintenanceRateProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @return TraversablePaginator<MaintenanceRateOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId)) {
      throw MaintenanceCostException::notFound();
    }
    $request = $this->requests->getCurrentRequest();
    $member = $request?->query->getString('memberId', '') ?? '';
    /** @var ListMaintenanceRatesResult $result */
    $result = $this->queries->ask(new ListMaintenanceRatesQuery($organizationId, $actor, '' === $member ? null : $member, $request?->query->getInt('page', 1) ?? 1, $request?->query->getInt('itemsPerPage', 30) ?? 30));

    return new TraversablePaginator(new ArrayIterator(array_map(static fn ($rate): MaintenanceRateOutput => MaintenanceRateOutput::fromSnapshot($rate), $result->items)), (float) $result->page, (float) $result->itemsPerPage, (float) $result->total);
  }
}
