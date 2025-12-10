<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\ActivateClient;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\Exception\InvalidClientException;
use Client\Domain\ValueObject\ClientId;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Service\EventIdProvider;

/**
 * Handler ActivateClientHandler
 * @final
 *
 * Handles the activation of an OAuth client.
 *
 * @category Handler
 * @package Client\Application\UseCase\Command\ActivateClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ActivateClientHandler implements \Shared\Application\Message\CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The client repository.
   * @param EventBusPort $eventBus The event bus.
   * @param EventIdProvider $eventIdProvider The event ID provider.
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly EventBusPort $eventBus,
    private readonly EventIdProvider $eventIdProvider,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the ActivateClientCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ActivateClientCommand $command The command to handle.
   *
   * @return void
   * @throws InvalidClientException If the client is not found.
   */
  public function __invoke(ActivateClientCommand $command): void
  {
    // Find the client
    $clientId = new ClientId(value: $command->clientId);
    $client = $this->clientRepository->findById(id: $clientId);

    if ($client === null) {
      throw new InvalidClientException(message: 'Client not found');
    }

    // Activate the client
    $client->activate($this->eventIdProvider);

    // Save the client
    $this->clientRepository->save(client: $client);

    // Publish domain events
    foreach ($client->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }

  }
  //#endregion
}
