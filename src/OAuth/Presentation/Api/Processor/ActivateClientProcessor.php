<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use OAuth\Application\UseCase\Command\ActivateClient\ActivateClientCommand;
use OAuth\Application\UseCase\Query\GetClient\GetClientQuery;
use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use OAuth\Presentation\Api\Dto\Output\ClientOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Processor ActivateClientProcessor
 * @final
 *
 * API Platform processor for activating a client.
 *
 * @category Processor
 * @package OAuth\Presentation\Api\Processor
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProcessorInterface<mixed, ClientOutput>
 */
final readonly class ActivateClientProcessor implements ProcessorInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ActivateClientProcessor class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus The command bus.
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly QueryBusPort $queryBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the client activation.
   *
   * @access public
   * @since 1.0.0
   *
   * @param mixed $data The input data (not used).
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return ClientOutput The processed output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw new \InvalidArgumentException('Client ID must be a string');
    }

    // Dispatch command
    $command = new ActivateClientCommand(clientId: $id);
    $this->commandBus->dispatch($command);

    // Fetch updated client
    $query = new GetClientQuery(clientId: $id);
    /** @var GetClientResult $result */
    $result = $this->queryBus->ask(query: $query);

    // Create output DTO
    $output = new ClientOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    $output->redirectUris = $result->redirectUris;
    $output->grantTypes = $result->grantTypes;
    $output->scopes = $result->scopes;
    $output->isActive = $result->isActive;
    $output->createdAt = $result->createdAt;

    return $output;
  }
  //#endregion
}
