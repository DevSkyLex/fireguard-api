<?php

declare(strict_types=1);

namespace Mission\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Mission\Application\UseCase\Query\Workflow\GetMissionWorkflow\{GetMissionWorkflowQuery, GetMissionWorkflowResult};
use Mission\Application\UseCase\Query\Workflow\ListMissionWorkflow\{ListMissionWorkflowQuery, ListMissionWorkflowResult};
use Mission\Presentation\Api\Dto\Output\MissionWorkItemOutput;
use Mission\Presentation\Api\Factory\MissionWorkItemOutputFactory;
use Mission\Presentation\Api\Trait\MissionWorkflowExceptionMapperTrait;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function is_string;
use function max;
use function min;

/**
 * Provider MissionWorkItemProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<MissionWorkItemOutput>
 */
final readonly class MissionWorkItemProvider implements ProviderInterface
{
  use MissionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the MissionWorkItemProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param MissionWorkItemOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private MissionWorkItemOutputFactory $mapper,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * Method provide.
   *
   * Executes the provide operation.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return MissionWorkItemOutput|TraversablePaginator the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): MissionWorkItemOutput|TraversablePaginator
  {
    $user = $this->user();
    $id = $uriVariables['id'] ?? null;
    if (is_string($id)) {
      try {
        /** @var GetMissionWorkflowResult $result */
        $result = $this->queryBus->ask(new GetMissionWorkflowQuery($user->getId(), 'work_item', $id));
      } catch (Throwable $exception) {
        throw $this->mapWorkflowException($exception);
      }

      return $this->mapper->fromView($result->view);
    }
    $query = $this->requestStack->getCurrentRequest()?->query;
    $mission = $query?->get('mission');
    if (!is_string($mission) || '' === $mission) {
      throw new BadRequestHttpException('The mission filter is required.');
    }
    $filters = [];
    foreach (['source', 'action', 'status'] as $filter) {
      $value = $query?->get($filter);
      if (is_string($value) && '' !== $value) {
        $filters[$filter] = $value;
      }
    }
    $assignee = $query?->get('assignee');
    if (is_string($assignee) && '' !== $assignee) {
      $filters['assigneeId'] = ResourceIriParser::memberId($assignee);
    }

    try {
      /** @var ListMissionWorkflowResult $result */
      $result = $this->queryBus->ask(new ListMissionWorkflowQuery(
        $user->getId(),
        'work_item',
        ResourceIriParser::id($mission, 'missions'),
        $filters,
        max(1, $query?->getInt('page', 1) ?? 1),
        max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30)),
      ));
    } catch (Throwable $exception) {
      throw $this->mapWorkflowException($exception);
    }

    return new TraversablePaginator(
      new ArrayIterator(array_map($this->mapper->fromView(...), $result->page->items)),
      (float) $result->page->page,
      (float) $result->page->itemsPerPage,
      (float) $result->page->total,
    );
  }

  /**
   * Method user.
   *
   * Executes the user operation.
   *
   * @since 1.0.0
   *
   * @return SecurityUser the user result
   */
  private function user(): SecurityUser
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    return $user;
  }
}
