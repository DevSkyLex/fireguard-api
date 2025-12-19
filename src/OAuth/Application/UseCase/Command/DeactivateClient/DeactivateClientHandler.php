<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\DeactivateClient;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Domain\Exception\InvalidClientException;
use OAuth\Domain\ValueObject\ClientId;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\Service\EventIdProvider;

/**
 * Handler DeactivateClientHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeactivateClientHandler implements CommandHandler
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of the DeactivateClientHandler class.
     *
     * @since 1.0.0
     *
     * @param ClientRepositoryPort $clientRepository the client repository
     * @param EventBusPort         $eventBus         the event bus
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
     * Handles the DeactivateClientCommand.
     *
     * @since 1.0.0
     *
     * @param DeactivateClientCommand $command the command to handle
     *
     * @throws InvalidClientException if the client is not found
     */
    public function __invoke(DeactivateClientCommand $command): void
    {
        // Find the client
        $clientId = new ClientId(value: $command->clientId);
        $client = $this->clientRepository->findById(id: $clientId);

        if (null === $client) {
            throw new InvalidClientException(message: 'Client not found');
        }

        // Deactivate the client
        $client->deactivate($this->eventIdProvider);

        // Save the client
        $this->clientRepository->save(client: $client);

        // Publish domain events
        foreach ($client->releaseEvents() as $event) {
            $this->eventBus->publish(event: $event);
        }

    }
    // #endregion
}
