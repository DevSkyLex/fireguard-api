<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\OAuth2\League\Repository;

use DateTimeImmutable;
use OAuth\Application\Port\Outbound\Token\AuthCodeRepositoryPort;
use OAuth\Domain\Model\Token\AuthCode as DomainAuthCode;
use OAuth\Infrastructure\OAuth2\League\Entity\{AuthCode as LeagueAuthCode, Client as LeagueClient, Scope as LeagueScope};
use OAuth\Infrastructure\OAuth2\League\Repository\AuthCodeRepositoryAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuthCodeRepositoryAdapterTest.
 *
 * @category Repository Adapter Tests
 */
#[CoversClass(className: AuthCodeRepositoryAdapter::class)]
final class AuthCodeRepositoryAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetNewAuthCodeReturnsEntity(): void
  {
    $adapter = new AuthCodeRepositoryAdapter(
      authCodeRepository: $this->createMock(AuthCodeRepositoryPort::class),
    );

    $entity = $adapter->getNewAuthCode();

    self::assertInstanceOf(LeagueAuthCode::class, $entity);
  }

  #[Test]
  public function testPersistNewAuthCodeSavesDomainAuthCode(): void
  {
    $authCode = new LeagueAuthCode();
    $authCode->setIdentifier('code-123');
    $authCode->setExpiryDateTime(new DateTimeImmutable('+1 hour'));
    $authCode->setUserIdentifier('user-123');
    $authCode->setRedirectUri('https://example.com/callback');

    $client = new LeagueClient('client-123', 'Client App', 'https://example.com/callback');
    $authCode->setClient($client);

    $scope = new LeagueScope();
    $scope->setIdentifier('OPENID');
    $authCode->addScope($scope);

    $repository = $this->createMock(AuthCodeRepositoryPort::class);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(function (DomainAuthCode $saved): bool {
        return 'code-123' === $saved->identifier()
          && 'client-123' === (string) $saved->clientIdentifier()
          && 'user-123' === $saved->userIdentifier()
          && 'https://example.com/callback' === $saved->redirectUri()
          && ['OPENID'] === $saved->scopes()->toArray();
      }));

    $adapter = new AuthCodeRepositoryAdapter(authCodeRepository: $repository);

    $adapter->persistNewAuthCode($authCode);
  }

  #[Test]
  public function testRevokeAuthCodeMarksRevoked(): void
  {
    $code = new DomainAuthCode(
      identifier: 'code-123',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      clientIdentifier: new \OAuth\Domain\ValueObject\Client\OAuthClientIdentifier('client-123'),
      userIdentifier: 'user-123',
      scopes: \OAuth\Domain\ValueObject\Scope\Scopes::fromArray(['OPENID']),
      redirectUri: null,
    );

    $repository = $this->createMock(AuthCodeRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('code-123')
      ->willReturn($code);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(fn (DomainAuthCode $saved): bool => $saved->isRevoked()));

    $adapter = new AuthCodeRepositoryAdapter(authCodeRepository: $repository);

    $adapter->revokeAuthCode('code-123');
  }

  #[Test]
  public function testIsAuthCodeRevokedReturnsTrueWhenMissing(): void
  {
    $repository = $this->createMock(AuthCodeRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('missing-code')
      ->willReturn(null);

    $adapter = new AuthCodeRepositoryAdapter(authCodeRepository: $repository);

    self::assertTrue($adapter->isAuthCodeRevoked('missing-code'));
  }

  #[Test]
  public function testIsAuthCodeRevokedReturnsFalseWhenActive(): void
  {
    $code = new DomainAuthCode(
      identifier: 'code-123',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      clientIdentifier: new \OAuth\Domain\ValueObject\Client\OAuthClientIdentifier('client-123'),
      userIdentifier: 'user-123',
      scopes: \OAuth\Domain\ValueObject\Scope\Scopes::fromArray(['OPENID']),
      redirectUri: null,
    );

    $repository = $this->createMock(AuthCodeRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('code-123')
      ->willReturn($code);

    $adapter = new AuthCodeRepositoryAdapter(authCodeRepository: $repository);

    self::assertFalse($adapter->isAuthCodeRevoked('code-123'));
  }
  // #endregion
}
