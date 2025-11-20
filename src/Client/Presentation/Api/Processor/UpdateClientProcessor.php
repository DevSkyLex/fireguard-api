<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Client\Application\UseCase\Command\UpdateClientDetails\UpdateClientDetailsCommand;
use Client\Presentation\Api\Resource\ClientResource;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Domain\ValueObject\RedirectUri;
use Shared\Domain\ValueObject\Scopes;

/**
 * Processor UpdateClientProcessor
 * @final
 *
 * API Platform processor for client update.
 *
 * @category Processor
 * @package Client\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProcessorInterface<ClientResource, ClientResource>
 */
final readonly class UpdateClientProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the UpdateClientProcessor class.
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
   * Processes the client update.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientResource $data The input data (ClientResource).
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return ClientResource The processed resource.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientResource
  {
    // Convert DTO to Command
    $command = new UpdateClientDetailsCommand(
      clientId: $data->id,
      name: $data->name,
      redirectUris: array_map(fn(string $uri) => new RedirectUri($uri), $data->redirectUris),
      scopes: Scopes::fromArray($data->scopes)
    );

    // Dispatch command
    $this->commandBus->dispatch($command);

    return $data;
  }
  //#endregion
}
