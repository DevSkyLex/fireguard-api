<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Processor\Rate;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate\{CreateMaintenanceRateCommand, CreateMaintenanceRateResult};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Presentation\Api\Dto\Input\Rate\CreateMaintenanceRateInput;
use MaintenanceCost\Presentation\Api\Dto\Output\Rate\MaintenanceRateOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/** @implements ProcessorInterface<mixed,MaintenanceRateOutput> */
final readonly class CreateMaintenanceRateProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceRateOutput
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!$data instanceof CreateMaintenanceRateInput || !is_string($organizationId)) {
      throw MaintenanceCostException::invalid('Invalid hourly rate request.');
    }
    /** @var CreateMaintenanceRateResult $result */
    $result = $this->commands->dispatch(new CreateMaintenanceRateCommand($organizationId, $actor, $data->memberId, $data->hourlyAmount, $data->effectiveFrom, $data->clientId));

    return MaintenanceRateOutput::fromSnapshot($result->rate, $result->replayed);
  }
}
