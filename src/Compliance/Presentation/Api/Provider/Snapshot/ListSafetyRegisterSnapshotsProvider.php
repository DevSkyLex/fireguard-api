<?php

declare(strict_types=1);

namespace Compliance\Presentation\Api\Provider\Snapshot;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Compliance\Application\UseCase\Query\Snapshot\ListSafetyRegisterSnapshots\{ListSafetyRegisterSnapshotsQuery, ListSafetyRegisterSnapshotsResult};
use Compliance\Presentation\Api\Dto\Output\Snapshot\SafetyRegisterSnapshotOutput;
use Compliance\Presentation\Api\Factory\SafetyRegisterSnapshotOutputFactory;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider ListSafetyRegisterSnapshotsProvider.
 *
 * Handles `GET /organizations/{organizationId}/compliance/register-snapshots`
 * (org-scoped snapshot metadata, most recently generated first). The handler
 * owns the whole gate — `resolveAccess` first, then the plan entitlement —
 * and domain exceptions map to HTTP through the central
 * `api_platform.exception_to_status` configuration.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<SafetyRegisterSnapshotOutput>
 */
final readonly class ListSafetyRegisterSnapshotsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param SafetyRegisterSnapshotOutputFactory $outputFactory the output factory
   * @param Security $security the security helper
   * @param RequestStack $requestStack the request stack
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private SafetyRegisterSnapshotOutputFactory $outputFactory,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the API operation metadata
   * @param array<string, mixed> $uriVariables URI variables extracted from the request
   * @param array<string, mixed> $context processing context values
   *
   * @return object the paginated snapshot metadata
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    /** @var ListSafetyRegisterSnapshotsResult $result */
    $result = $this->queryBus->ask(new ListSafetyRegisterSnapshotsQuery(
      organizationId: $organizationId,
      userId: $user->getId(),
      page: $page,
      itemsPerPage: $itemsPerPage,
    ));

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->outputFactory->fromView(...), $result->items)),
      (float) $result->page,
      (float) $result->itemsPerPage,
      (float) $result->total,
    );
  }
  // #endregion
}
