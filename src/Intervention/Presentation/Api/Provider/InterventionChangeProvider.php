<?php

declare(strict_types=1);

namespace Intervention\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Query\Workflow\GetInterventionWorkflow\{GetInterventionWorkflowQuery, GetInterventionWorkflowResult};
use Intervention\Application\UseCase\Query\Workflow\ListInterventionWorkflow\{ListInterventionWorkflowQuery, ListInterventionWorkflowResult};
use Intervention\Presentation\Api\Dto\Output\InterventionChangeOutput;
use Intervention\Presentation\Api\Factory\InterventionChangeOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
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
 * Provider InterventionChangeProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InterventionChangeOutput>
 */
final readonly class InterventionChangeProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionChangeProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param InterventionChangeOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private InterventionChangeOutputFactory $mapper,
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
   * @return InterventionChangeOutput|TraversablePaginator the provide result
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): InterventionChangeOutput|TraversablePaginator
  {
    $user = $this->user();
    $id = $uriVariables['id'] ?? null;
    if (is_string($id)) {
      try {
        /** @var GetInterventionWorkflowResult $result */
        $result = $this->queryBus->ask(new GetInterventionWorkflowQuery($user->getId(), 'change', $id));
      } catch (Throwable $exception) {
        throw $this->mapWorkflowException($exception);
      }

      return $this->mapper->fromView($result->view);
    }
    $query = $this->requestStack->getCurrentRequest()?->query;
    $intervention = $query?->get('intervention');
    if (!is_string($intervention) || '' === $intervention) {
      throw new BadRequestHttpException('The intervention filter is required.');
    }
    $filters = [];
    foreach (['resource', 'status'] as $filter) {
      $value = $query?->get($filter);
      if (is_string($value) && '' !== $value) {
        $filters[$filter] = $value;
      }
    }

    try {
      /** @var ListInterventionWorkflowResult $result */
      $result = $this->queryBus->ask(new ListInterventionWorkflowQuery(
        $user->getId(),
        'change',
        ResourceIriParser::id($intervention, 'interventions'),
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
