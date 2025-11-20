<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Client\Application\UseCase\Command\DeleteClient\DeleteClientCommand;
use Client\Presentation\Api\Resource\ClientResource;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Processor DeleteClientProcessor
 * @final
 *
 * API Platform processor for client deletion.
 *
 * @category Processor
 * @package Client\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProcessorInterface<ClientResource, void>
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
  ) {}
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
   * @param ClientResource $data The input data (ClientResource).
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return void
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
  {
    // Get client ID from URI variables
    $clientId = $uriVariables['id'] ?? $data->id;

    // Convert DTO to Command
    $command = new DeleteClientCommand(
      clientId: $clientId
    );

    // Dispatch command (soft delete)
    $this->commandBus->dispatch($command);
  }
  //#endregion
}
