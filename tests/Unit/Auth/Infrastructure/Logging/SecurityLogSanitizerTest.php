<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Logging;

use Auth\Infrastructure\Logging\SecurityLogSanitizer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function hash;
use function hash_hmac;

#[CoversClass(SecurityLogSanitizer::class)]
final class SecurityLogSanitizerTest extends TestCase
{
  #[Test]
  public function testEmailIsRedactedWhenPiiIsDisabled(): void
  {
    $sanitizer = new SecurityLogSanitizer(piiSalt: 'salt-for-tests');

    self::assertNull($sanitizer->email('User@Example.com'));
  }

  #[Test]
  public function testEmailIsNormalizedWhenPiiIsEnabled(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true, piiSalt: 'salt-for-tests');

    self::assertSame('user@example.com', $sanitizer->email('  User@Example.com  '));
  }

  #[Test]
  public function testEmailReturnsNullForNullAndBlankValues(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true, piiSalt: 'salt-for-tests');

    self::assertNull($sanitizer->email(null));
    self::assertNull($sanitizer->email('   '));
  }

  #[Test]
  public function testIpIsRedactedWhenPiiIsDisabled(): void
  {
    $sanitizer = new SecurityLogSanitizer(piiSalt: 'salt-for-tests');

    self::assertNull($sanitizer->ip('203.0.113.7'));
  }

  #[Test]
  public function testIpIsNormalizedWhenPiiIsEnabled(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true, piiSalt: 'salt-for-tests');

    self::assertSame('203.0.113.7', $sanitizer->ip(' 203.0.113.7 '));
  }

  #[Test]
  public function testIpReturnsNullForNullAndBlankValues(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true, piiSalt: 'salt-for-tests');

    self::assertNull($sanitizer->ip(null));
    self::assertNull($sanitizer->ip(''));
  }

  #[Test]
  public function testRefusesToStartWithoutASalt(): void
  {
    // This test used to be `testHashesUsePlainSha256WithoutSalt` and asserted the
    // fallback by name. A bare sha256 of an email is not a privacy measure -- the
    // input space is a wordlist, so anyone holding the logs can reverse it. The
    // salt was blank in every env file in the repository, so that is what shipped.
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessageMatches('/SECURITY_LOG_PII_SALT is blank/');

    new SecurityLogSanitizer(piiSalt: '   ');
  }

  #[Test]
  public function testHashesNeverFallBackToABareDigest(): void
  {
    $sanitizer = new SecurityLogSanitizer(piiSalt: 'salt-for-tests');

    self::assertSame(
      hash_hmac('sha256', 'user@example.com', 'salt-for-tests'),
      $sanitizer->emailHash('User@Example.com'),
    );
    self::assertNotSame(hash('sha256', 'user@example.com'), $sanitizer->emailHash('User@Example.com'));
    self::assertNotSame(hash('sha256', '203.0.113.7'), $sanitizer->ipHash('203.0.113.7'));
  }

  #[Test]
  public function testHashesUseHmacWhenSaltIsConfigured(): void
  {
    $sanitizer = new SecurityLogSanitizer(piiSalt: 'pepper');

    self::assertSame(
      hash_hmac('sha256', 'user@example.com', 'pepper'),
      $sanitizer->emailHash('user@example.com'),
    );
    self::assertSame(
      hash_hmac('sha256', '203.0.113.7', 'pepper'),
      $sanitizer->ipHash('203.0.113.7'),
    );
  }

  #[Test]
  public function testHashesReturnNullForMissingValues(): void
  {
    $sanitizer = new SecurityLogSanitizer(piiSalt: 'salt-for-tests');

    self::assertNull($sanitizer->emailHash(null));
    self::assertNull($sanitizer->emailHash('  '));
    self::assertNull($sanitizer->ipHash(null));
  }
}
