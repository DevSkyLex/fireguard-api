<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Query\ListClients;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Application\UseCase\Query\GetClient\GetClientResult;
use Client\Domain\Model\Client;
use Shared\Application\Query\PaginatedResult;

/**
 * Handler ListClientsHandler
 * @final
 *
 * Handles the listing of clients.
 *
 * @category Handler
 * @package Client\Application\UseCase\Query\ListClients
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListClientsHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ListClientsHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The client repository.
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the ListClientsQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ListClientsQuery $query The query to handle.
   *
   * @return PaginatedResult<GetClientResult> The paginated result.
   */
  public function __invoke(ListClientsQuery $query): PaginatedResult
  {
    $clients = $this->clientRepository->findAll(
      offset: $query->pagination->offset,
      limit: $query->pagination->limit
    );

    $total = $this->clientRepository->count();

    $items = array_map(
      fn(Client $client) => new GetClientResult(
        id: $client->id()->value,
        name: $client->name()->value,
        redirectUris: $client->redirectUris(),
        grantTypes: $client->grantTypes()->toArray(),
        scopes: $client->scopes()->toArray(),
        isActive: $client->isActive(),
        createdAt: $client->createdAt()->format(format: 'Y-m-d H:i:s')
      ),
      $clients
    );

    return new PaginatedResult(
      items: $items,
      total: $total,
      limit: $query->pagination->limit,
      offset: $query->pagination->offset
    );
  }
  //#endregion
}
