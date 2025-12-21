<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\RegisterClient;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Application\UseCase\Command\RegisterClient\{
  RegisterClientCommand,
  RegisterClientHandler,
  RegisterClientResult
};
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\{
  GrantType,
  GrantTypes,
  RedirectUri,
  Scope,
  Scopes
};
use OAuth\Domain\ValueObject\ClientId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{
  EventBusPort,
  HashingPort
};
use Shared\Domain\ValueObject\HashedSecret;
use Tests\Helper\TestEventIdProvider;

use function strlen;

/**
 * Test RegisterClientHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RegisterClientHandler::class)]
final class RegisterClientHandlerTest extends TestCase
{
  // #region Methods
  /**
   * Method testInvokeRegistersNewClient.
   *
   * Test that __invoke registers a new
   * client successfully
   *
   * @return void No return value
   */
  #[Test]
  public function testInvokeRegistersNewClient(): void
  {
    $clientId = '123e4567-e89b-12d3-a456-426614174000';
    $plainSecret = 'generated-secret'; // This will be mocked by random_bytes in real execution, but here we check flow
    $hashedSecretValue = '$2y$10$hashedsecret';

    // Mocks
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(ClientId::class)
      ->willReturn(new ClientId($clientId));

    $eventIdProvider = new TestEventIdProvider();

    $hashingPort = $this->createMock(HashingPort::class);
    $hashingPort->expects(self::once())
      ->method('hash')
      ->willReturn(new HashedSecret(value: $hashedSecretValue));

    $repository = $this->createMock(ClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Client::class));

    $eventBus = $this->createMock(EventBusPort::class);
    $eventBus->expects(self::once())
      ->method('publish');

    // Command
    $command = new RegisterClientCommand(
      name: 'Test Client',
      redirectUris: [new RedirectUri(value: 'https://example.com/callback')],
      grantTypes: new GrantTypes(GrantType::AUTHORIZATION_CODE),
      scopes: new Scopes(Scope::READ)
    );

    // Handler
    $handler = new RegisterClientHandler(
      clientRepository: $repository,
      uuidFactory: $uuidFactory,
      hashing: $hashingPort,
      eventBus: $eventBus,
      eventIdProvider: $eventIdProvider,
    );

    // Execute
    $result = $handler->__invoke(command: $command);

    // Assert
    self::assertInstanceOf(expected: RegisterClientResult::class, actual: $result);
    self::assertSame(expected: $clientId, actual: $result->clientId);
    self::assertEquals(expected: 64, actual: strlen($result->clientSecret));
  }
  // #endregion
}
