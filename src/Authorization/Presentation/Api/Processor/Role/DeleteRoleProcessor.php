<?php

declare(strict_types=1);

namespace Authorization\Presentation\Api\Processor\Role;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Authorization\Application\UseCase\Command\Role\DeleteRole\DeleteRoleCommand;
use Authorization\Domain\Exception\RoleNotFoundException;
use Shared\Application\Port\Inbound\CommandBusPort;

use function is_string;

/**
 * Processor DeleteRoleProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteRoleProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteRoleProcessor class.
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
   * Processes the role deletion.
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
      throw RoleNotFoundException::withId(roleId: 'unknown');
    }

    $command = new DeleteRoleCommand(roleId: $id);
    $this->commandBus->dispatch(command: $command);
  }
  // #endregion
}
