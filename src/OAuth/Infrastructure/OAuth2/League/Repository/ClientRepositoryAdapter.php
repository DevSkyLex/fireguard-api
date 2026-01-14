<?php

declare(strict_types=1);

namespace OAuth\Infrastructure\OAuth2\League\Repository;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use OAuth\Application\Port\Outbound\Client\{ClientValidationPort, OAuthClientRepositoryPort};
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Infrastructure\OAuth2\League\Entity\Client;
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
  /**
   * Constructor.
   *
   * Initialize the ClientRepositoryAdapter.
   *
   * @since 2.0.0
   *
   * @param OAuthClientRepositoryPort $clientRepository the OAuth client repository
   * @param ClientValidationPort $clientValidation the client validation service
   */
  public function __construct(
    private OAuthClientRepositoryPort $clientRepository,
    private ClientValidationPort $clientValidation,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method getClientEntity
   * {@inheritDoc}
   *
   * Get a client entity by its identifier.
   *
   * @since 2.0.0
   *
   * @param string $clientIdentifier the client identifier
   *
   * @return Client|null the client entity
   */
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

  /**
   * Method validateClient
   * {@inheritDoc}
   *
   * Validate client credentials.
   *
   * @since 2.0.0
   *
   * @param string $clientIdentifier the client identifier
   * @param string|null $clientSecret the client secret
   * @param string|null $grantType the grant type
   *
   * @return bool True if valid
   */
  public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
  {
    return $this->clientValidation->validateCredentials(
      clientId: $clientIdentifier,
      clientSecret: $clientSecret ?? '',
    );
  }
  // #endregion
}
