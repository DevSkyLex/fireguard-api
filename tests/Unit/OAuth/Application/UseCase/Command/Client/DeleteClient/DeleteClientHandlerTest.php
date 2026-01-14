<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\Client\DeleteClient;

use OAuth\Application\Port\Outbound\Client\ClientRepositoryPort;
use OAuth\Application\UseCase\Command\Client\DeleteClient\{
  DeleteClientCommand,
  DeleteClientHandler
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
 * Test DeleteClientHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeleteClientHandler::class)]
final class DeleteClientHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeDeletesClient.
   *
   * Test that __invoke deletes client
   * successfully (soft delete)
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeDeletesClient(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    // Create real client
    $eventIdProvider = new TestEventIdProvider();
    $client = Client::register(
      id: new ClientId($clientId),
      name: new ClientName('Test Client'),
      secret: new ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://example.com')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ),
      eventIdProvider: $eventIdProvider,
    );
    $client->releaseEvents();

    // Mocks
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
    $command = new DeleteClientCommand(clientId: $clientId);

    // Handler
    $handler = new DeleteClientHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    // Execute
    $handler->__invoke($command);

    // Assert
    self::assertTrue($client->isDeleted());
    self::assertNotNull($client->deletedAt());
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

    $eventBus = $this->createMock(EventBusPort::class);

    $command = new DeleteClientCommand(clientId: $clientId);

    $handler = new DeleteClientHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    $this->expectException(InvalidClientException::class);
    $handler->__invoke($command);
  }
  // #endregion
}
