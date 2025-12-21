<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Repository;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use OAuth\Application\Port\Outbound\ClientValidationPort;
use OAuth\Application\Port\Outbound\LeagueClientRepositoryPort;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use OAuth\Infrastructure\OAuth2\Entity\Client;
use Throwable;

/**
 * Repository ClientRepositoryAdapter.
 *
 * @category Repository
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRepositoryAdapter implements ClientRepositoryInterface
{
  // #region Constructor
  public function __construct(
    private LeagueClientRepositoryPort $clientRepository,
    private ClientValidationPort $clientValidation,
  ) {
  }
  // #endregion

  // #region Methods
  public function getClientEntity(string $clientIdentifier): ?Client
  {
    try {
      $identifier = new OAuthClientIdentifier(value: $clientIdentifier);
      $client = $this->clientRepository->find(identifier: $identifier);

      if (!$client) {
        return null;
      }

      return new Client(
        identifier: (string) $client->identifier(),
        name: $client->name(),
        redirectUri: $client->redirectUris(),
        isConfidential: $client->isConfidential(),
      );
    } catch (Throwable $exception) {
      return null;
    }
  }

  public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
  {
    return $this->clientValidation->validateCredentials(
      clientId: $clientIdentifier,
      clientSecret: $clientSecret ?? '',
    );
  }
  // #endregion
}
