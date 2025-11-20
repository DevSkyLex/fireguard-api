<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\DeleteClient;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\Exception\InvalidClientException;
use Client\Domain\ValueObject\ClientId;
use Shared\Application\Handler\CommandHandler;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Outbound\EventBusPort;

/**
 * Handler DeleteClientHandler
 * @final
 *
 * Handles the deletion of an OAuth client (soft delete).
 *
 * @category Handler
 * @package Client\Application\UseCase\Command\DeleteClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteClientHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the DeleteClientHandler class.
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
   * Handles the DeleteClientCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param DeleteClientCommand $command The command to handle.
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

    // Soft delete the client
    $client->delete();

    // Save the client (with deletedAt set)
    $this->clientRepository->save(client: $client);

    // Publish domain events
    foreach ($client->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }

    return null;
  }
  //#endregion
}
