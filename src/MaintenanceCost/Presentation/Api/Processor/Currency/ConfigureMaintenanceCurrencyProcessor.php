<?php

declare(strict_types=1);

namespace MaintenanceCost\Presentation\Api\Processor\Currency;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use MaintenanceCost\Application\UseCase\Command\Currency\ConfigureMaintenanceCurrency\{ConfigureMaintenanceCurrencyCommand, ConfigureMaintenanceCurrencyResult};
use MaintenanceCost\Domain\Exception\MaintenanceCostException;
use MaintenanceCost\Presentation\Api\Dto\Input\Currency\ConfigureMaintenanceCurrencyInput;
use MaintenanceCost\Presentation\Api\Dto\Output\Currency\MaintenanceCurrencyOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function is_string;

/** @implements ProcessorInterface<mixed,MaintenanceCurrencyOutput> */
final readonly class ConfigureMaintenanceCurrencyProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): MaintenanceCurrencyOutput
  {
    $actor = $this->actor->userId() ?? throw new AccessDeniedHttpException('Authentication required.');
    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!$data instanceof ConfigureMaintenanceCurrencyInput || !is_string($organizationId)) {
      throw MaintenanceCostException::invalid('Invalid currency configuration request.');
    }
    /** @var ConfigureMaintenanceCurrencyResult $result */
    $result = $this->commands->dispatch(new ConfigureMaintenanceCurrencyCommand($organizationId, $actor, $data->currency));

    return MaintenanceCurrencyOutput::fromSnapshot($result->currency);
  }
}
