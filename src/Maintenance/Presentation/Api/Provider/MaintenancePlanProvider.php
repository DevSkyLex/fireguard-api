<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use DateTimeImmutable;
use Maintenance\Application\Contract\Plan\MaintenancePlanDetails;
use Maintenance\Application\UseCase\Query\Plan\ReadMaintenancePlans\{ReadMaintenancePlansQuery, ReadMaintenancePlansResult};
use Maintenance\Presentation\Api\Dto\Output\{MaintenancePlanEngineOutput, MaintenancePlanPreviewOutput};
use Maintenance\Presentation\Api\Factory\MaintenancePlanOutputFactory;
use Maintenance\Presentation\Api\Operation\MaintenancePlanOperations;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function array_map;
use function is_string;
use function max;
use function min;

use const DATE_ATOM;

/** @implements ProviderInterface<object> */
final readonly class MaintenancePlanProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor, private MaintenancePlanOutputFactory $outputs, private RequestStack $requests)
  {
  }

  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $uriVariables['organizationId'] ?? null;
    $planId = $uriVariables['id'] ?? null;
    if (!is_string($organizationId) || (null !== $planId && !is_string($planId))) {
      throw new BadRequestHttpException('Invalid maintenance route identifiers.');
    }
    $request = $this->requests->getCurrentRequest();
    $page = max(1, $request?->query->getInt('page', 1) ?? 1);
    $size = max(1, min(100, $request?->query->getInt('itemsPerPage', 30) ?? 30));
    $action = match ($operation->getName()) {
      MaintenancePlanOperations::ENGINE => 'engine',
      MaintenancePlanOperations::PREVIEW => 'preview',
      MaintenancePlanOperations::GET => 'detail',
      default => 'list',
    };
    /** @var ReadMaintenancePlansResult $result */
    $result = $this->queries->ask(new ReadMaintenancePlansQuery(
      $organizationId,
      $actor,
      $action,
      $planId,
      $page,
      $size,
      $request?->query->get('equipmentId'),
      $request?->query->get('operationKind'),
      $request?->query->get('search'),
    ));
    if ('engine' === $action) {
      $output = new MaintenancePlanEngineOutput();
      $output->mode = $result->mode;
      $output->preparedCount = $result->preparedCount;

      return $output;
    }
    if ('preview' === $action) {
      $output = new MaintenancePlanPreviewOutput();
      $output->dates = array_map(static fn (DateTimeImmutable $date): string => $date->format(DATE_ATOM), $result->dates);

      return $output;
    }
    $items = array_map(fn (MaintenancePlanDetails $details): object => $this->outputs->fromDetails($details), $result->items);

    return 'detail' === $action ? $items[0] : new TraversablePaginator(new ArrayIterator($items), $page, $size, $result->total);
  }
}
