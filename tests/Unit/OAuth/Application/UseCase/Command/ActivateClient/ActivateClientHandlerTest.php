<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\ActivateClient;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Application\UseCase\Command\ActivateClient\{
  ActivateClientCommand,
  ActivateClientHandler
};
use OAuth\Domain\Exception\InvalidClientException;
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\{
  ClientId,
  ClientName,
  ClientSecret
};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\EventBusPort;
use OAuth\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes
};
use Tests\Helper\TestEventIdProvider;

/**
 * Test ActivateClientHandlerTest
 * @final
 *
 * Test class for ActivateClientHandler.
 *
 * @category Handler Tests
 * @package Tests\Client\Application\UseCase\Command\ActivateClient
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ActivateClientHandler::class)]
final class ActivateClientHandlerTest extends TestCase
{
  //#region Methods
  /**
   * Method testInvokeActivatesClient
   *
   * Test that __invoke activates client
   * successfully
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeActivatesClient(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    // Create real client and deactivate it first
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
    $client->deactivate($eventIdProvider);
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
    $command = new ActivateClientCommand(clientId: $clientId);

    // Handler
    $eventIdProvider2 = new TestEventIdProvider();
    $handler = new ActivateClientHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: $eventIdProvider2,
    );

    // Execute
    $handler->__invoke($command);

    // Assert
    self::assertTrue($client->isActive());
  }

  /**
   * Method testInvokeThrowsExceptionWhenClientNotFound
   *
   * Test that __invoke throws exception
   * when client is not found
   *
   * @access public
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

    $command = new ActivateClientCommand(clientId: $clientId);

    $handler = new ActivateClientHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    $this->expectException(InvalidClientException::class);
    $handler->__invoke($command);
  }
  //#endregion
}


