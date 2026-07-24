<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Assistant\Application\UseCase\Query\Thread\ListAssistantThreads\{ListAssistantThreadsQuery, ListAssistantThreadsResult};
use Assistant\Presentation\Api\Dto\Output\AssistantThreadOutput;
use Assistant\Presentation\Api\Factory\AssistantThreadOutputFactory;
use Assistant\Presentation\Api\Trait\AssistantExceptionMapperTrait;
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
 * Provider ListAssistantThreadsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<AssistantThreadOutput>
 */
final readonly class ListAssistantThreadsProvider implements ProviderInterface
{
  use AssistantExceptionMapperTrait;

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   * @param Security $security the security service
   * @param RequestStack $requestStack the request stack
   * @param AssistantThreadOutputFactory $outputFactory the output factory
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private Security $security,
    private RequestStack $requestStack,
    private AssistantThreadOutputFactory $outputFactory,
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

    try {
      /** @var ListAssistantThreadsResult $result */
      $result = $this->queryBus->ask(new ListAssistantThreadsQuery(
        organizationId: $organizationId,
        actorUserId: $user->getId(),
        page: $page,
        itemsPerPage: $itemsPerPage,
      ));
    } catch (Throwable $exception) {
      throw $this->mapAssistantException($exception);
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
