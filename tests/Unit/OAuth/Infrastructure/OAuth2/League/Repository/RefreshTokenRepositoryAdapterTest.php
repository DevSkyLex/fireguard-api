<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Repository;

use DateTimeImmutable;
use OAuth\Application\Port\Outbound\Token\RefreshTokenRepositoryPort;
use OAuth\Domain\Model\Token\RefreshToken as DomainRefreshToken;
use OAuth\Infrastructure\OAuth2\League\Entity\{AccessToken as LeagueAccessToken, Client as LeagueClient, RefreshToken as LeagueRefreshToken};
use OAuth\Infrastructure\OAuth2\League\Repository\RefreshTokenRepositoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RefreshTokenRepositoryAdapterTest.
 *
 * @category Repository Adapter Tests
 */
#[CoversClass(className: RefreshTokenRepositoryAdapter::class)]
final class RefreshTokenRepositoryAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetNewRefreshTokenReturnsEntity(): void
  {
    $adapter = new RefreshTokenRepositoryAdapter(
      refreshTokenRepository: $this->createMock(RefreshTokenRepositoryPort::class),
    );

    $entity = $adapter->getNewRefreshToken();

    self::assertInstanceOf(LeagueRefreshToken::class, $entity);
  }

  #[Test]
  public function testPersistNewRefreshTokenSavesDomainRefreshToken(): void
  {
    $client = new LeagueClient('client-123', 'Client App', 'https://example.com/callback');

    $accessToken = new LeagueAccessToken();
    $accessToken->setIdentifier('access-123');
    $accessToken->setClient($client);
    $accessToken->setExpiryDateTime(new DateTimeImmutable('+1 hour'));

    $refreshToken = new LeagueRefreshToken();
    $refreshToken->setIdentifier('refresh-123');
    $refreshToken->setAccessToken($accessToken);
    $refreshToken->setExpiryDateTime(new DateTimeImmutable('+2 hours'));

    $repository = $this->createMock(RefreshTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(function (DomainRefreshToken $saved): bool {
        return 'refresh-123' === $saved->identifier()
          && 'access-123' === $saved->accessTokenIdentifier()
          && 'client-123' === (string) $saved->clientIdentifier();
      }));

    $adapter = new RefreshTokenRepositoryAdapter(refreshTokenRepository: $repository);

    $adapter->persistNewRefreshToken($refreshToken);
  }

  #[Test]
  public function testRevokeRefreshTokenMarksRevoked(): void
  {
    $token = new DomainRefreshToken(
      identifier: 'refresh-123',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      accessTokenIdentifier: 'access-123',
      clientIdentifier: new \OAuth\Domain\ValueObject\Client\OAuthClientIdentifier('client-123'),
    );

    $repository = $this->createMock(RefreshTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('refresh-123')
      ->willReturn($token);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(fn (DomainRefreshToken $saved): bool => $saved->isRevoked()));

    $adapter = new RefreshTokenRepositoryAdapter(refreshTokenRepository: $repository);

    $adapter->revokeRefreshToken('refresh-123');
  }

  #[Test]
  public function testIsRefreshTokenRevokedReturnsTrueWhenMissing(): void
  {
    $repository = $this->createMock(RefreshTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('missing-token')
      ->willReturn(null);

    $adapter = new RefreshTokenRepositoryAdapter(refreshTokenRepository: $repository);

    self::assertTrue($adapter->isRefreshTokenRevoked('missing-token'));
  }

  #[Test]
  public function testIsRefreshTokenRevokedReturnsFalseWhenActive(): void
  {
    $token = new DomainRefreshToken(
      identifier: 'refresh-123',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      accessTokenIdentifier: 'access-123',
      clientIdentifier: new \OAuth\Domain\ValueObject\Client\OAuthClientIdentifier('client-123'),
    );

    $repository = $this->createMock(RefreshTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('refresh-123')
      ->willReturn($token);

    $adapter = new RefreshTokenRepositoryAdapter(refreshTokenRepository: $repository);

    self::assertFalse($adapter->isRefreshTokenRevoked('refresh-123'));
  }
  // #endregion
}
