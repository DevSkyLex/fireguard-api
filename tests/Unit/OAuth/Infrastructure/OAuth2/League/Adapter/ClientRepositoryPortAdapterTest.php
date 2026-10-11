<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Adapter;

use Auth\Application\Contract\Client\ClientRegistration as Client;
use OAuth\Application\UseCase\Query\Client\GetClient\GetClientResult;
use OAuth\Infrastructure\OAuth2\League\Adapter\ClientRepositoryPortAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Test ClientRepositoryPortAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: ClientRepositoryPortAdapter::class)]
final class ClientRepositoryPortAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testFindReturnsClientWhenActive(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetClientResult(
        id: 'client-123',
        name: 'Client App',
        redirectUris: ['https://example.com/callback'],
        grantTypes: ['AUTHORIZATION_CODE'],
        scopes: ['READ'],
        isActive: true,
        createdAt: '2024-01-01T00:00:00Z',
      ));

    $adapter = new ClientRepositoryPortAdapter($queryBus);

    $client = $adapter->find('client-123');

    self::assertInstanceOf(Client::class, $client);
    self::assertSame('client-123', $client->identifier);
    self::assertSame('Client App', $client->name);
    self::assertSame(['https://example.com/callback'], $client->redirectUris);
    self::assertSame(['AUTHORIZATION_CODE'], $client->grantTypes);
    self::assertSame(['READ'], $client->scopes);
  }

  #[Test]
  public function testFindReturnsNullWhenInactive(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetClientResult(
        id: 'client-123',
        name: 'Client App',
        redirectUris: ['https://example.com/callback'],
        grantTypes: ['AUTHORIZATION_CODE'],
        scopes: ['READ'],
        isActive: false,
        createdAt: '2024-01-01T00:00:00Z',
      ));

    $adapter = new ClientRepositoryPortAdapter($queryBus);

    self::assertNull($adapter->find('client-123'));
  }

  #[Test]
  public function testFindReturnsNullOnException(): void
  {
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new ClientRepositoryPortAdapter($queryBus);

    self::assertNull($adapter->find('client-123'));
  }
  // #endregion
}
