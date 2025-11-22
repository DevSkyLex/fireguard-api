<?php

declare(strict_types=1);

namespace Tests\Client\Application\UseCase\Command\ActivateClient;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Application\UseCase\Command\ActivateClient\{
  ActivateClientCommand,
  ActivateClientHandler
};
use Client\Domain\Exception\InvalidClientException;
use Client\Domain\Model\Client;
use Client\Domain\ValueObject\{
  ClientId,
  ClientName
};
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Application\Port\Outbound\EventBusPort;
use Shared\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes
};

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
    $client = Client::register(
      id: new ClientId($clientId),
      name: new ClientName('Test Client'),
      secret: new \Client\Domain\ValueObject\ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://example.com')],
      grantTypes: new GrantTypes(GrantType::from('authorization_code')),
      scopes: new Scopes(new Scope('read'))
    );
    $client->deactivate();
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
    $handler = new ActivateClientHandler(
      clientRepository: $repository,
      eventBus: $eventBus
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
      eventBus: $eventBus
    );

    $this->expectException(InvalidClientException::class);
    $handler->__invoke($command);
  }
  //#endregion
}


