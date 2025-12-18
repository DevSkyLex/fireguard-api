<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Query\ValidateClientCredentials;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Domain\ValueObject\ClientId;
use Shared\Application\Port\Outbound\HashingPort;
use Throwable;
use Shared\Application\Message\QueryHandler;

/**
 * Handler ValidateClientCredentialsHandler
 * @final
 *
 * Handles the ValidateClientCredentialsQuery.
 * Validates OAuth client credentials during token exchange.
 *
 * @category Handler
 * @package OAuth\Application\UseCase\Query\ValidateClientCredentials
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ValidateClientCredentialsHandler implements QueryHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ValidateClientCredentialsHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The client repository.
   * @param HashingPort $hashing The hashing service.
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly HashingPort $hashing
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the ValidateClientCredentialsQuery.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ValidateClientCredentialsQuery $query The query to handle.
   *
   * @return ValidateClientCredentialsResult The result message.
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
      hashed: $client->secret()
    );

    if (!$isSecretValid) {
      return new ValidateClientCredentialsResult(isValid: false);
    }

    // Credentials are valid
    return new ValidateClientCredentialsResult(
      isValid: true,
      clientId: $client->id()->value,
      allowedScopes: $client->scopes()->toArray(),
      allowedGrantTypes: $client->grantTypes()->toArray()
    );
  }
  //#endregion
}
