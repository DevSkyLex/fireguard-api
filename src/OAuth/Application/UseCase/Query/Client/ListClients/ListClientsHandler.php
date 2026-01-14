<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\Client\ListClients;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientResult;
use OAuth\Domain\Model\Client\Client;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * Handler ListClientsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListClientsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListClientsHandler class.
   *
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository the client repository
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ListClientsQuery.
   *
   * @since 1.0.0
   *
   * @param ListClientsQuery $query the query to handle
   *
   * @return PaginatedResult<GetClientResult> the paginated result
   */
  public function __invoke(ListClientsQuery $query): PaginatedResult
  {
    $clients = $this->clientRepository->findAll(
      offset: $query->pagination->offset,
      limit: $query->pagination->limit,
    );

    $total = $this->clientRepository->count();

    $items = array_map(
      fn (Client $client) => new GetClientResult(
        id: $client->id()->value,
        name: $client->name()->value,
        redirectUris: $client->redirectUris(),
        grantTypes: $client->grantTypes()->toArray(),
        scopes: $client->scopes()->toArray(),
        isActive: $client->isActive(),
        createdAt: $client->createdAt()->format(format: 'Y-m-d H:i:s'),
      ),
      $clients,
    );

    return new PaginatedResult(
      items: $items,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset,
    );
  }
  // #endregion
}
