<?php

declare(strict_types=1);

namespace Workload\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};
use Workload\Application\UseCase\Query\Projection\GetWorkload\{GetWorkloadQuery, GetWorkloadResult};
use Workload\Presentation\Api\Dto\Output\WorkloadOutput;

use function filter_var;
use function is_array;
use function is_string;

use const FILTER_VALIDATE_INT;
use const PHP_INT_MAX;

/**
 * WorkloadProvider.
 *
 * @category Workload
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<WorkloadOutput>
 */
final readonly class WorkloadProvider implements ProviderInterface
{
  /**
   * @since 1.0.0
   *
   * @param QueryBusPort $queries dispatches read use cases through the query bus
   * @param Security $security resolves the authenticated account at the HTTP boundary
   */
  public function __construct(private QueryBusPort $queries, private Security $security)
  {
  }

  /**
   * Dispatches an authorized workload read using validated organization and date filters.
   *
   * @since 1.0.0
   *
   * @param Operation $operation API operation metadata
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @return WorkloadOutput authorized daily projection and available filter options
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): WorkloadOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $organizationId = $uriVariables['organizationId'] ?? null;
    $filters = is_array($context['filters'] ?? null) ? $context['filters'] : [];
    $from = $filters['from'] ?? null;
    $to = $filters['to'] ?? null;
    if (!is_string($organizationId) || !is_string($from) || !is_string($to)) {
      throw new BadRequestHttpException('Organization, from and to are required.');
    }
    /** @var GetWorkloadResult $result */
    $result = $this->queries->ask(new GetWorkloadQuery(
      $user->getId(),
      $organizationId,
      $from,
      $to,
      is_string($filters['member'] ?? null) ? $filters['member'] : null,
      is_string($filters['team'] ?? null) ? $filters['team'] : null,
      'true' === ($filters['overloaded'] ?? null) || '1' === ($filters['overloaded'] ?? null),
      $this->positiveInteger($filters['page'] ?? 1, 'page'),
      $this->positiveInteger($filters['pageSize'] ?? 10, 'pageSize', 100),
    ));

    return new WorkloadOutput($organizationId, $result->projection, $result->canManageCapacity, $result->canReadTeam, $result->teams, $result->memberOptions, $result->totalItems, $result->page, $result->pageSize);
  }

  /**
   * Rejects malformed or unbounded pagination before dispatching a projection.
   *
   * @since 1.0.0
   *
   * @param mixed $value raw query parameter
   * @param string $name parameter name for the client error
   * @param int $maximum accepted upper bound
   *
   * @return int validated positive integer
   */
  private function positiveInteger(mixed $value, string $name, int $maximum = PHP_INT_MAX): int
  {
    $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $maximum]]);
    if (false === $parsed) {
      throw new BadRequestHttpException($name . ' must be a positive integer within its allowed range.');
    }

    return $parsed;
  }
}
