<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Model\Token;

use DateTimeImmutable;
use OAuth\Domain\Model\Token\RefreshToken;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RefreshTokenTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RefreshToken::class)]
final class RefreshTokenTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testRevokeAndExpiration(): void
  {
    $token = new RefreshToken(
      identifier: 'refresh-1',
      expiryDateTime: new DateTimeImmutable('+1 hour'),
      accessTokenIdentifier: 'access-1',
      clientIdentifier: new OAuthClientIdentifier('client-1'),
    );

    self::assertFalse($token->isRevoked());
    self::assertFalse($token->isExpired());

    $token->revoke();
    self::assertTrue($token->isRevoked());
  }

  #[Test]
  public function testIsExpiredWhenPast(): void
  {
    $token = new RefreshToken(
      identifier: 'refresh-2',
      expiryDateTime: new DateTimeImmutable('-1 hour'),
      accessTokenIdentifier: 'access-2',
      clientIdentifier: new OAuthClientIdentifier('client-2'),
    );

    self::assertTrue($token->isExpired());
  }

  #[Test]
  public function testAccessorsReturnValues(): void
  {
    $expiry = new DateTimeImmutable('+2 hours');
    $clientIdentifier = new OAuthClientIdentifier('client-3');

    $token = new RefreshToken(
      identifier: 'refresh-3',
      expiryDateTime: $expiry,
      accessTokenIdentifier: 'access-3',
      clientIdentifier: $clientIdentifier,
    );

    self::assertSame('refresh-3', $token->identifier());
    self::assertSame($expiry, $token->expiryDateTime());
    self::assertSame('access-3', $token->accessTokenIdentifier());
    self::assertSame('client-3', $token->clientIdentifier()->value);
  }
  // #endregion
}
