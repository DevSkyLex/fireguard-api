<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\UpdateClientDetails;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\Exception\InvalidClientException;
use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientName;
use Shared\Application\Handler\CommandHandler;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Outbound\EventBusPort;

/**
 * Handler UpdateClientDetailsHandler
 * @final
 *
 * Handles the update of an existing OAuth client's details.
 *
 * @category Handler
 * @package Client\Application\UseCase\Command\UpdateClientDetails
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateClientDetailsHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * UpdateClientDetailsHandler class.
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
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the UpdateClientDetailsCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param UpdateClientDetailsCommand $command The command to handle.
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

    // Update the client details
    $client->updateDetails(
      name: new ClientName(value: $command->name),
      redirectUris: $command->redirectUris,
      scopes: $command->scopes
    );

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
