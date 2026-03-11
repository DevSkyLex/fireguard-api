<?php

declare(strict_types=1);

namespace Tenant\Presentation\Api\Provider\Tenant;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tenant\Application\UseCase\Query\Tenant\GetTenant\GetTenantResult;
use Tenant\Application\UseCase\Query\Tenant\ListTenants\ListTenantsQuery;
use Tenant\Presentation\Api\Dto\Output\Tenant\TenantOutput;

use function array_map;
use function array_slice;
use function count;
use function is_numeric;
use function max;

/**
 * Provider ListTenantsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<TenantOutput>
 */
final readonly class ListTenantsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * @return TraversablePaginator<TenantOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    if (null === $this->security->getUser()) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    /** @var PaginatedResult<GetTenantResult> $result */
    $result = $this->queryBus->ask(query: new ListTenantsQuery(
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
    ));

    $outputs = array_map(
      callback: function (GetTenantResult $tenant): TenantOutput {
        $output = new TenantOutput();
        $output->id = $tenant->tenantId;
        $output->name = $tenant->name;
        $output->isActive = $tenant->isActive;
        $output->accessTokenTtl = $tenant->settings->accessTokenTtl;
        $output->refreshTokenTtl = $tenant->settings->refreshTokenTtl;
        $output->requirePkce = $tenant->settings->requirePkce;
        $output->allowPublicClients = $tenant->settings->allowPublicClients;
        $output->allowedScopes = $tenant->settings->allowedScopes;
        $output->customIssuer = $tenant->settings->customIssuer;
        $output->createdAt = $tenant->createdAt->format('c');

        return $output;
      },
      array: $result->items,
    );

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name']);

    $total = count($outputs);

    $sorting = SortingExtractor::fromContext($context, ['name', 'isActive', 'createdAt'], 'name');
    $outputs = CollectionSorter::sort($outputs, $sorting);

    $outputs = array_slice($outputs, $offset, $itemsPerPage);

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $total,
    );
  }
  // #endregion
}
