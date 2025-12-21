<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\RegenerateClientSecret\{RegenerateClientSecretCommand, RegenerateClientSecretResult};
use OAuth\Application\UseCase\Query\GetClient\GetClientQuery;
use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use OAuth\Presentation\Api\Dto\Output\ClientOutput;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Inbound\QueryBusPort;

use function is_string;

/**
 * Processor RegenerateSecretProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<mixed, ClientOutput>
 */
final readonly class RegenerateSecretProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RegenerateSecretProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort   $queryBus   the query bus
   */
  public function __construct(
    private readonly CommandBusPort $commandBus,
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method process
   * {@inheritDoc}
   *
   * Processes the secret regeneration.
   *
   * @since 1.0.0
   *
   * @param mixed                $data         the input data (not used)
   * @param Operation            $operation    the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context      the context
   *
   * @return ClientOutput the processed output with new secret
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw new InvalidArgumentException('Client ID must be a string');
    }

    // Dispatch command
    $command = new RegenerateClientSecretCommand(clientId: $id);
    /** @var RegenerateClientSecretResult $result */
    $result = $this->commandBus->dispatch($command);

    // Fetch updated client
    $query = new GetClientQuery(clientId: $id);
    /** @var GetClientResult $clientResult */
    $clientResult = $this->queryBus->ask(query: $query);

    // Create output DTO
    $output = new ClientOutput();
    $output->id = $clientResult->id;
    $output->name = $clientResult->name;
    $output->secret = $result->clientSecret; // Include new secret
    $output->redirectUris = $clientResult->redirectUris;
    $output->grantTypes = $clientResult->grantTypes;
    $output->scopes = $clientResult->scopes;
    $output->isActive = $clientResult->isActive;
    $output->createdAt = $clientResult->createdAt;

    return $output;
  }
  // #endregion
}
