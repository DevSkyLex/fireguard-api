<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\Client;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use OAuth\Application\UseCase\Query\GetClient\GetClientQuery;
use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use OAuth\Presentation\Api\Dto\Output\ClientOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Provider GetClientProvider
 * @final
 *
 * API Platform provider for fetching a single client.
 *
 * @category Provider
 * @package OAuth\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProviderInterface<ClientOutput>
 */
final readonly class GetClientProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * GetClientProvider class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private readonly QueryBusPort $queryBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the client output.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return ClientOutput|null The client output or null if not found.
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ClientOutput
  {
    $id = $uriVariables['id'] ?? null;

    if (!is_string($id)) {
      return null;
    }

    $query = new GetClientQuery(clientId: $id);

    try {
      $result = $this->queryBus->ask(query: $query);
    } catch (EntityNotFoundException) {
      return null;
    }

    assert($result instanceof GetClientResult);

    $output = new ClientOutput();
    $output->id = $result->id;
    $output->name = $result->name;
    // Secret is never returned in GET
    $output->redirectUris = $result->redirectUris;
    $output->grantTypes = $result->grantTypes;
    $output->scopes = $result->scopes;
    $output->isActive = $result->isActive;
    $output->createdAt = $result->createdAt;

    return $output;
  }
  //#endregion
}
