<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\UseCase\Query\Organization\GetOrganization\GetOrganizationResult;
use Organization\Application\UseCase\Query\Organization\ListUserOrganizations\ListUserOrganizationsQuery;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationOutput;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_slice;
use function count;
use function is_numeric;
use function max;

/**
 * Provider ListUserOrganizationsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationOutput>
 */
final readonly class ListUserOrganizationsProvider implements ProviderInterface
{
  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method provide.
   *
   * Provides a paginated list of organizations for the authenticated user.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return TraversablePaginator<OrganizationOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
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

    /** @var PaginatedResult<GetOrganizationResult> $result */
    $result = $this->queryBus->ask(new ListUserOrganizationsQuery(
      userId: $user->getId(),
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
    ));

    $outputs = [];
    foreach ($result->items as $organization) {
      $output = new OrganizationOutput();
      $output->id = $organization->id;
      $output->name = $organization->name;
      $output->slug = $organization->slug;
      $output->ownerUserId = $organization->ownerUserId;
      $output->createdByUserId = $organization->createdByUserId;
      $output->status = $organization->status;
      $output->isActive = $organization->isActive;
      $output->memberCount = $organization->memberCount;
      $output->createdAt = $organization->createdAt->format('c');
      $output->updatedAt = $organization->updatedAt->format('c');
      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['name', 'slug', 'status']);

    $total = count($outputs);

    $sorting = SortingExtractor::fromContext($context, ['name', 'slug', 'status', 'memberCount', 'createdAt'], 'name');
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
