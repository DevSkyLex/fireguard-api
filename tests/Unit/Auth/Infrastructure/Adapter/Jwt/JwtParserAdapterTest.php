<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Jwt;

use Auth\Infrastructure\Adapter\Jwt\JwtParserAdapter;
use DateTimeImmutable;
use DateTimeInterface;
use Lcobucci\JWT\{Configuration, Parser, Token};
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\DataSet;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

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
    self::assertIsInt($claims['iat'] ?? null);
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

    self::assertNull($adapter->parse('invalid-token'));
  }

  #[Test]
  public function testParseReturnsNullForNonUnencryptedToken(): void
  {
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());
    $this->replaceParser($adapter, $this->createNonUnencryptedParser());

    self::assertNull($adapter->parse('dummy-token'));
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
  public function testValidateReturnsFalseWhenClaimsMissing(): void
  {
    $config = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file($this->privateKeyPath()),
      verificationKey: InMemory::file($this->publicKeyPath()),
    );

    $token = $config->builder()
      ->issuedBy('https://issuer.example')
      ->getToken($config->signer(), $config->signingKey())
      ->toString();

    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertFalse($adapter->validate($token));
  }

  #[Test]
  public function testValidateReturnsFalseWhenParserThrows(): void
  {
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertFalse($adapter->validate('invalid-token'));
  }

  #[Test]
  public function testValidateReturnsFalseForNonUnencryptedToken(): void
  {
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());
    $this->replaceParser($adapter, $this->createNonUnencryptedParser());

    self::assertFalse($adapter->validate('dummy-token'));
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
  public function testGetTokenIdAndUserIdReturnNullOnParseFailure(): void
  {
    $adapter = new JwtParserAdapter(publicKeyPath: $this->publicKeyPath());

    self::assertNull($adapter->getTokenId('invalid-token'));
    self::assertNull($adapter->getUserId('invalid-token'));
  }

  private function createToken(): string
  {
    $config = Configuration::forAsymmetricSigner(
      signer: new Sha256(),
      signingKey: InMemory::file($this->privateKeyPath()),
      verificationKey: InMemory::file($this->publicKeyPath()),
    );

    $now = new DateTimeImmutable();

    $token = $config->builder()
      ->issuedBy('https://issuer.example')
      ->permittedFor('client-123')
      ->identifiedBy('token-id')
      ->relatedTo('user-123')
      ->issuedAt($now)
      ->canOnlyBeUsedAfter($now)
      ->expiresAt($now->modify('+1 hour'))
      ->withClaim('email', 'user@example.com')
      ->withClaim('scopes', ['read'])
      ->getToken($config->signer(), $config->signingKey());

    return $token->toString();
  }

  private function replaceParser(JwtParserAdapter $adapter, Parser $parser): void
  {
    $property = new ReflectionProperty(JwtParserAdapter::class, 'jwtConfig');
    $property->setAccessible(true);
    /** @var Configuration $config */
    $config = $property->getValue($adapter);
    $property->setValue($adapter, $config->withParser($parser));
  }

  private function createNonUnencryptedParser(): Parser
  {
    return new class () implements Parser {
      public function parse(string $jwt): Token
      {
        return new class () implements Token {
          public function headers(): DataSet
          {
            return new DataSet([], '');
          }

          public function isPermittedFor(string $audience): bool
          {
            return false;
          }

          public function isIdentifiedBy(string $id): bool
          {
            return false;
          }

          public function isRelatedTo(string $subject): bool
          {
            return false;
          }

          public function hasBeenIssuedBy(string ...$issuers): bool
          {
            return false;
          }

          public function hasBeenIssuedBefore(DateTimeInterface $now): bool
          {
            return false;
          }

          public function isMinimumTimeBefore(DateTimeInterface $now): bool
          {
            return false;
          }

          public function isExpired(DateTimeInterface $now): bool
          {
            return false;
          }

          public function toString(): string
          {
            return 'dummy';
          }
        };
      }
    };
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
