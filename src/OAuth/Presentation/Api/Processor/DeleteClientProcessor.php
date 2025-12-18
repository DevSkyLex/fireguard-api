<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use OAuth\Application\UseCase\Command\DeleteClient\DeleteClientCommand;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Processor DeleteClientProcessor
 * @final
 *
 * API Platform processor for deleting a client.
 *
 * @category Processor
 * @package OAuth\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteClientProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the DeleteClientProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the client deletion.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data (not used).
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return void
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw new \InvalidArgumentException('Client ID must be a string');
    }

    // Dispatch command
    $command = new DeleteClientCommand(clientId: $id);
    $this->commandBus->dispatch($command);
  }
  //#endregion
}
