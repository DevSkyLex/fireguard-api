<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor\Permission;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\UseCase\Command\Permission\CreatePermission\{CreatePermissionCommand, CreatePermissionResult};
use Authorization\Presentation\Api\Dto\Input\Permission\PermissionInput;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Processor CreatePermissionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<PermissionInput, PermissionOutput>
 */
final readonly class CreatePermissionProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * CreatePermissionProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the permission creation.
   *
   * @since 1.0.0
   *
   * @param PermissionInput $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return PermissionOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PermissionOutput
  {
    /** @var PermissionInput $data */
    $command = new CreatePermissionCommand(
      name: $data->name,
      description: $data->description,
    );

    /** @var CreatePermissionResult $result */
    $result = $this->commandBus->dispatch(command: $command);

    $output = new PermissionOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->description = $result->description;
    $output->createdAt = $result->createdAt;

    return $output;
  }
  // #endregion
}
