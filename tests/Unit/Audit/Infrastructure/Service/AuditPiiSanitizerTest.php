<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\Service;

use Audit\Infrastructure\Service\AuditPiiSanitizer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function hash;
use function hash_hmac;

/**
 * Test AuditPiiSanitizerTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuditPiiSanitizer::class)]
final class AuditPiiSanitizerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testHashesWhenPiiIsExcluded(): void
  {
    $sanitizer = new AuditPiiSanitizer(includePii: false, piiSalt: null);

    self::assertNull($sanitizer->email(' User@Example.com '));
    self::assertSame(
      hash('sha256', 'user@example.com'),
      $sanitizer->emailHash(' User@Example.com '),
    );

    self::assertNull($sanitizer->ip(' 127.0.0.1 '));
    self::assertSame(
      hash('sha256', '127.0.0.1'),
      $sanitizer->ipHash(' 127.0.0.1 '),
    );
  }

  #[Test]
  public function testReturnsPiiWhenEnabledAndUsesSaltedHash(): void
  {
    $sanitizer = new AuditPiiSanitizer(includePii: true, piiSalt: 'pepper');

    self::assertSame('user@example.com', $sanitizer->email(' User@Example.com '));
    self::assertSame(
      hash_hmac('sha256', 'user@example.com', 'pepper'),
      $sanitizer->emailHash(' User@Example.com '),
    );

    self::assertSame('203.0.113.5', $sanitizer->ip(' 203.0.113.5 '));
    self::assertSame(
      hash_hmac('sha256', '203.0.113.5', 'pepper'),
      $sanitizer->ipHash(' 203.0.113.5 '),
    );
  }

  #[Test]
  public function testReturnsNullForAMissingValue(): void
  {
    $sanitizer = new AuditPiiSanitizer(includePii: true, piiSalt: 'pepper');

    self::assertNull($sanitizer->email(null));
    self::assertNull($sanitizer->emailHash(null));
    self::assertNull($sanitizer->ip(null));
    self::assertNull($sanitizer->ipHash(null));
  }

  #[Test]
  public function testTreatsABlankValueAsMissing(): void
  {
    // Whitespace-only never becomes a hash: an empty-string digest would be a
    // stable, correlatable token for "no value", which is exactly what the
    // hashing is meant to prevent.
    $sanitizer = new AuditPiiSanitizer(includePii: true, piiSalt: 'pepper');

    self::assertNull($sanitizer->email('   '));
    self::assertNull($sanitizer->emailHash('   '));
    self::assertNull($sanitizer->ip("\t\n"));
    self::assertNull($sanitizer->ipHash("\t\n"));
  }
  // #endregion
}
