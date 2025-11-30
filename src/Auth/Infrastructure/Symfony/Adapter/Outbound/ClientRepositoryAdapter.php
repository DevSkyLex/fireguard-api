<?php

declare(strict_types=1);

namespace Auth\Infrastructure\Symfony\Adapter\Outbound;

use Auth\Application\Port\Outbound\ClientRepositoryPort;
use Auth\Domain\Model\Client;
use Client\Application\UseCase\Query\GetClient\GetClientQuery;
use Client\Application\UseCase\Query\GetClient\GetClientResult;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Domain\ValueObject\OAuthClientIdentifier;

/**
 * Adapter ClientRepositoryAdapter
 * @final
 *
 * Adapter to retrieve clients from the Client module (via Symfony/App Bus).
 *
 * @category Adapter
 * @package Auth\Infrastructure\Symfony\Adapter\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRepositoryAdapter implements ClientRepositoryPort
{
  /**
   * Constructor
   *
   * @param QueryBusPort $queryBus The query bus.
   */
  public function __construct(
    private QueryBusPort $queryBus
  ) {
  }

  /**
   * Method find
   * {@inheritDoc}
   */
  public function find(OAuthClientIdentifier $identifier): ?Client
  {
    try {
      /** @var GetClientResult $result */
      $result = $this->queryBus->ask(new GetClientQuery(clientId: $identifier->value));
    } catch (\Throwable $e) {
      // Client not found or other error
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
        fn(string $grantType) => \Shared\Domain\ValueObject\GrantType::from($grantType),
        $result->grantTypes
      ),
      scopes: array_map(
        fn(string $scope) => \Shared\Domain\ValueObject\Scope::from($scope),
        $result->scopes
      ),
      secret: null, // Secret is not exposed by GetClientResult
      isConfidential: true // Assuming all clients are confidential for now
    );
  }
}
