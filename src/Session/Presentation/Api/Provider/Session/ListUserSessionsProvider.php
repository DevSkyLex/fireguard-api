<?php

declare(strict_types=1);

namespace Session\Presentation\Api\Provider\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Session\Application\UseCase\Query\Session\GetSession\GetSessionResult;
use Session\Application\UseCase\Query\Session\ListUserSessions\ListUserSessionsQuery;
use Session\Presentation\Api\Dto\Output\Session\SessionOutput;
use Session\Presentation\Api\Support\ResolvesCurrentSessionId;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Search\{CollectionSearcher, SearchExtractor};
use Shared\Presentation\Api\Sorting\{CollectionSorter, SortingExtractor};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function array_slice;
use function count;
use function is_numeric;
use function max;

/**
 * Provider ListUserSessionsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<SessionOutput>
 */
final readonly class ListUserSessionsProvider implements ProviderInterface
{
  use ResolvesCurrentSessionId;

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
   * @return TraversablePaginator<SessionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (null === $user) {
      throw new AccessDeniedHttpException('Authentication required');
    }

    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authenticated user type is not supported');
    }

    $userId = $user->getId();

    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    /** @var PaginatedResult<GetSessionResult> $result */
    $result = $this->queryBus->ask(query: new ListUserSessionsQuery(
      userId: $userId,
      activeOnly: true,
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage),
    ));

    $currentSessionId = $this->resolveCurrentSessionId(context: $context);

    $outputs = array_map(
      callback: function ($session) use ($currentSessionId): SessionOutput {
        $output = new SessionOutput();
        $output->id = $session->sessionId;
        $output->userId = $session->userId;
        $output->ipAddress = $session->ipAddress;
        $output->userAgent = $session->userAgent;
        $output->deviceType = $session->deviceType;
        $output->browser = $session->browser;
        $output->createdAt = $session->createdAt->format('c');
        $output->lastActivityAt = $session->lastActivityAt->format('c');
        $output->isActive = !$session->isRevoked;
        $output->isCurrent = $session->sessionId === $currentSessionId;

        return $output;
      },
      array: $result->items,
    );

    $search = SearchExtractor::fromContext($context);
    $outputs = CollectionSearcher::search($outputs, $search, ['ipAddress', 'browser', 'deviceType']);

    $total = count($outputs);

    $sorting = SortingExtractor::fromContext($context, ['createdAt', 'lastActivityAt', 'isActive'], 'lastActivityAt', SortDirection::DESC);
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
