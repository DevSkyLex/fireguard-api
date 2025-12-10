<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\RegenerateClientSecret;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\Exception\InvalidClientException;
use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientSecret;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Domain\Service\EventIdProvider;

/**
 * Handler RegenerateClientSecretHandler
 * @final
 *
 * Handles the regeneration of an OAuth client's secret.
 *
 * @category Handler
 * @package Client\Application\UseCase\Command\RegenerateClientSecret
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegenerateClientSecretHandler implements \Shared\Application\Message\CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the RegenerateClientSecretHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The client repository.
   * @param HashingPort $hashing The hashing service.
   * @param EventBusPort $eventBus The event bus.
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly HashingPort $hashing,
    private readonly EventBusPort $eventBus,
    private readonly EventIdProvider $eventIdProvider,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the RegenerateClientSecretCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RegenerateClientSecretCommand $command The command to handle.
   *
   * @return RegenerateClientSecretResult The result message with the new plain secret.
   * @throws InvalidClientException If the client is not found.
   */
  public function __invoke(RegenerateClientSecretCommand $command): RegenerateClientSecretResult
  {
    // Find the client
    $clientId = new ClientId(value: $command->clientId);
    $client = $this->clientRepository->findById(id: $clientId);

    if ($client === null) {
      throw new InvalidClientException(message: 'Client not found');
    }

    // Generate new plain secret (32 random bytes = 64 hex chars)
    $plainSecret = bin2hex(random_bytes(32));

    // Hash the secret
    $hashedSecret = new ClientSecret(
      value: $this->hashing->hash(value: $plainSecret)->value
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
      clientSecret: $plainSecret
    );
  }
  //#endregion
}
