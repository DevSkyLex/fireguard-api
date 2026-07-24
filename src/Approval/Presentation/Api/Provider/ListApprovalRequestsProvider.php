<?php

declare(strict_types=1);

namespace Approval\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use Approval\Application\UseCase\Query\Request\ListApprovalRequests\{ListApprovalRequestsQuery, ListApprovalRequestsResult};
use Approval\Presentation\Api\Dto\Output\ApprovalRequestOutput;
use Approval\Presentation\Api\Factory\ApprovalRequestOutputFactory;
use Approval\Presentation\Api\Trait\ApprovalExceptionMapperTrait;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider ListApprovalRequestsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ApprovalRequestOutput>
 */
final readonly class ListApprovalRequestsProvider implements ProviderInterface
{
  use ApprovalExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack
   * @param ApprovalRequestOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
    private ApprovalRequestOutputFactory $outputFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return object the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      throw new BadRequestHttpException('OrganizationId URI parameter is required.');
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));
    $status = $query?->get('status');
    $actionType = $query?->get('actionType');

    try {
      /** @var ListApprovalRequestsResult $result */
      $result = $this->queryBus->ask(new ListApprovalRequestsQuery(
        organizationId: $organizationId,
        userId: $user->getId(),
        status: is_string($status) && '' !== $status ? $status : null,
        actionType: is_string($actionType) && '' !== $actionType ? $actionType : null,
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapApprovalException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->outputFactory->fromView(...), $result->items)),
      (float) $result->page,
      (float) $result->itemsPerPage,
      (float) $result->total,
    );
  }
  // #endregion
}
