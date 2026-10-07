<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Processor\Cost;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use MaintenanceCost\Application\UseCase\Command\Cost\WriteMaintenanceCost\{WriteMaintenanceCostCommand, WriteMaintenanceCostResult};
use MaintenanceCost\Presentation\Api\Dto\Input\Cost\{CreateMaintenanceExpenseInput, WriteCostPlanningInput};
use MaintenanceCost\Presentation\Api\Dto\Output\Cost\MaintenanceCostOutput;
use MaintenanceCost\Presentation\Api\Operation\Cost\MaintenanceCostOperations;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;
use function preg_match;

/**
 * Class MaintenanceCostProcessor. Retains literal decimal strings and transport revision requirements.
 *
 * @category Processor
 *
 * @implements ProcessorInterface<CreateMaintenanceExpenseInput|WriteCostPlanningInput,MaintenanceCostOutput>
 */
final readonly class MaintenanceCostProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables @param array<string,mixed> $context
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceCostOutput
  {
    $actorId = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = is_string($uriVariables['organizationId'] ?? null) ? $uriVariables['organizationId'] : '';
    $interventionId = is_string($uriVariables['id'] ?? null) ? $uriVariables['id'] : '';
    $request = $this->requests->getCurrentRequest();
    $header = $request?->headers->get('If-Match');
    $revision = null === $header ? null : (1 === preg_match('/^"revision-(\d+)"$/D', $header, $matches) ? (int) $matches[1] : -1);
    /** @var WriteMaintenanceCostResult $result */
    $result = $this->commands->dispatch(new WriteMaintenanceCostCommand($actorId, $organizationId, $interventionId, MaintenanceCostOperations::PLANNING === $operation->getName() ? 'planning' : 'expense', $request?->getPayload()->all() ?? [], $revision));

    return MaintenanceCostOutput::fromView($result->view);
  }
}
