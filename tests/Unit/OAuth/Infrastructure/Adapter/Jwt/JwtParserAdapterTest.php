<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\Adapter\Jwt;

use DateTimeImmutable;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use OAuth\Infrastructure\Adapter\Jwt\JwtParserAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function getcwd;

use const DIRECTORY_SEPARATOR;

/**
 * Test JwtParserAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: JwtParserAdapter::class)]
final class JwtParserAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testParseReturnsClaims(): void
  {
    $token = $this->createToken();
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    $claims = $adapter->parse($token);

    self::assertIsArray($claims);
    self::assertSame('token-id', $claims['jti'] ?? null);
    self::assertSame('user-123', $claims['sub'] ?? null);
    self::assertSame('https://issuer.example', $claims['iss'] ?? null);
    self::assertSame('user@example.com', $claims['email'] ?? null);
    self::assertSame(['read'], $claims['scopes'] ?? null);
    self::assertIsInt($claims['exp'] ?? null);
  }

  #[Test]
  public function testValidateChecksToken(): void
  {
    $token = $this->createToken();
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertTrue($adapter->validate($token));
    self::assertFalse($adapter->validate(''));
  }

  #[Test]
  public function testGetTokenIdAndUserId(): void
  {
    $token = $this->createToken();
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertSame('token-id', $adapter->getTokenId($token));
    self::assertSame('user-123', $adapter->getUserId($token));
  }

  #[Test]
  public function testParseReturnsNullForEmptyToken(): void
  {
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertNull($adapter->parse(''));
  }

  #[Test]
  public function testParseReturnsNullForInvalidToken(): void
  {
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertNull($adapter->parse('not-a-token'));
  }

  #[Test]
  public function testValidateReturnsFalseWhenClaimsMissing(): void
  {
    $token = $this->createToken(includeIssuedAt: false, includeExpiresAt: false);
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertFalse($adapter->validate($token));
  }

  #[Test]
  public function testGetTokenIdAndUserIdReturnNullForInvalidToken(): void
  {
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertNull($adapter->getTokenId(''));
    self::assertNull($adapter->getUserId(''));
  }

  private function createToken(bool $includeIssuedAt = true, bool $includeExpiresAt = true): string
  {
    $config = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file($this->privateKeyPath()),
      verificationKey: InMemory::file($this->publicKeyPath()),
    );

    $now = new DateTimeImmutable();

    $builder = $config->builder()
      ->issuedBy('https://issuer.example')
      ->permittedFor('client-123')
      ->identifiedBy('token-id')
      ->relatedTo('user-123');

    if ($includeIssuedAt) {
      $builder = $builder->issuedAt($now)->canOnlyBeUsedAfter($now);
    }

    if ($includeExpiresAt) {
      $builder = $builder->expiresAt($now->modify('+1 hour'));
    }

    $builder = $builder
      ->withClaim('email', 'user@example.com')
      ->withClaim('scopes', ['read']);

    $token = $builder->getToken($config->signer(), $config->signingKey());

    return $token->toString();
  }

  /**
   * @return non-empty-string
   */
  private function publicKeyPath(): string
  {
    return getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'jwt' . DIRECTORY_SEPARATOR . 'public.key';
  }

  /**
   * @return non-empty-string
   */
  private function privateKeyPath(): string
  {
    return getcwd() . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'jwt' . DIRECTORY_SEPARATOR . 'private.key';
  }
  // #endregion
}
