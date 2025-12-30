<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\Client\DeleteClient\DeleteClientCommand;
use Shared\Application\Port\Inbound\CommandBusPort;

use function is_string;

/**
 * Processor DeleteClientProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, void>
 */
final readonly class DeleteClientProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * DeleteClientProcessor class.
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
   * Processes the client deletion.
   *
   * @since 1.0.0
   *
   * @param mixed $data the input data (not used)
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
      throw new InvalidArgumentException(
        message: 'Client ID must be a string',
        previous: null,
      );
    }

    // Dispatch command
    $command = new DeleteClientCommand(clientId: $id);
    $this->commandBus->dispatch(command: $command);
  }
  // #endregion
}
