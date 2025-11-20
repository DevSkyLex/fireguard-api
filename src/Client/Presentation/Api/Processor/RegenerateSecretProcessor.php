<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Client\Application\UseCase\Command\RegenerateClientSecret\RegenerateClientSecretCommand;
use Client\Application\UseCase\Command\RegenerateClientSecret\RegenerateClientSecretResult;
use Client\Presentation\Api\Resource\ClientResource;
use Shared\Application\Port\Inbound\CommandBusPort;

/**
 * Processor RegenerateSecretProcessor
 * @final
 *
 * API Platform processor for client secret regeneration.
 *
 * @category Processor
 * @package Client\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProcessorInterface<ClientResource, ClientResource>
 */
final readonly class RegenerateSecretProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the RegenerateSecretProcessor class.
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
   * Processes the client secret regeneration.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientResource $data The input data (ClientResource).
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return ClientResource The processed resource with new secret.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientResource
  {
    // Get client ID from URI variables
    $clientId = $uriVariables['id'] ?? $data->id;

    // Convert DTO to Command
    $command = new RegenerateClientSecretCommand(
      clientId: $clientId
    );

    // Dispatch command
    /** @var RegenerateClientSecretResult $result */
    $result = $this->commandBus->dispatch($command);

    // Update DTO with new secret (shown only once)
    $data->id = $result->clientId;
    $data->secret = $result->clientSecret;

    return $data;
  }
  //#endregion
}
