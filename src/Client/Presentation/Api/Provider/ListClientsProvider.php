<?php

declare(strict_types=1);

namespace Client\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Client\Application\UseCase\Query\GetClient\GetClientResult;
use Client\Application\UseCase\Query\ListClients\ListClientsQuery;
use Client\Presentation\Api\Resource\ClientResource;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Query\PaginatedResult;
use Shared\Application\Query\Pagination;

/**
 * Provider ListClientsProvider
 * @final
 *
 * API Platform provider for listing clients.
 *
 * @category Provider
 * @package Client\Presentation\Api\Provider
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @implements ProviderInterface<ClientResource>
 */
final readonly class ListClientsProvider implements ProviderInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ListClientsProvider class.
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
   * Provides the collection of client resources.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Operation $operation The operation.
   * @param array<string, mixed> $uriVariables The URI variables.
   * @param array<string, mixed> $context The context.
   *
   * @return TraversablePaginator<ClientResource> The collection of resources.
   * @phpstan-return TraversablePaginator<ClientResource>
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $page = (int) ($context['filters']['page'] ?? 1);
    $itemsPerPage = (int) ($context['filters']['itemsPerPage'] ?? 30);

    // Ensure valid values
    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    $query = new ListClientsQuery(
      pagination: new Pagination(offset: $offset, limit: $itemsPerPage)
    );

    /** @var PaginatedResult<GetClientResult> $result */
    $result = $this->queryBus->ask(query: $query);

    $resources = array_map(
      fn(GetClientResult $item) => $this->mapToResource(result: $item),
      $result->items
    );

    return new TraversablePaginator(
      traversable: new ArrayIterator(array: $resources),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total
    );
  }

  /**
   * Method mapToResource
   *
   * Maps a GetClientResult to a ClientResource.
   *
   * @access private
   * @since 1.0.0
   *
   * @param GetClientResult $result The result to map.
   *
   * @return ClientResource The mapped resource.
   */
  private function mapToResource(GetClientResult $result): ClientResource
  {
    $resource = new ClientResource();
    $resource->id = $result->id;
    $resource->name = $result->name;
    $resource->redirectUris = $result->redirectUris;
    $resource->grantTypes = $result->grantTypes;
    $resource->scopes = $result->scopes;
    $resource->isActive = $result->isActive;
    $resource->createdAt = $result->createdAt;

    return $resource;
  }
  //#endregion
}
