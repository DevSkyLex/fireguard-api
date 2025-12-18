<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\Repository;

use OAuth\Application\Port\Outbound\LeagueClientRepositoryPort;
use OAuth\Application\Port\Outbound\ClientValidationPort;
use OAuth\Infrastructure\OAuth2\Entity\Client;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use OAuth\Domain\ValueObject\OAuthClientIdentifier;
use Throwable;

/**
 * Repository ClientRepositoryAdapter
 * @final
 *
 * Adapter implementing League's ClientRepositoryInterface.
 *
 * @category Repository
 * @package OAuth\Infrastructure\OAuth2\Repository
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRepositoryAdapter implements ClientRepositoryInterface
{
  //#region Constructor
  public function __construct(
    private LeagueClientRepositoryPort $clientRepository,
    private ClientValidationPort $clientValidation
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * {@inheritDoc}
   */
  /**
   * @param string $clientIdentifier
   */
  public function getClientEntity($clientIdentifier): ?Client
  {
    try {
      $identifier = new OAuthClientIdentifier(value: $clientIdentifier);
      $client = $this->clientRepository->find(identifier: $identifier);

      if (!$client)
        return null;

      return new Client(
        identifier: (string) $client->identifier(),
        name: $client->name(),
        redirectUri: $client->redirectUris(),
        isConfidential: $client->isConfidential()
      );
    } catch (Throwable $exception) {
      return null;
    }
  }

  /**
   * {@inheritDoc}
   */
  /**
   * @param string $clientIdentifier
   * @param string|null $clientSecret
   * @param string|null $grantType
   */
  public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
  {
    return $this->clientValidation->validateCredentials(
      clientId: $clientIdentifier,
      clientSecret: $clientSecret ?? ''
    );
  }
  //#endregion
}
