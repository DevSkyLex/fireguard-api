<?php

declare(strict_types=1);

namespace OAuth\Application\UseCase\Command\RegisterClient;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\ClientId;
use OAuth\Domain\ValueObject\ClientName;
use OAuth\Domain\ValueObject\ClientSecret;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Domain\Service\EventIdProvider;

use function bin2hex;
use function random_bytes;

/**
 * Handler RegisterClientHandler.
 *
 * @category Handler
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterClientHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * RegisterClientHandler class.
   *
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository the client repository
   * @param UuidFactory          $uuidFactory      the UUID factory
   * @param HashingPort          $hashing          the hashing service
   * @param EventBusPort         $eventBus         the event bus
   * @param EventIdProvider      $eventIdProvider  the event ID provider
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly UuidFactory $uuidFactory,
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
   * Handles the RegisterClientCommand.
   *
   * @since 1.0.0
   *
   * @param RegisterClientCommand $command the command to handle
   *
   * @return RegisterClientResult the result message
   */
  public function __invoke(RegisterClientCommand $command): RegisterClientResult
  {
    // Generate client ID using factory
    $clientId = $this->uuidFactory->create(ClientId::class);

    // Generate plain secret (32 random bytes = 64 hex chars)
    $plainSecret = bin2hex(random_bytes(32));

    // Hash the secret
    $hashedSecret = new ClientSecret(
      value: $this->hashing->hash(value: $plainSecret)->value
    );

    // Create the client with event ID provider
    $client = Client::register(
      id: $clientId,
      name: new ClientName(value: $command->name),
      secret: $hashedSecret,
      redirectUris: $command->redirectUris,
      grantTypes: $command->grantTypes,
      scopes: $command->scopes,
      eventIdProvider: $this->eventIdProvider,
    );

    // Save the client
    $this->clientRepository->save(client: $client);

    // Publish domain events
    foreach ($client->releaseEvents() as $event) {
      $this->eventBus->publish(event: $event);
    }

    // Return the result with plain secret (shown only once)
    return new RegisterClientResult(
      clientId: $clientId->value,
      clientSecret: $plainSecret
    );
  }
  // #endregion
}
