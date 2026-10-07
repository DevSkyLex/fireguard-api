<?php

declare(strict_types=1);

namespace ServiceRequest\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use ServiceRequest\Application\UseCase\Query\GetServiceRequest\{GetServiceRequestQuery, GetServiceRequestResult};
use ServiceRequest\Application\UseCase\Query\ListServiceRequests\{ListServiceRequestsQuery, ListServiceRequestsResult};
use ServiceRequest\Presentation\Api\Dto\Output\ServiceRequestOutput;
use ServiceRequest\Presentation\Api\Operation\ServiceRequestOperations;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function is_string;

/**
 * Class ServiceRequestProvider
 *
 * Translates scoped identifiers and paginated filters without querying persistence directly.
 *
 * @category Provider
 *
 * @implements ProviderInterface<ServiceRequestOutput>
 */
final readonly class ServiceRequestProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables
   * @param array<string,mixed> $context
   *
   * @return ServiceRequestOutput|TraversablePaginator<ServiceRequestOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ServiceRequestOutput|TraversablePaginator
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $this->id($uriVariables, 'organizationId');
    if (ServiceRequestOperations::LIST === $operation->getName()) {
      $request = $this->requests->getCurrentRequest();
      /** @var ListServiceRequestsResult $result */
      $result = $this->queries->ask(new ListServiceRequestsQuery($actorId, $organizationId, $this->filter($request?->query->get('status')), $this->filter($request?->query->get('equipmentId')), $this->filter($request?->query->get('siteId')), $request?->query->getString('search', '') ?? '', $request?->query->getInt('page', 1) ?? 1, $request?->query->getInt('itemsPerPage', 30) ?? 30));

      return new TraversablePaginator(new ArrayIterator(array_map(ServiceRequestOutput::fromView(...), $result->items)), (float) $result->page, (float) $result->itemsPerPage, (float) $result->total);
    }
    /** @var GetServiceRequestResult $result */
    $result = $this->queries->ask(new GetServiceRequestQuery($actorId, $organizationId, $this->id($uriVariables, 'id')));

    return ServiceRequestOutput::fromView($result->request);
  }

  private function filter(mixed $value): ?string
  {
    return is_string($value) && '' !== $value ? $value : null;
  }

  /**
   * @param array<string,mixed> $variables
   */
  private function id(array $variables, string $key): string
  {
    $value = $variables[$key] ?? null;

    return is_string($value) ? $value : '';
  }
}
