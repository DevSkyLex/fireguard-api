<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\Service;

use Audit\Infrastructure\Service\AuditPiiSanitizer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

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
    // This used to assert hash('sha256', ...) — it encoded the unsalted fallback as
    // the contract, which is how a reversible digest passed for a privacy measure
    // for as long as it did. The value is normalized and trimmed before hashing;
    // that part was always right.
    $sanitizer = new AuditPiiSanitizer(includePii: false, piiSalt: 'salt-for-tests');

    self::assertNull($sanitizer->email(' User@Example.com '));
    self::assertSame(
      hash_hmac('sha256', 'user@example.com', 'salt-for-tests'),
      $sanitizer->emailHash(' User@Example.com '),
    );

    self::assertNull($sanitizer->ip(' 127.0.0.1 '));
    self::assertSame(
      hash_hmac('sha256', '127.0.0.1', 'salt-for-tests'),
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

  #[Test]
  public function testRefusesToStartWithoutASalt(): void
  {
    // The whole point of the change: a blank salt used to fall through to a bare
    // sha256, which is not a privacy measure. The input space is a wordlist of
    // email addresses, so the digest is reversible by anyone holding the events.
    // It was blank in every env file in the repository, so that is what shipped.
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessageMatches('/SECURITY_LOG_PII_SALT is blank/');

    new AuditPiiSanitizer(includePii: false, piiSalt: '   ');
  }

  #[Test]
  public function testHashesWithAnHmacKeyedOnTheSaltAndNeverABareDigest(): void
  {
    $sanitizer = new AuditPiiSanitizer(includePii: false, piiSalt: 'pepper');

    self::assertSame(
      hash_hmac('sha256', 'user@example.com', 'pepper'),
      $sanitizer->emailHash('user@example.com'),
    );
    self::assertNotSame(
      hash('sha256', 'user@example.com'),
      $sanitizer->emailHash('user@example.com'),
    );
  }

  #[Test]
  public function testTwoSaltsDisagreeOnTheSameValue(): void
  {
    // If they agreed, the salt would not be keying anything.
    self::assertNotSame(
      new AuditPiiSanitizer(piiSalt: 'one')->emailHash('user@example.com'),
      new AuditPiiSanitizer(piiSalt: 'two')->emailHash('user@example.com'),
    );
  }
  // #endregion
}
