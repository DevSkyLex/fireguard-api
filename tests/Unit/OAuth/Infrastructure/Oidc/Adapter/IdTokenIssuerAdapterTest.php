<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Oidc\Adapter;

use DateTimeImmutable;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;
use OAuth\Infrastructure\Oidc\Adapter\IdTokenIssuerAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function hash;

/**
 * Test IdTokenIssuerAdapterTest.
 *
 * @category OIDC Adapter Tests
 */
#[CoversClass(className: IdTokenIssuerAdapter::class)]
final class IdTokenIssuerAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testIssueIdTokenIncludesClaimsAndHeaders(): void
  {
    $adapter = new IdTokenIssuerAdapter(
      privateKeyPath: $this->getPrivateKeyPath(),
      publicKeyPath: $this->getPublicKeyPath(),
      issuer: 'https://issuer.example',
      defaultUri: null,
      accessTokenTtl: 3600,
      idTokenTtl: '900',
    );

    $jwt = $adapter->issueIdToken(
      subject: 'user-123',
      audience: 'client-123',
      nonce: 'nonce-456',
      claims: ['role' => 'admin', '' => 'ignored'],
    );

    $token = $this->parseToken($jwt);
    $claims = $token->claims();

    self::assertSame('https://issuer.example', $claims->get('iss'));
    self::assertSame(['client-123'], $claims->get('aud'));
    self::assertSame('user-123', $claims->get('sub'));
    self::assertSame('nonce-456', $claims->get('nonce'));
    self::assertSame('admin', $claims->get('role'));

    $issuedAt = $claims->get('iat');
    $expiresAt = $claims->get('exp');

    self::assertInstanceOf(DateTimeImmutable::class, $issuedAt);
    self::assertInstanceOf(DateTimeImmutable::class, $expiresAt);
    self::assertSame(900, $expiresAt->getTimestamp() - $issuedAt->getTimestamp());

    $publicKey = file_get_contents($this->getPublicKeyPath());
    self::assertIsString($publicKey);
    self::assertSame(hash('sha256', $publicKey), $token->headers()->get('kid'));
  }

  #[Test]
  public function testIssuerFallbackUsesDefaultUri(): void
  {
    $adapter = new IdTokenIssuerAdapter(
      privateKeyPath: $this->getPrivateKeyPath(),
      publicKeyPath: $this->getPublicKeyPath(),
      issuer: null,
      defaultUri: 'https://default.local',
      accessTokenTtl: 3600,
      idTokenTtl: null,
    );

    $jwt = $adapter->issueIdToken(
      subject: 'user-123',
      audience: 'client-123',
    );

    $token = $this->parseToken($jwt);

    self::assertSame('https://default.local', $token->claims()->get('iss'));
  }

  #[Test]
  public function testIssuerFallbackUsesLocalhostWhenMissing(): void
  {
    $adapter = new IdTokenIssuerAdapter(
      privateKeyPath: $this->getPrivateKeyPath(),
      publicKeyPath: $this->getPublicKeyPath(),
      issuer: null,
      defaultUri: null,
      accessTokenTtl: 3600,
      idTokenTtl: null,
    );

    $jwt = $adapter->issueIdToken(
      subject: 'user-123',
      audience: 'client-123',
    );

    $token = $this->parseToken($jwt);

    self::assertSame('https://localhost', $token->claims()->get('iss'));
  }

  #[Test]
  public function testUsesAccessTokenTtlWhenIdTokenTtlInvalid(): void
  {
    $adapter = new IdTokenIssuerAdapter(
      privateKeyPath: $this->getPrivateKeyPath(),
      publicKeyPath: $this->getPublicKeyPath(),
      issuer: 'https://issuer.example',
      defaultUri: null,
      accessTokenTtl: 120,
      idTokenTtl: '0',
    );

    $jwt = $adapter->issueIdToken(
      subject: 'user-123',
      audience: 'client-123',
    );

    $token = $this->parseToken($jwt);
    $issuedAt = $token->claims()->get('iat');
    $expiresAt = $token->claims()->get('exp');

    self::assertInstanceOf(DateTimeImmutable::class, $issuedAt);
    self::assertInstanceOf(DateTimeImmutable::class, $expiresAt);
    self::assertSame(120, $expiresAt->getTimestamp() - $issuedAt->getTimestamp());
  }
  // #endregion

  // #region Helpers
  /**
   * @phpstan-assert non-empty-string $jwt
   */
  private function parseToken(string $jwt): UnencryptedToken
  {
    self::assertNotEmpty($jwt);
    /** @var non-empty-string $jwt */
    $jwt = $jwt;

    $parser = new Parser(new JoseEncoder());
    $token = $parser->parse($jwt);

    self::assertInstanceOf(UnencryptedToken::class, $token);

    return $token;
  }

  private function getProjectDir(): string
  {
    return dirname(__DIR__, 6);
  }

  /**
   * @return non-empty-string
   */
  private function getPublicKeyPath(): string
  {
    return $this->getProjectDir() . '/config/jwt/public.key';
  }

  /**
   * @return non-empty-string
   */
  private function getPrivateKeyPath(): string
  {
    return $this->getProjectDir() . '/config/jwt/private.key';
  }
  // #endregion
}
