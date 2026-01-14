<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor\Permission;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\UseCase\Command\Permission\DeletePermission\DeletePermissionCommand;
use Authorization\Domain\Exception\PermissionNotFoundException;
use Shared\Application\Port\Inbound\CommandBusPort;

use function is_string;

/**
 * Processor DeletePermissionProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeletePermissionProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeletePermissionProcessor class.
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
   * Processes the permission deletion.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return void no return value
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw PermissionNotFoundException::withId(permissionId: 'unknown');
    }

    $command = new DeletePermissionCommand(permissionId: $id);
    $this->commandBus->dispatch(command: $command);
  }
  // #endregion
}
