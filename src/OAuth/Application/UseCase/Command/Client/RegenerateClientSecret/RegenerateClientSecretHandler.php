<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Client\RegenerateClientSecret;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\Exception\Client\InvalidClientException;
use OAuth\Domain\ValueObject\Client\ClientId;
use OAuth\Domain\ValueObject\Client\ClientSecret;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Domain\Service\EventIdProvider;

use function bin2hex;
use function random_bytes;

/**
 * Handler RegenerateClientSecretHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegenerateClientSecretHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RegenerateClientSecretHandler class.
   *
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository the client repository
   * @param HashingPort $hashing the hashing service
   * @param EventBusPort $eventBus the event bus
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly HashingPort $hashing,
    private readonly EventBusPort $eventBus,
    private readonly EventIdProvider $eventIdProvider,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the RegenerateClientSecretCommand.
   *
   * @since 1.0.0
   *
   * @param RegenerateClientSecretCommand $command the command to handle
   *
   * @throws InvalidClientException if the client is not found
   *
   * @return RegenerateClientSecretResult the result message with the new plain secret
   */
  public function __invoke(RegenerateClientSecretCommand $command): RegenerateClientSecretResult
  {
    // Find the client
    $clientId = new ClientId(value: $command->clientId);
    $client = $this->clientRepository->findById(id: $clientId);

    if (null === $client) {
      throw new InvalidClientException(message: 'Client not found');
    }

    // Generate new plain secret (32 random bytes = 64 hex chars)
    $plainSecret = bin2hex(random_bytes(32));

    // Hash the secret
    $hashedSecret = new ClientSecret(
      value: $this->hashing->hash(value: $plainSecret)->value,
    );

    // Regenerate the client secret
    $client->regenerateSecret(newSecret: $hashedSecret, eventIdProvider: $this->eventIdProvider);

    // Save the client
    $this->clientRepository->save(client: $client);

    // Publish domain events
    foreach ($client->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }

    // Return the result with plain secret (shown only once)
    return new RegenerateClientSecretResult(
      clientId: $clientId->value,
      clientSecret: $plainSecret,
    );
  }
  // #endregion
}
