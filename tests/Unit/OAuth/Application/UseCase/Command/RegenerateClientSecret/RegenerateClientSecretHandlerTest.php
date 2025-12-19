<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\UseCase\Command\RegenerateClientSecret;

use OAuth\Application\Port\Outbound\ClientRepositoryPort;
use OAuth\Application\UseCase\Command\RegenerateClientSecret\{
    RegenerateClientSecretCommand,
    RegenerateClientSecretHandler,
    RegenerateClientSecretResult
};
use OAuth\Domain\Exception\InvalidClientException;
use OAuth\Domain\Model\Client;
use OAuth\Domain\ValueObject\{
    GrantType,
    GrantTypes,
    RedirectUri,
    Scope,
    Scopes
};
use OAuth\Domain\ValueObject\{ClientId, ClientName, ClientSecret};
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{
    EventBusPort,
    HashingPort
};
use Shared\Domain\ValueObject\HashedSecret;
use Tests\Helper\TestEventIdProvider;

use function password_hash;
use function strlen;

/**
 * Test RegenerateClientSecretHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: RegenerateClientSecretHandler::class)]
final class RegenerateClientSecretHandlerTest extends TestCase
{
    // #region Methods
    /**
     * Method testInvokeRegeneratesClientSecret.
     *
     * Test that __invoke regenerates client
     * secret successfully
     *
     * @return void No return value
     */
    #[Test]
    public function testInvokeRegeneratesClientSecret(): void
    {
        $clientId = '123e4567-e89b-12d3-a456-426614174000';
        $newHashedSecret = '$2y$10$newhashedsecret';

        // Create real client
        $eventIdProvider = new TestEventIdProvider();
        $client = Client::register(
            id: new ClientId($clientId),
            name: new ClientName('Test Client'),
            secret: new ClientSecret(password_hash('old_secret', PASSWORD_BCRYPT)),
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

        $hashing = $this->createMock(HashingPort::class);
        $hashing->expects(self::once())
          ->method('hash')
          ->willReturn(new HashedSecret($newHashedSecret));

        $eventBus = $this->createMock(EventBusPort::class);
        $eventBus->expects(self::once())
          ->method('publish');

        // Command
        $command = new RegenerateClientSecretCommand(clientId: $clientId);

        // Handler
        $handler = new RegenerateClientSecretHandler(
            clientRepository: $repository,
            hashing: $hashing,
            eventBus: $eventBus,
            eventIdProvider: new TestEventIdProvider(),
        );

        // Execute
        $result = $handler->__invoke($command);

        // Assert
        self::assertInstanceOf(expected: RegenerateClientSecretResult::class, actual: $result);
        self::assertSame(expected: $clientId, actual: $result->clientId);
        self::assertEquals(expected: 64, actual: strlen($result->clientSecret));

        // Verify client state
        self::assertEquals($newHashedSecret, $client->secret()->value);
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

        $hashing = $this->createMock(HashingPort::class);
        $eventBus = $this->createMock(EventBusPort::class);

        $command = new RegenerateClientSecretCommand(clientId: $clientId);

        $handler = new RegenerateClientSecretHandler(
            clientRepository: $repository,
            hashing: $hashing,
            eventBus: $eventBus,
            eventIdProvider: new TestEventIdProvider(),
        );

        $this->expectException(InvalidClientException::class);
        $handler->__invoke($command);
    }
    // #endregion
}
