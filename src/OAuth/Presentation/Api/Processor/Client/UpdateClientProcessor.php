<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Processor\Client;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use InvalidArgumentException;
use OAuth\Application\UseCase\Command\Client\UpdateClientDetails\UpdateClientDetailsCommand;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use OAuth\Domain\ValueObject\Client\RedirectUri;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Presentation\Api\Dto\Input\Client\ClientInput;
use OAuth\Presentation\Api\Dto\Output\Client\ClientOutput;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};

use function array_map;
use function is_string;

/**
 * Processor UpdateClientProcessor.
 *
 * @category Processor
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProcessorInterface<ClientInput, ClientOutput>
 */
final readonly class UpdateClientProcessor implements ProcessorInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the UpdateClientProcessor class.
   *
   * @since 1.0.0
   *
   * @param CommandBusPort $commandBus the command bus
   * @param QueryBusPort $queryBus the query bus
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
   * Processes the client update.
   *
   * @since 1.0.0
   *
   * @param ClientInput $data the input data
   * @param Operation $operation the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context the context
   *
   * @return ClientOutput the processed output
   */
  public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ClientOutput
  {
    /** @var ClientInput $data */
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      throw new InvalidArgumentException(
        message: 'Client ID must be a string',
        previous: null,
      );
    }

    $name = $data->name ?? '';

    // Convert DTO to Command
    $command = new UpdateClientDetailsCommand(
      clientId: $id,
      name: $name,
      redirectUris: array_map(fn (string $uri) => new RedirectUri($uri), $data->redirectUris),
      scopes: Scopes::fromArray($data->scopes),
    );

    // Dispatch command
    $this->commandBus->dispatch(command: $command);

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
  // #endregion
}
