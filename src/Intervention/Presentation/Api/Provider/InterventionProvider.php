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
use Intervention\Domain\ValueObject\InterventionPriority;
use Intervention\Presentation\Api\Dto\Output\InterventionOutput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Trait\InterventionWorkflowExceptionMapperTrait;
use Shared\Application\Contract\Sorting\SortDirection;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Presentation\Api\Http\ResourceIriParser;
use Shared\Presentation\Api\Sorting\SortingExtractor;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Throwable;

use function array_map;
use function ctype_digit;
use function is_string;
use function max;
use function min;
use function stripos;
use function substr;

/**
 * Provider InterventionProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<InterventionOutput>
 */
final readonly class InterventionProvider implements ProviderInterface
{
  use InterventionWorkflowExceptionMapperTrait;

  /**
   * Constructor.
   *
   * Initializes a new instance of the InterventionProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus value
   * @param InterventionOutputFactory $mapper the mapper value
   * @param Security $security the security value
   * @param RequestStack $requestStack the request stack value
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private InterventionOutputFactory $mapper,
    private Security $security,
    private RequestStack $requestStack,
  ) {
  }

  /**
   * Method provide.
   *
   * @since 1.0.0
   *
   * @param Operation $operation the operation value
   * @param array<string, mixed> $uriVariables the uri variables value
   * @param array<string, mixed> $context the context value
   *
   * @return InterventionOutput|TraversablePaginator<InterventionOutput>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $user = $this->user();
    $id = $uriVariables['id'] ?? null;
    if (is_string($id) && '' !== $id) {
      try {
        /** @var GetInterventionWorkflowResult $result */
        $result = $this->queryBus->ask(new GetInterventionWorkflowQuery($user->getId(), 'intervention', $id));
      } catch (Throwable $exception) {
        throw $this->mapWorkflowException($exception);
      }

      return $this->mapper->fromView($result->view);
    }

    $query = $this->requestStack->getCurrentRequest()?->query;
    $organization = $query?->get('organization');
    if (!is_string($organization) || '' === $organization) {
      throw new BadRequestHttpException('The organization filter is required.');
    }
    $filters = [];
    foreach (['name', 'type', 'status', 'priority', 'dueAtAfter', 'dueAtBefore', 'plannedStartAtAfter', 'plannedStartAtBefore'] as $filter) {
      $value = $query?->get($filter);
      if (is_string($value) && '' !== $value) {
        $filters[$filter] = $value;
      }
    }
    // Reject an unknown priority up front: the gateway's equality filter would
    // otherwise return a silently empty collection instead of a client error.
    if (isset($filters['priority']) && !InterventionPriority::tryFrom((string) $filters['priority']) instanceof InterventionPriority) {
      throw new BadRequestHttpException('The priority filter must be one of: low, normal, high, urgent.');
    }
    foreach (['responsible' => 'responsibleId', 'participant' => 'participantId', 'member' => 'memberId'] as $filter => $target) {
      $value = $query?->get($filter);
      if (is_string($value) && '' !== $value) {
        $filters[$target] = ResourceIriParser::memberId($value);
      }
    }
    $site = $query?->get('site');
    if (is_string($site) && '' !== $site) {
      $filters['siteId'] = ResourceIriParser::id($site, 'facilities');
    }
    $label = $query?->get('label');
    if (is_string($label) && '' !== $label) {
      $filters['labelId'] = ResourceIriParser::id($label, 'intervention-labels');
    }
    // Accept the client's `FG-` prefix and strip it before validating the
    // remainder is numeric, mirroring the priority guard above.
    $number = $query?->get('number');
    if (is_string($number) && '' !== $number) {
      $number = 0 === stripos($number, 'FG-') ? substr($number, 3) : $number;
      if (!ctype_digit($number)) {
        throw new BadRequestHttpException('The number filter must be a positive integer, optionally prefixed with FG-.');
      }
      $filters['number'] = (int) $number;
    }
    $page = max(1, $query?->getInt('page', 1) ?? 1);
    $itemsPerPage = max(1, min(100, $query?->getInt('itemsPerPage', 30) ?? 30));

    try {
      /** @var ListInterventionWorkflowResult $result */
      $result = $this->queryBus->ask(new ListInterventionWorkflowQuery(
        $user->getId(),
        'intervention',
        ResourceIriParser::id($organization, 'organizations'),
        $filters,
        $page,
        $itemsPerPage,
        SortingExtractor::fromContext(
          $context,
          ['name', 'status', 'type', 'priority', 'plannedStartAt', 'dueAt', 'createdAt', 'updatedAt'],
          'updatedAt',
          SortDirection::DESC,
        ),
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
