<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Client\Application\UseCase\Command\UpdateClientDetails\UpdateClientDetailsCommand;
use Client\Application\UseCase\Query\GetClient\GetClientQuery;
use Client\Application\UseCase\Query\GetClient\GetClientResult;
use Client\Presentation\Api\Dto\ClientInput;
use Client\Presentation\Api\Dto\ClientOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Inbound\QueryBusPort;
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
 * @implements ProcessorInterface<ClientInput, ClientOutput>
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
   * Processes the client update.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientInput $data The input data.
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return ClientOutput The processed output.
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientOutput
  {
    /** @var ClientInput $data */

    $id = $uriVariables['id'] ?? null;

    // Convert DTO to Command
    $command = new UpdateClientDetailsCommand(
      clientId: $id,
      name: $data->name,
      redirectUris: array_map(fn(string $uri) => new RedirectUri($uri), $data->redirectUris),
      scopes: Scopes::fromArray($data->scopes)
    );

    // Dispatch command
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
