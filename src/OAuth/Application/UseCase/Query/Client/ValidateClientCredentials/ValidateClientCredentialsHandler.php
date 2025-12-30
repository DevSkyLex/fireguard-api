<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\Client\ValidateClientCredentials;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\ValueObject\Client\ClientId;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\HashingPort;
use Throwable;

/**
 * Handler ValidateClientCredentialsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateClientCredentialsHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ValidateClientCredentialsHandler class.
   *
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository the client repository
   * @param HashingPort $hashing the hashing service
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly HashingPort $hashing,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the ValidateClientCredentialsQuery.
   *
   * @since 1.0.0
   *
   * @param ValidateClientCredentialsQuery $query the query to handle
   *
   * @return ValidateClientCredentialsResult the result message
   */
  public function __invoke(ValidateClientCredentialsQuery $query): ValidateClientCredentialsResult
  {
    try {
      $clientId = new ClientId(value: $query->clientId);
    } catch (Throwable) {
      // Invalid UUID format
      return new ValidateClientCredentialsResult(isValid: false);
    }

    $client = $this->clientRepository->findById(id: $clientId);

    if (!$client) {
      return new ValidateClientCredentialsResult(isValid: false);
    }

    // Check if client is active
    if (!$client->isActive()) {
      return new ValidateClientCredentialsResult(isValid: false);
    }

    // Verify the secret
    $isSecretValid = $this->hashing->verify(
      value: $query->clientSecret,
      hashed: $client->secret(),
    );

    if (!$isSecretValid) {
      return new ValidateClientCredentialsResult(isValid: false);
    }

    // Credentials are valid
    return new ValidateClientCredentialsResult(
      isValid: true,
      clientId: $client->id()->value,
      allowedScopes: $client->scopes()->toArray(),
      allowedGrantTypes: $client->grantTypes()->toArray(),
    );
  }
  // #endregion
}
