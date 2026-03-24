<?php

declare(strict_types=1);

namespace User\Presentation\Api\Provider\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeInterface;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\SearchExtractor;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, UnauthorizedHttpException};
use User\Application\UseCase\Query\User\ListUsers\ListUsersQuery;
use User\Domain\Model\User\User;
use User\Presentation\Api\Dto\Output\User\UserOutput;

use function array_map;
use function is_numeric;
use function max;
use function min;

/**
 * Provider ListUsersProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<UserOutput>
 */
final readonly class ListUsersProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListUsersProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
    private readonly Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Provides the list of users.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return array<UserOutput> the list of users
   */
  /**
   * @return TraversablePaginator<UserOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $caller = $this->security->getUser();
    if (!$caller instanceof SecurityUser) {
      throw new UnauthorizedHttpException('Bearer', 'Authentication required.');
    }

    $tenantId = $caller->getTenantId();
    if (null === $tenantId && !$this->security->isGranted('ROLE_SUPER_ADMIN')) {
      throw new AccessDeniedHttpException('Cross-tenant access requires elevated privileges.');
    }

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));

    $offset = ($page - 1) * $itemsPerPage;

    $search = SearchExtractor::fromContext($context);
    $sorting = SortingExtractor::fromContext($context, ['username', 'email', 'firstName', 'lastName', 'status', 'createdAt'], 'createdAt');

    /** @var PaginatedResult<User> $result */
    $result = $this->queryBus->ask(query: new ListUsersQuery(
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
      search: $search,
      sorting: $sorting,
      tenantId: $tenantId,
    ));

    $outputs = array_map(function (User $user) {
      $output = new UserOutput();
      $output->id = $user->id()->value;
      $output->username = $user->username()->value;
      $output->email = $user->email()->value;
      $output->firstName = $user->profile()->firstName;
      $output->lastName = $user->profile()->lastName;
      $output->avatarUrl = $user->profile()->avatarUrl;
      $output->status = $user->status()->value;
      $output->emailVerified = $user->isEmailVerified();
      $output->tenantId = $user->tenantId()?->__toString();
      $output->createdAt = $user->createdAt()->format(DateTimeInterface::ATOM);
      $output->lastLoginAt = $user->lastLoginAt()?->format(DateTimeInterface::ATOM);

      return $output;
    }, $result->items);

    return new TraversablePaginator(
      traversable: new ArrayIterator($outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total,
    );
  }
}
