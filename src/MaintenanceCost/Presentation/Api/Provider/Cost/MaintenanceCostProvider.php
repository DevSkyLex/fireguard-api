<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Provider\Cost;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use MaintenanceCost\Application\UseCase\Query\Cost\GetMaintenanceCost\{GetMaintenanceCostQuery, GetMaintenanceCostResult};
use MaintenanceCost\Presentation\Api\Dto\Output\Cost\MaintenanceCostOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/**
 * Class MaintenanceCostProvider. Uses only the dedicated authorized financial query.
 *
 * @category Provider
 *
 * @implements ProviderInterface<MaintenanceCostOutput>
 */
final readonly class MaintenanceCostProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private CurrentActorPort $actor)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables @param array<string,mixed> $context
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): MaintenanceCostOutput
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = is_string($uriVariables['organizationId'] ?? null) ? $uriVariables['organizationId'] : '';
    $interventionId = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : '';
    /** @var GetMaintenanceCostResult $result */
    $result = $this->queries->ask(new GetMaintenanceCostQuery($actorId, $organizationId, $interventionId));

    return MaintenanceCostOutput::fromView($result->view);
  }
}
