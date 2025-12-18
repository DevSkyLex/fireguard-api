<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\DeactivateClient;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Application\UseCase\Command\DeactivateClient\{
  DeactivateClientCommand,
  DeactivateClientHandler
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
 * Test DeactivateClientHandlerTest
 * @final
 *
 * Test class for DeactivateClientHandler.
 *
 * @category Handler Tests
 * @package Tests\Client\Application\UseCase\Command\DeactivateClient
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DeactivateClientHandler::class)]
final class DeactivateClientHandlerTest extends TestCase
{
  //#region Methods
  /**
   * Method testInvokeDeactivatesClient
   *
   * Test that __invoke deactivates client
   * successfully
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeDeactivatesClient(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    // Create real client (active by default)
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
    $command = new DeactivateClientCommand(clientId: $clientId);

    // Handler
    $handler = new DeactivateClientHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    // Execute
    $handler->__invoke($command);

    // Assert
    self::assertFalse($client->isActive());
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

    $command = new DeactivateClientCommand(clientId: $clientId);

    $handler = new DeactivateClientHandler(
      clientRepository: $repository,
      eventBus: $eventBus,
      eventIdProvider: new TestEventIdProvider(),
    );

    $this->expectException(InvalidClientException::class);
    $handler->__invoke($command);
  }
  //#endregion
}

