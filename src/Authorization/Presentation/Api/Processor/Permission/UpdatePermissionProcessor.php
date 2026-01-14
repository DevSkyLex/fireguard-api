<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor\Permission;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\UseCase\Command\Permission\UpdatePermission\{UpdatePermissionCommand, UpdatePermissionResult};
use Authorization\Domain\Exception\PermissionNotFoundException;
use Authorization\Presentation\Api\Dto\Input\Permission\PermissionInput;
use Authorization\Presentation\Api\Dto\Output\Permission\PermissionOutput;
use Shared\Application\Port\Inbound\CommandBusPort;

use function is_string;

/**
 * Processor UpdatePermissionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<PermissionInput, PermissionOutput>
 */
final readonly class UpdatePermissionProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdatePermissionProcessor class.
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
   * Processes the permission update.
   * Note: Permission is immutable, so we cannot update it.
   * This endpoint returns the current state.
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
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw PermissionNotFoundException::withId(permissionId: 'unknown');
    }

    $command = new UpdatePermissionCommand(permissionId: $id);

    /** @var UpdatePermissionResult $result */
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
