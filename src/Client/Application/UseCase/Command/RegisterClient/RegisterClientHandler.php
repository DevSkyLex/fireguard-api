<?php

declare(strict_types=1);

namespace Client\Application\UseCase\Command\RegisterClient;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Domain\Model\Client;
use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientName;
use Client\Domain\ValueObject\ClientSecret;
use Shared\Application\Handler\CommandHandler;
use Shared\Application\Message\CommandMessage;
use Shared\Application\Message\ResultMessage;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Application\Port\Outbound\HashingPort;
use Shared\Application\Port\Outbound\UuidGeneratorPort;
use Shared\Domain\ValueObject\Uuid;

/**
 * Handler RegisterClientHandler
 * @final
 *
 * Handles the registration of a new OAuth client.
 *
 * @category Handler
 * @package Client\Application\UseCase\Command\RegisterClient
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RegisterClientHandler implements CommandHandler
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * RegisterClientHandler class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientRepositoryPort $clientRepository The client repository.
   * @param UuidGeneratorPort $uuidGenerator The UUID generator.
   * @param HashingPort $hashing The hashing service.
   * @param EventBusPort $eventBus The event bus.
   */
  public function __construct(
    private readonly ClientRepositoryPort $clientRepository,
    private readonly UuidGeneratorPort $uuidGenerator,
    private readonly HashingPort $hashing,
    private readonly EventBusPort $eventBus
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method __invoke
   *
   * Handles the RegisterClientCommand.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RegisterClientCommand $command The command to handle.
   *
   * @return RegisterClientResult The result message.
   */
  public function __invoke(CommandMessage $command): ResultMessage
  {
    // Generate client ID
    $clientId = new ClientId(value: $this->uuidGenerator->generate());

    // Generate plain secret (32 random bytes = 64 hex chars)
    $plainSecret = bin2hex(random_bytes(32));

    // Hash the secret
    $hashedSecret = new ClientSecret(
      value: $this->hashing->hash(value: $plainSecret)->value
    );

    // Create the client
    $client = Client::register(
      id: $clientId,
      name: new ClientName(value: $command->name),
      secret: $hashedSecret,
      redirectUris: $command->redirectUris,
      grantTypes: $command->grantTypes,
      scopes: $command->scopes
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
  //#endregion
}
