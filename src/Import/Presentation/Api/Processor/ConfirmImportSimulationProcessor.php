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
  // #region Constructor
  /**
   * Method __construct
   *
   * Initializes the ConfirmImportSimulationProcessor dependencies and state.
   *
   * @access public
   *
   * @param CommandBusPort $commands the commands
   * @param CurrentActorPort $actor the actor
   * @param ImportJobOutputFactory $output the output being built
   *
   * @return void
   */
  public function __construct(private CommandBusPort $commands, private CurrentActorPort $actor, private ImportJobOutputFactory $output)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method process
   *
   * Processes the API operation by translating the request into the corresponding application action.
   *
   * @access public
   *
   * @param mixed $data the data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the optional uri variables
   * @param array<string, mixed> $context the optional context
   *
   * @return ImportJobOutput
   */
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
  // #endregion
}
