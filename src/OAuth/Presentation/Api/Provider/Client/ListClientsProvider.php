<?php

declare(strict_types=1);

namespace OAuth\Presentation\Api\Provider\Client;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use OAuth\Application\UseCase\Query\ListClients\ListClientsQuery;
use OAuth\Presentation\Api\Dto\Output\ClientOutput;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Query\PaginatedResult;
use Shared\Application\Query\Pagination;

use function array_map;
use function is_numeric;
use function max;

/**
 * Provider ListClientsProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<ClientOutput>
 */
final readonly class ListClientsProvider implements ProviderInterface
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ListClientsProvider class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private readonly QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide
   * {@inheritDoc}
   *
   * Provides the collection of client outputs.
   *
   * @since 1.0.0
   *
   * @param Operation            $operation    the operation
   * @param array<string, mixed> $uriVariables the URI variables
   * @param array<string, mixed> $context      the context
   *
   * @return TraversablePaginator<ClientOutput> the collection of outputs
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $filters = $context['filters'] ?? [];
    /** @var array<string, mixed> $filters */
    $pageValue = $filters['page'] ?? 1;
    $itemsPerPageValue = $filters['itemsPerPage'] ?? 30;

    $page = is_numeric($pageValue) ? (int) $pageValue : 1;
    $itemsPerPage = is_numeric($itemsPerPageValue) ? (int) $itemsPerPageValue : 30;

    // Ensure valid values
    $page = max(1, $page);
    $itemsPerPage = max(1, $itemsPerPage);

    $offset = ($page - 1) * $itemsPerPage;

    $query = new ListClientsQuery(
      pagination: new Pagination(
        offset: $offset,
        limit: $itemsPerPage
      )
    );

    /** @var PaginatedResult<GetClientResult> $result */
    $result = $this->queryBus->ask(query: $query);

    $outputs = array_map(
      fn (GetClientResult $item): ClientOutput => $this->mapToOutput(result: $item),
      $result->items
    );

    return new TraversablePaginator(
      traversable: new ArrayIterator(array: $outputs),
      currentPage: (float) $page,
      itemsPerPage: (float) $itemsPerPage,
      totalItems: (float) $result->total
    );
  }

  /**
   * Method mapToOutput.
   *
   * Maps a GetClientResult to a ClientOutput.
   *
   * @since 1.0.0
   *
   * @param GetClientResult $result the result to map
   *
   * @return ClientOutput the mapped output
   */
  private function mapToOutput(GetClientResult $result): ClientOutput
  {
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
