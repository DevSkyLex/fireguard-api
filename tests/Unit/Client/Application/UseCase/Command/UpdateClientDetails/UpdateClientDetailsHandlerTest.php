<?php

declare(strict_types=1);

namespace Tests\Client\Application\UseCase\Command\UpdateClientDetails;

use Client\Application\Port\Outbound\ClientRepositoryPort;
use Client\Application\UseCase\Command\UpdateClientDetails\{
  UpdateClientDetailsCommand,
  UpdateClientDetailsHandler
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
  RedirectUri,
  Scope,
  Scopes
};

/**
 * Test UpdateClientDetailsHandlerTest
 * @final
 *
 * Test class for UpdateClientDetailsHandler.
 *
 * @category Handler Tests
 * @package Tests\Client\Application\UseCase\Command\UpdateClientDetails
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: UpdateClientDetailsHandler::class)]
final class UpdateClientDetailsHandlerTest extends TestCase
{
  //#region Methods
  /**
   * Method testInvokeUpdatesClientDetails
   *
   * Test that __invoke updates client
   * details successfully
   *
   * @access public
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeUpdatesClientDetails(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';

    // Create real client
    $client = Client::register(
      id: new ClientId($clientId),
      name: new ClientName('Original Name'),
      secret: new \Client\Domain\ValueObject\ClientSecret(password_hash('secret', PASSWORD_BCRYPT)),
      redirectUris: [new RedirectUri('https://old.example.com')],
      grantTypes: new \Shared\Domain\ValueObject\GrantTypes(\Shared\Domain\ValueObject\GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(new Scope('read'))
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
      scopes: new Scopes(new Scope('write'))
    );

    // Handler
    $handler = new UpdateClientDetailsHandler(
      clientRepository: $repository,
      eventBus: $eventBus
    );

    // Execute
    $handler->__invoke($command);

    // Verify state change
    self::assertEquals('Updated Client', $client->name()->value);
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

    $command = new UpdateClientDetailsCommand(
      clientId: $clientId,
      name: 'Updated Client',
      redirectUris: [],
      scopes: new Scopes(new Scope('read'))
    );

    $handler = new UpdateClientDetailsHandler(
      clientRepository: $repository,
      eventBus: $eventBus
    );

    $this->expectException(InvalidClientException::class);
    $handler->__invoke($command);
  }
  //#endregion
}

