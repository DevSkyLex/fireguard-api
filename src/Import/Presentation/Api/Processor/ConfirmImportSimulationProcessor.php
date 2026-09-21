<?php

declare(strict_types=1);

namespace Import\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Import\Application\UseCase\Command\ConfirmImportSimulation\ConfirmImportSimulationCommand;
use Import\Application\UseCase\Query\GetImportJob\GetImportJobResult;
use Import\Presentation\Api\Dto\Output\ImportJobOutput;
use Import\Presentation\Api\Factory\ImportJobOutputFactory;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException};

use function is_string;

/** @implements ProcessorInterface<mixed, ImportJobOutput> */
final readonly class ConfirmImportSimulationProcessor implements ProcessorInterface
{
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private ImportJobOutputFactory $output)
  {
  }

  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ImportJobOutput
  {
    $userId = $this->actor->userId();
    if (null === $userId) {
      throw new AccessDeniedHttpException('Authentication required.');
    }
    $id = $uriVariables['id'] ?? null;
    if (!is_string($id)) {
      throw new BadRequestHttpException('An import identifier is required.');
    }

    /** @var GetImportJobResult $result */
    $result = $this->commands->dispatch(new ConfirmImportSimulationCommand($userId, $id));

    return $this->output->fromView($result);
  }
}
