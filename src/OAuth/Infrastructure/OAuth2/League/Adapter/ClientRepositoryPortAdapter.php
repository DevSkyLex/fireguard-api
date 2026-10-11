<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Adapter;

use Auth\Application\Contract\Client\ClientRegistration;
use Auth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Application\UseCase\Query\Client\GetClient\{GetClientQuery, GetClientResult};
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;

/**
 * Adapter ClientRepositoryPortAdapter.
 *
 * @category Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRepositoryPortAdapter implements ClientRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ClientRepositoryPortAdapter class.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus
   */
  public function __construct(
    private QueryBusPort $queryBus,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method find
   * {@inheritDoc}
   *
   * Finds a client by its OAuth identifier.
   *
   * @since 1.0.0
   *
   * @param string $identifier the client identifier
   *
   * @return ClientRegistration|null the client or null if not found
   */
  public function find(string $identifier): ?ClientRegistration
  {
    try {
      /** @var GetClientResult $result */
      $result = $this->queryBus->ask(new GetClientQuery(clientId: $identifier));
    } catch (Throwable $e) {
      return null;
    }

    if (!$result->isActive) {
      return null;
    }

    return new ClientRegistration(
      identifier: $identifier,
      name: $result->name,
      redirectUris: $result->redirectUris,
      grantTypes: $result->grantTypes,
      scopes: $result->scopes,
    );
  }
  // #endregion
}
