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
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Pagination\PaginationExtractor;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function count;
use function is_string;
use function trim;

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
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListOrganizationMembersProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param Security $security the security service
   */
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

    $pagination = PaginationExtractor::fromContext($context);

    try {
      /** @var PaginatedResult<GetOrganizationMemberResult> $result */
      $result = $this->queryBus->ask(new ListOrganizationMembersQuery(
        organizationId: $organizationId,
        pagination: new Pagination(offset: $pagination->offset, limit: $pagination->itemsPerPage),
      ));
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    }

    /** @var ListOrganizationRolesResult $rolesResult */
    $rolesResult = $this->queryBus->ask(new ListOrganizationRolesQuery($organizationId));
    $roleNamesById = [];
    foreach ($rolesResult->roles as $role) {
      $roleNamesById[$role->id] = $role->name;
    }

    $outputs = [];
    foreach ($result->items as $member) {
      $output = new OrganizationMemberOutput();
      $output->id = $member->id;
      $output->organizationId = $member->organizationId;
      $output->userId = $member->userId;
      $output->displayName = $member->userId;
      $output->isActive = $member->isActive;
      $output->isOwner = $member->isOwner;
      $output->joinedAt = $member->joinedAt->format('c');
      $output->roleIds = $member->roleIds;
      $output->roleNames = array_values(array_filter(array_map(
        static fn (string $roleId): ?string => $roleNamesById[$roleId] ?? null,
        $member->roleIds,
      )));

      $userResult = $this->findUser($member->userId);
      if ($userResult instanceof GetUserResult && null !== $userResult->user) {
        $output->email = $userResult->user->email;
        $output->firstName = $userResult->user->firstName;
        $output->lastName = $userResult->user->lastName;
        $output->displayName = trim($userResult->user->firstName . ' ' . $userResult->user->lastName)
          ?: $userResult->user->username
          ?: $member->userId;
        $output->avatarUrl = $userResult->user->avatarUrl;
      }

      $outputs[] = $output;
    }

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['userId', 'displayName', 'firstName', 'lastName', 'email']);

    $total = count($outputs);

    $sorting = SortingExtractor::fromContext($context, ['userId', 'isActive', 'joinedAt'], 'joinedAt');
    $outputs = CollectionSorter::sort($outputs, $sorting);

    $outputs = array_slice($outputs, $pagination->offset, $pagination->itemsPerPage);

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $pagination->page,
      itemsPerPage: (float) $pagination->itemsPerPage,
      totalItems: (float) $total,
    );
  }

  /**
   * Resolves a user profile without making member listing fail when the user
   * record is unavailable.
   */
  private function findUser(string $userId): ?GetUserResult
  {
    try {
      /** @var GetUserResult $result */
      $result = $this->queryBus->ask(new GetUserQuery($userId));
    } catch (Throwable) {
      return null;
    }

    return $result;
  }
  // #endregion
}
