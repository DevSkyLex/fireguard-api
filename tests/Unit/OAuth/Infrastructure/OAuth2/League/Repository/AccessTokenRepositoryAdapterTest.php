<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Repository;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\{ClientEntityInterface, ScopeEntityInterface};
use OAuth\Application\Port\Outbound\Token\AccessTokenRepositoryPort;
use OAuth\Domain\Model\Token\AccessToken as DomainAccessToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\OAuth2\League\Entity\AccessToken;
use OAuth\Infrastructure\OAuth2\League\Repository\AccessTokenRepositoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AccessTokenRepositoryAdapterTest.
 *
 * @category Repository Adapter Tests
 */
#[CoversClass(className: AccessTokenRepositoryAdapter::class)]
final class AccessTokenRepositoryAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetNewTokenBuildsAccessTokenEntity(): void
  {
    $client = new TestClient('client-123');
    $scope = new TestScope('OPENID');

    $adapter = new AccessTokenRepositoryAdapter(
      accessTokenRepository: $this->createMock(AccessTokenRepositoryPort::class),
    );

    $token = $adapter->getNewToken($client, [$scope], 'user-1');

    self::assertInstanceOf(AccessToken::class, $token);
    self::assertSame('client-123', $token->getClient()->getIdentifier());
    self::assertSame('user-1', $token->getUserIdentifier());
    self::assertCount(1, $token->getScopes());
  }

  #[Test]
  public function testPersistNewAccessTokenSavesDomainToken(): void
  {
    $client = new TestClient('client-123');
    $scope = new TestScope('OPENID');

    $token = new AccessToken();
    $token->setIdentifier('token-123');
    $token->setClient($client);
    $token->addScope($scope);
    $token->setUserIdentifier('user-1');
    $token->setExpiryDateTime(new DateTimeImmutable('+1 hour'));

    $repository = $this->createMock(AccessTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(function (DomainAccessToken $saved): bool {
        return 'token-123' === $saved->identifier()
          && 'client-123' === (string) $saved->clientIdentifier()
          && 'user-1' === $saved->userIdentifier();
      }));

    $adapter = new AccessTokenRepositoryAdapter(accessTokenRepository: $repository);

    $adapter->persistNewAccessToken($token);
  }

  #[Test]
  public function testRevokeAccessTokenHandlesMissingToken(): void
  {
    $repository = $this->createMock(AccessTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('missing-token')
      ->willReturn(null);
    $repository->expects(self::never())
      ->method('save');

    $adapter = new AccessTokenRepositoryAdapter(accessTokenRepository: $repository);

    $adapter->revokeAccessToken('missing-token');
  }

  #[Test]
  public function testIsAccessTokenRevokedUsesRepository(): void
  {
    $token = new DomainAccessToken(
      identifier: 'token-123',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      expiry: new DateTimeImmutable('+1 hour'),
      scopes: Scopes::fromArray(['OPENID']),
      userIdentifier: null,
      isRevoked: false,
    );

    $repository = $this->createMock(AccessTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn($token);

    $adapter = new AccessTokenRepositoryAdapter(accessTokenRepository: $repository);

    self::assertFalse($adapter->isAccessTokenRevoked('token-123'));
  }
  // #endregion
}

final class TestClient implements ClientEntityInterface
{
  public function __construct(private string $identifier)
  {
  }

  /**
   * @return non-empty-string
   */
  public function getIdentifier(): string
  {
    return $this->identifier ?: 'default-id';
  }

  public function getName(): string
  {
    return 'Test Client';
  }

  public function getRedirectUri(): string
  {
    return 'https://example.com/callback';
  }

  public function isConfidential(): bool
  {
    return true;
  }
}

final class TestScope implements ScopeEntityInterface
{
  public function __construct(private string $identifier)
  {
  }

  /**
   * @return non-empty-string
   */
  public function getIdentifier(): string
  {
    return $this->identifier ?: 'default-scope';
  }

  public function jsonSerialize(): string
  {
    return $this->identifier;
  }
}
