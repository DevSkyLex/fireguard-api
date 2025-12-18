<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Adapter;

use Auth\Application\Port\Outbound\ClientRepositoryPort;
use Auth\Domain\Model\Client;
use OAuth\Application\UseCase\Query\GetClient\GetClientQuery;
use OAuth\Application\UseCase\Query\GetClient\GetClientResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\GrantType;
use OAuth\Domain\ValueObject\Scope;
use Throwable;

/**
 * Adapter ClientRepositoryPortAdapter
 * @final
 *
 * Adapter to retrieve clients from the Client module.
 * Uses the QueryBus to communicate with the Client module,
 * maintaining proper module isolation.
 *
 * @category Adapter
 * @package OAuth\Infrastructure\OAuth2\Adapter
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRepositoryPortAdapter implements ClientRepositoryPort
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * ClientRepositoryPortAdapter class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private QueryBusPort $queryBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method find
   * {@inheritDoc}
   *
   * Finds a client by its OAuth identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OAuthClientIdentifier $identifier The client identifier.
   *
   * @return Client|null The client or null if not found.
   */
  public function find(OAuthClientIdentifier $identifier): ?Client
  {
    try {
      /** @var GetClientResult $result */
      $result = $this->queryBus->ask(new GetClientQuery(clientId: $identifier->value));
    } catch (Throwable $e) {
      return null;
    }

    if (!$result->isActive) {
      return null;
    }

    return new Client(
      identifier: $identifier,
      name: $result->name,
      redirectUris: $result->redirectUris,
      grantTypes: array_map(
        fn(string $grantType) => GrantType::from($grantType),
        $result->grantTypes
      ),
      scopes: array_map(
        fn(string $scope) => Scope::from($scope),
        $result->scopes
      ),
      secret: null,
      isConfidential: true
    );
  }
  //#endregion
}
