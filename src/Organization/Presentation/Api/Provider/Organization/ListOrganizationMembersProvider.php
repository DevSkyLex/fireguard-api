<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{GetOrganizationMemberResult, ListOrganizationMembersQuery};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function array_slice;
use function count;
use function is_numeric;
use function is_string;
use function max;

/**
 * Provider ListOrganizationMembersProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationMemberOutput>
 */
final readonly class ListOrganizationMembersProvider implements ProviderInterface
{
  // #region Constructor
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods

  /**
   * Method provide.
   *
   * Provides resource data for the requested API operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return list<OrganizationMemberOutput>
   */
  /**
   * @return TraversablePaginator<OrganizationMemberOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return new TraversablePaginator(new ArrayIterator([]), 1, 30, 0);
    }

    if (!$this->authorization->hasPermission($user->getId(), $organizationId, 'organization.members.read')) {
      throw new AccessDeniedHttpException('Missing Organization.members.read permission.');
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

    try {
      /** @var PaginatedResult<GetOrganizationMemberResult> $result */
      $result = $this->queryBus->ask(new ListOrganizationMembersQuery(
        organizationId: $organizationId,
        pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    $outputs = [];
    foreach ($result->items as $member) {
      $output = new OrganizationMemberOutput();
      $output->id = $member->id;
      $output->organizationId = $member->organizationId;
      $output->userId = $member->userId;
      $output->isActive = $member->isActive;
      $output->joinedAt = $member->joinedAt->format('c');
      $output->roleIds = $member->roleIds;
      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['userId']);

    $total = count($outputs);

    $sorting = SortingExtractor::fromContext($context, ['userId', 'isActive', 'joinedAt'], 'joinedAt');
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
