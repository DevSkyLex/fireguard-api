<?php

declare(strict_types=1);

namespace User\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Shared\Application\Port\Inbound\CommandBusPort;
use User\Application\UseCase\Command\DeleteUser\DeleteUserCommand;

/**
 * Processor DeleteUserProcessor
 * @final
 *
 * Processor for deleting a user.
 *
 * @category Processor
 * @package User\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteUserProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * DeleteUserProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method process
   *
   * Processes the delete user request.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return void No return value.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $id = $uriVariables['id'] ?? null;
    if (!$id) return;

    $command = new DeleteUserCommand(id: $id);
    $this->commandBus->dispatch(command: $command);
  }
  //#endregion
}
