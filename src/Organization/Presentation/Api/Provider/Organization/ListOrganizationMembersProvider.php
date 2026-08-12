<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\{GetOrganizationMemberResult, ListOrganizationMembersQuery};
use Organization\Application\UseCase\Query\Organization\ListOrganizationRoles\{ListOrganizationRolesQuery, ListOrganizationRolesResult};
use Organization\Domain\Exception\OrganizationNotFoundException;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationMemberOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationBusFailures;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Presentation\Api\Pagination\PaginationExtractor;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException, UnprocessableEntityHttpException};
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_filter;
use function array_map;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function trim;

/**
 * Provider ListOrganizationMembersProvider.
 *
 * @category Provider
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationMemberOutput>
 */
final readonly class ListOrganizationMembersProvider implements ProviderInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationBusFailures.
   *
   * The query bus wraps every handler-thrown exception into
   * `MessengerRuntimeException` (see `MessengerQueryBusAdapter::ask()`), so a
   * direct `catch (OrganizationNotFoundException)` around `queryBus->ask()`
   * alone would never match at runtime — this trait walks the wrapped
   * exception's `getPrevious()`/`HandlerFailedException` chain to find the
   * real domain exception underneath.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;

  /**
   * The `status` filter's allowed wire values — kept in lock-step with
   * {@see \Organization\Application\UseCase\Query\Organization\ListOrganizationMembers\ListOrganizationMembersHandler::resolveStatusFilter()}.
   */
  private const array ALLOWED_STATUS_FILTERS = ['active', 'inactive', 'all'];

  /**
   * The sort fields the query (and, beneath it, the repository) actually
   * understands — see {@see ListOrganizationMembersQuery}'s docblock.
   */
  private const array ALLOWED_SORT_FIELDS = ['joinedAt', 'displayName'];
  // #endregion

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
   * Provides resource data for the requested API operation. Filtering
   * (search, status, role), sorting and pagination are all forwarded to
   * `ListOrganizationMembersQuery` — the handler pushes them down to the
   * repository at SQL level, so this provider must not re-search, re-sort or
   * re-slice the page it gets back.
   *
   * @since 2.0.0
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
    $status = $this->extractStatusFilter($context);
    $roleId = $this->extractRoleIdFilter($context);
    $sorting = SortingExtractor::fromContext($context, self::ALLOWED_SORT_FIELDS, 'joinedAt');

    try {
      /** @var PaginatedResult<GetOrganizationMemberResult> $result */
      $result = $this->queryBus->ask(new ListOrganizationMembersQuery(
        organizationId: $organizationId,
        pagination: new Pagination(offset: $pagination->offset, limit: $pagination->itemsPerPage),
        search: SearchExtractor::fromContext($context),
        status: $status,
        roleId: $roleId,
        sorting: $sorting,
      ));
    } catch (MessengerRuntimeException $exception) {
      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if (null !== $notFound) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      $invalid = $this->findWrappedException($exception, InvalidArgumentException::class)
        ?? $this->findWrappedException($exception, InvalidValueException::class);
      if (null !== $invalid) {
        throw new UnprocessableEntityHttpException($invalid->getMessage(), $exception);
      }

      throw $exception;
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

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $pagination->page,
      itemsPerPage: (float) $pagination->itemsPerPage,
      totalItems: (float) $result->total,
    );
  }

  /**
   * Method extractStatusFilter.
   *
   * Reads the `status` filter from the request and validates it against the
   * allowed wire values, mapping garbage to a 422 instead of letting it
   * reach the handler's own `InvalidArgumentException` (belt and suspenders:
   * both layers reject it, but the provider is where the HTTP status is
   * decided).
   *
   * @since 2.0.0
   *
   * @param array<string, mixed> $context the provider context
   *
   * @return ?string the validated status filter, or null for "no filter"
   */
  private function extractStatusFilter(array $context): ?string
  {
    $filters = $context['filters'] ?? [];
    if (!is_array($filters)) {
      return null;
    }

    $status = $filters['status'] ?? null;
    if (null === $status) {
      return null;
    }

    if (!is_string($status) || !in_array($status, self::ALLOWED_STATUS_FILTERS, true)) {
      throw new UnprocessableEntityHttpException('Invalid status filter. Expected one of: active, inactive, all.');
    }

    return $status;
  }

  /**
   * Method extractRoleIdFilter.
   *
   * Reads the `roleId` filter from the request.
   *
   * @since 2.0.0
   *
   * @param array<string, mixed> $context the provider context
   *
   * @return ?string the role identifier filter, or null when absent
   */
  private function extractRoleIdFilter(array $context): ?string
  {
    $filters = $context['filters'] ?? [];
    if (!is_array($filters)) {
      return null;
    }

    $roleId = $filters['roleId'] ?? null;

    return is_string($roleId) && '' !== $roleId ? $roleId : null;
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
