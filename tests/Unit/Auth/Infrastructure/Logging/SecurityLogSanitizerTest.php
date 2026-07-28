<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Logging;

use Auth\Infrastructure\Logging\SecurityLogSanitizer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function hash;
use function hash_hmac;

#[CoversClass(SecurityLogSanitizer::class)]
final class SecurityLogSanitizerTest extends TestCase
{
  #[Test]
  public function testEmailIsRedactedWhenPiiIsDisabled(): void
  {
    $sanitizer = new SecurityLogSanitizer();

    self::assertNull($sanitizer->email('User@Example.com'));
  }

  #[Test]
  public function testEmailIsNormalizedWhenPiiIsEnabled(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true);

    self::assertSame('user@example.com', $sanitizer->email('  User@Example.com  '));
  }

  #[Test]
  public function testEmailReturnsNullForNullAndBlankValues(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true);

    self::assertNull($sanitizer->email(null));
    self::assertNull($sanitizer->email('   '));
  }

  #[Test]
  public function testIpIsRedactedWhenPiiIsDisabled(): void
  {
    $sanitizer = new SecurityLogSanitizer();

    self::assertNull($sanitizer->ip('203.0.113.7'));
  }

  #[Test]
  public function testIpIsNormalizedWhenPiiIsEnabled(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true);

    self::assertSame('203.0.113.7', $sanitizer->ip(' 203.0.113.7 '));
  }

  #[Test]
  public function testIpReturnsNullForNullAndBlankValues(): void
  {
    $sanitizer = new SecurityLogSanitizer(includePii: true);

    self::assertNull($sanitizer->ip(null));
    self::assertNull($sanitizer->ip(''));
  }

  #[Test]
  public function testHashesUsePlainSha256WithoutSalt(): void
  {
    $sanitizer = new SecurityLogSanitizer();

    self::assertSame(hash('sha256', 'user@example.com'), $sanitizer->emailHash('User@Example.com'));
    self::assertSame(hash('sha256', '203.0.113.7'), $sanitizer->ipHash('203.0.113.7'));
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
    $sanitizer = new SecurityLogSanitizer(piiSalt: '');

    self::assertNull($sanitizer->emailHash(null));
    self::assertNull($sanitizer->emailHash('  '));
    self::assertNull($sanitizer->ipHash(null));
  }
}
