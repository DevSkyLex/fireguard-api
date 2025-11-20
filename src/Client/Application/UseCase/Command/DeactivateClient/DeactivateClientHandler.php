<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\DeactivateClient;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\Exception\InvalidClientException;
use Client\Domain\ValueObject\ClientId;
use Shared\Application\Handler\CommandHandler;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Outbound\EventBusPort;

/**
 * Handler DeactivateClientHandler
 * @final
 *
 * Handles the deactivation of an OAuth client.
 *
 * @category Handler
 * @package Client\Application\UseCase\Command\DeactivateClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeactivateClientHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the DeactivateClientHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The client repository.
   * @param EventBusPort $eventBus The event bus.
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly EventBusPort $eventBus
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the DeactivateClientCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DeactivateClientCommand $command The command to handle.
   *
   * @return null Always returns null.
   * @throws InvalidClientException If the client is not found.
   */
  public function __invoke(CommandMessage $command): ?ResultMessage
  {
    // Find the client
    $clientId = new ClientId(value: $command->clientId);
    $client = $this->clientRepository->findById(id: $clientId);

    if ($client === null) {
      throw new InvalidClientException(message: 'Client not found');
    }

    // Deactivate the client
    $client->deactivate();

    // Save the client
    $this->clientRepository->save(client: $client);

    // Publish domain events
    foreach ($client->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }

    return null;
  }
  //#endregion
}
