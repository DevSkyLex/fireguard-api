<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\Client\UpdateClientDetails;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Application\UseCase\Command\Client\UpdateClientDetails\{
  UpdateClientDetailsCommand,
  UpdateClientDetailsHandler
};
use OAuth\Domain\Exception\Client\InvalidClientException;
use OAuth\Domain\Model\Client\Client;
use OAuth\Domain\ValueObject\Client\{ClientId, ClientName, ClientSecret, RedirectUri};
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventBusPort;
use Tests\Helper\TestEventIdProvider;

use function password_hash;

use const PASSWORD_BCRYPT;

/**
 * Test UpdateClientDetailsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UpdateClientDetailsHandler::class)]
final class UpdateClientDetailsHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeUpdatesClientDetails.
   *
   * Test that __invoke updates client
   * details successfully
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeUpdatesClientDetails(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    // Create real client
    $eventIdProvider = new TestEventIdProvider();
    $client = Client::register(
      id: new ClientId($clientId),
      name: new ClientName('Original Name'),
      secret: new ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://old.example.com')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: $eventIdProvider,
    );
    $client->releaseEvents(); // Clear creation events

    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::equalTo(new ClientId($clientId)))
      ->willReturn($client);
    $repository->expects(self::once())
      ->method('save')
      ->with($client);

    $eventBus = $this->createMock(EventBusPort::class);
    $eventBus->expects(self::once())
      ->method('publish');

    // Command
    $command = new UpdateClientDetailsCommand(
      clientId: $clientId,
      name: 'Updated Client',
      redirectUris: [new RedirectUri('https://new.example.com')],
      scopes: new Scopes(Scope::WRITE),
    );

    // Handler
    $handler = new UpdateClientDetailsHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    // Execute
    $handler->__invoke($command);

    // Verify state change
    self::assertEquals('Updated Client', $client->name()->value);
  }

  /**
   * Method testInvokeThrowsExceptionWhenClientNotFound.
   *
   * Test that __invoke throws exception
   * when client is not found
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeThrowsExceptionWhenClientNotFound(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $eventBus = $this->createStub(EventBusPort::class);

    $command = new UpdateClientDetailsCommand(
      clientId: $clientId,
      name: 'Updated Client',
      redirectUris: [],
      scopes: new Scopes(Scope::READ),
    );

    $handler = new UpdateClientDetailsHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    $this->expectException(InvalidClientException::class);
    $handler->__invoke($command);
  }
  // #endregion
}
