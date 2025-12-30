<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\Client\GetClient;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\ValueObject\Client\ClientId;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\Exception\EntityNotFoundException;

/**
 * Handler GetClientHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetClientHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the GetClientHandler class.
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
   * Handles the GetClientQuery.
   *
   * @since 1.0.0
   *
   * @param GetClientQuery $query the query to handle
   *
   * @throws EntityNotFoundException if the client is not found
   *
   * @return GetClientResult the result message
   */
  public function __invoke(GetClientQuery $query): GetClientResult
  {
    $clientId = new ClientId(value: $query->clientId);

    $client = $this->clientRepository->findById(id: $clientId);

    if (!$client) {
      throw EntityNotFoundException::forId(
        entityType: 'Client',
        id: $clientId->value,
      );
    }

    return new GetClientResult(
      id: $client->id()->value,
      name: $client->name()->value,
      redirectUris: $client->redirectUris(),
      grantTypes: $client->grantTypes()->toArray(),
      scopes: $client->scopes()->toArray(),
      isActive: $client->isActive(),
      createdAt: $client->createdAt()->format('c'),
    );
  }
  // #endregion
}
