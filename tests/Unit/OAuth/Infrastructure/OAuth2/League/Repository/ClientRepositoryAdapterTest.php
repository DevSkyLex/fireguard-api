<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Repository;

use OAuth\Application\Port\Outbound\Client\{ClientValidationPort, OAuthClientRepositoryPort};
use OAuth\Domain\Model\Client\OAuthClient;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scope;
use OAuth\Domain\ValueObject\Security\GrantType;
use OAuth\Infrastructure\OAuth2\League\Entity\Client as LeagueClient;
use OAuth\Infrastructure\OAuth2\League\Repository\ClientRepositoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ClientRepositoryAdapterTest.
 *
 * @category Repository Adapter Tests
 */
#[CoversClass(className: ClientRepositoryAdapter::class)]
final class ClientRepositoryAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetClientEntityReturnsMappedClient(): void
  {
    $client = new OAuthClient(
      identifier: new OAuthClientIdentifier('client-123'),
      name: 'Client App',
      redirectUris: ['https://example.com/callback'],
      grantTypes: [GrantType::AUTHORIZATION_CODE],
      scopes: [Scope::OPENID],
      secret: null,
      isConfidential: true,
    );

    $repository = $this->createMock(OAuthClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with(self::isInstanceOf(OAuthClientIdentifier::class))
      ->willReturn($client);

    $validation = $this->createMock(ClientValidationPort::class);

    $adapter = new ClientRepositoryAdapter(
      clientRepository: $repository,
      clientValidation: $validation,
    );

    $entity = $adapter->getClientEntity('client-123');

    self::assertInstanceOf(LeagueClient::class, $entity);
    self::assertSame('client-123', $entity->getIdentifier());
    self::assertSame('Client App', $entity->getName());
    self::assertSame(['https://example.com/callback'], (array) $entity->getRedirectUri());
  }

  #[Test]
  public function testGetClientEntityReturnsNullOnMissingClient(): void
  {
    $repository = $this->createMock(OAuthClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->willReturn(null);

    $adapter = new ClientRepositoryAdapter(
      clientRepository: $repository,
      clientValidation: $this->createMock(ClientValidationPort::class),
    );

    self::assertNull($adapter->getClientEntity('client-123'));
  }

  #[Test]
  public function testGetClientEntityReturnsNullOnException(): void
  {
    $repository = $this->createMock(OAuthClientRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->willThrowException(new RuntimeException('boom'));

    $adapter = new ClientRepositoryAdapter(
      clientRepository: $repository,
      clientValidation: $this->createMock(ClientValidationPort::class),
    );

    self::assertNull($adapter->getClientEntity('client-123'));
  }

  #[Test]
  public function testValidateClientDelegatesToValidationPort(): void
  {
    $validation = $this->createMock(ClientValidationPort::class);
    $validation->expects(self::once())
      ->method('validateCredentials')
      ->with('client-123', 'secret')
      ->willReturn(true);

    $adapter = new ClientRepositoryAdapter(
      clientRepository: $this->createMock(OAuthClientRepositoryPort::class),
      clientValidation: $validation,
    );

    self::assertTrue($adapter->validateClient('client-123', 'secret', 'authorization_code'));
  }
  // #endregion
}
