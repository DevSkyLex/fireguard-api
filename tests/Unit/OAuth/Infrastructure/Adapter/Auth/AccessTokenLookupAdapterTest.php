<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Auth;

use Auth\Application\Contract\Token\AccessTokenStatus;
use DateTimeImmutable;
use OAuth\Application\Port\Outbound\Token\AccessTokenRepositoryPort;
use OAuth\Domain\Model\Token\AccessToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\Scopes;
use OAuth\Infrastructure\Adapter\Auth\AccessTokenLookupAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AccessTokenLookupAdapterTest.
 *
 * @category Adapter Tests
 */
#[CoversClass(className: AccessTokenLookupAdapter::class)]
final class AccessTokenLookupAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testFindReturnsNullWhenMissing(): void
  {
    $repository = $this->createMock(AccessTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('missing-token')
      ->willReturn(null);

    $adapter = new AccessTokenLookupAdapter($repository);

    self::assertNull($adapter->find('missing-token'));
  }

  #[Test]
  public function testFindReturnsAccessTokenStatus(): void
  {
    $token = new AccessToken(
      identifier: 'token-123',
      clientIdentifier: new OAuthClientIdentifier('client-123'),
      expiry: new DateTimeImmutable('+1 hour'),
      scopes: Scopes::fromArray(['OPENID', 'PROFILE']),
      userIdentifier: 'user-123',
      isRevoked: false,
    );

    $repository = $this->createMock(AccessTokenRepositoryPort::class);
    $repository->expects(self::once())
      ->method('find')
      ->with('token-123')
      ->willReturn($token);

    $adapter = new AccessTokenLookupAdapter($repository);

    $status = $adapter->find('token-123');

    self::assertInstanceOf(AccessTokenStatus::class, $status);
    self::assertSame(['OPENID', 'PROFILE'], $status->scopes);
    self::assertFalse($status->revoked);
    self::assertFalse($status->expired);
  }
  // #endregion
}
