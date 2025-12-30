<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Adapter;

use Auth\Application\Port\Outbound\ClientRepositoryPort;
use Auth\Domain\Model\Client;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientQuery;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientResult;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Security\GrantType;
use Shared\Application\Port\Inbound\QueryBusPort;
use Throwable;

use function array_map;

/**
 * Adapter ClientRepositoryPortAdapter.
 *
 * @category Adapter
 *
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
   * @param OAuthClientIdentifier $identifier the client identifier
   *
   * @return Client|null the client or null if not found
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
        fn (string $grantType) => GrantType::from($grantType),
        $result->grantTypes,
      ),
      scopes: array_map(
        fn (string $scope) => Scope::from($scope),
        $result->scopes,
      ),
      secret: null,
      isConfidential: true,
    );
  }
  // #endregion
}
