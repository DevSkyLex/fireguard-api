<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Query\GetClient;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\ValueObject\ClientId;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Handler GetClientHandler
 * @final
 *
 * Handles the GetClientQuery.
 *
 * @category Handler
 * @package Client\Application\UseCase\Query\GetClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetClientHandler implements \Shared\Application\Message\QueryHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of
   * the GetClientHandler class.
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
   * Handles the GetClientQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GetClientQuery $query The query to handle.
   *
   * @return GetClientResult The result message.
   *
   * @throws EntityNotFoundException If the client is not found.
   */
  public function __invoke(GetClientQuery $query): GetClientResult
  {
    $clientId = new ClientId(value: $query->clientId);

    $client = $this->clientRepository->findById(id: $clientId);

    if (!$client)
      throw EntityNotFoundException::forId(
        entityType: 'Client',
        id: $clientId->value
      );

    return new GetClientResult(
      id: $client->id()->value,
      name: $client->name()->value,
      redirectUris: $client->redirectUris(),
      grantTypes: $client->grantTypes()->toArray(),
      scopes: $client->scopes()->toArray(),
      isActive: $client->isActive(),
      createdAt: $client->createdAt()->format('c')
    );
  }
  //#endregion
}
