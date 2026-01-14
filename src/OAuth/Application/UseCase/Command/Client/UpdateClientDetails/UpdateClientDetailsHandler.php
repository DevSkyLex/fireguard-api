<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\Client\UpdateClientDetails;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Domain\Exception\Client\InvalidClientException;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Service\EventIdProvider;

/**
 * Handler UpdateClientDetailsHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateClientDetailsHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UpdateClientDetailsHandler class.
   *
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository the client repository
   * @param EventBusPort $eventBus the event bus
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly EventBusPort $eventBus,
    private readonly EventIdProvider $eventIdProvider,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Handles the UpdateClientDetailsCommand.
   *
   * @since 1.0.0
   *
   * @param UpdateClientDetailsCommand $command the command to handle
   *
   * @throws InvalidClientException if the client is not found
   *
   * @return void no return value
   */
  public function __invoke(UpdateClientDetailsCommand $command): void
  {
    // Find the client
    $clientId = new ClientId(value: $command->clientId);
    $client = $this->clientRepository->findById(id: $clientId);

    if (null === $client) {
      throw new InvalidClientException(message: 'Client not found');
    }

    // Update the client details
    $client->updateDetails(
      name: new ClientName(value: $command->name),
      redirectUris: $command->redirectUris,
      scopes: $command->scopes,
      eventIdProvider: $this->eventIdProvider,
    );

    // Save the client
    $this->clientRepository->save(client: $client);

    // Publish domain events
    foreach ($client->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }

  }
  // #endregion
}
