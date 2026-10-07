<?php

declare(strict_types=1);

namespace Customer\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Customer\Application\UseCase\Query\GetCustomer\{GetCustomerQuery, GetCustomerResult};
use Customer\Application\UseCase\Query\ListCustomers\{ListCustomersQuery, ListCustomersResult};
use Customer\Presentation\Api\Dto\Output\CustomerOutput;
use Customer\Presentation\Api\Operation\CustomerOperations;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function is_string;

/**
 * Class CustomerProvider
 *
 * Translates URI and filters into authorized customer queries.
 *
 * @category Provider
 *
 * @implements ProviderInterface<CustomerOutput>
 */
final readonly class CustomerProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables @param array<string,mixed> $context @return CustomerOutput|TraversablePaginator<CustomerOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): CustomerOutput|TraversablePaginator
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $this->identifier($uriVariables, 'organizationId');
    if (CustomerOperations::LIST === $operation->getName()) {
      $request = $this->requests->getCurrentRequest();
      /** @var ListCustomersResult $result */
      $result = $this->queries->ask(new ListCustomersQuery($actorId, $organizationId, $request?->query->getString('search', '') ?? '', $request?->query->getBoolean('archived', false) ?? false, $request?->query->getInt('page', 1) ?? 1, $request?->query->getInt('itemsPerPage', 30) ?? 30));

      return new TraversablePaginator(new ArrayIterator(array_map(CustomerOutput::fromView(...), $result->items)), (float) $result->page, (float) $result->itemsPerPage, (float) $result->total);
    }
    /** @var GetCustomerResult $result */
    $result = $this->queries->ask(new GetCustomerQuery($actorId, $organizationId, $this->identifier($uriVariables, 'id')));

    return CustomerOutput::fromView($result->customer);
  }

  /**
   * @param array<string,mixed> $variables
   */
  private function identifier(array $variables, string $key): string
  {
    $value = $variables[$key] ?? null;

    return is_string($value) ? $value : '';
  }
}
