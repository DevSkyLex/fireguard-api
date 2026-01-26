<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Model\Token;

use DateTimeImmutable;
use OAuth\Domain\Model\Token\AuthCode;
use OAuth\Domain\ValueObject\Client\OAuthClientIdentifier;
use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuthCodeTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuthCode::class)]
final class AuthCodeTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testRevokeAndAccessors(): void
  {
    $expiry = new DateTimeImmutable('+5 minutes');
    $clientId = new OAuthClientIdentifier('client-1');
    $scopes = new Scopes(Scope::READ);
    $code = new AuthCode(
      identifier: 'code-1',
      expiryDateTime: $expiry,
      clientIdentifier: $clientId,
      userIdentifier: 'user-1',
      scopes: $scopes,
      redirectUri: 'https://example.com/callback',
      nonce: 'nonce-1',
    );

    self::assertSame('code-1', $code->identifier());
    self::assertSame($expiry, $code->expiryDateTime());
    self::assertSame($clientId, $code->clientIdentifier());
    self::assertSame('user-1', $code->userIdentifier());
    self::assertSame($scopes, $code->scopes());
    self::assertSame('https://example.com/callback', $code->redirectUri());
    self::assertSame('nonce-1', $code->nonce());
    self::assertFalse($code->isRevoked());

    $code->revoke();
    self::assertTrue($code->isRevoked());
  }
  // #endregion
}
