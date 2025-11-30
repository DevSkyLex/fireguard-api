<?php

declare(strict_types=1);

namespace Auth\Infrastructure\League\Adapter\Outbound;

use Auth\Application\Port\Outbound\ClientRepositoryPort;
use Auth\Application\Port\Outbound\ClientValidationPort;
use Auth\Infrastructure\League\Model\Client;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Shared\Domain\ValueObject\OAuthClientIdentifier;
use Throwable;

/**
 * Adapter ClientRepositoryAdapter
 * @final
 *
 * Adapter for League ClientRepositoryInterface.
 * Uses ports to maintain proper hexagonal architecture.
 *
 * @category Adapter
 * @package Auth\Infrastructure\League\Adapter\Outbound
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRepositoryAdapter implements ClientRepositoryInterface
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initialize the adapter with the required ports.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The domain client repository.
   * @param ClientValidationPort $clientValidation The client validation port.
   */
  public function __construct(
    private ClientRepositoryPort $clientRepository,
    private ClientValidationPort $clientValidation
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method getClientEntity
   * {@inheritDoc}
   *
   * Get a client entity by identifier.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientIdentifier The client identifier.
   *
   * @return Client|null The client entity or null if not found.
   */
  public function getClientEntity($clientIdentifier): ?Client
  {
    try {
      $identifier = new OAuthClientIdentifier(value: $clientIdentifier);
      $client = $this->clientRepository->find(identifier: $identifier);

      if (!$client) return null;

      return new Client(
        identifier: (string) $client->identifier(),
        name: $client->name(),
        redirectUri: $client->redirectUris(),
        isConfidential: $client->isConfidential()
      );
    }
    catch (Throwable $exception) {
      return null;
    }
  }

  /**
   * Method validateClient
   * {@inheritDoc}
   *
   * Validate a client.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $clientIdentifier The client identifier.
   * @param string|null $clientSecret The client secret.
   * @param string|null $grantType The grant type.
   *
   * @return bool True if the client is valid, false otherwise.
   */
  public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
  {
    return $this->clientValidation->validateCredentials(
      clientId: $clientIdentifier,
      clientSecret: $clientSecret ?? ''
    );
  }
}
