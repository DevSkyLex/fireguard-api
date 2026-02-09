<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\ValueObject\Token;

use Auth\Domain\ValueObject\Token\TokenExpiry;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

use function time;

/**
 * Test TokenExpiryTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenExpiry::class)]
final class TokenExpiryTest extends TestCase
{
  // #region Methods
  /**
   * Method testFromTtlRejectsNonPositive.
   *
   * Tests that non-positive TTL throws an exception.
   */
  #[Test]
  public function testFromTtlRejectsNonPositive(): void
  {
    $this->expectException(InvalidValueException::class);

    TokenExpiry::fromTtl(0);
  }

  /**
   * Method testFromTtlCreatesFutureExpiry.
   *
   * Tests that fromTtl creates a future expiry.
   */
  #[Test]
  public function testFromTtlCreatesFutureExpiry(): void
  {
    $expiry = TokenExpiry::fromTtl(60);

    $this->assertFalse($expiry->isExpired());
    $this->assertGreaterThan(0, $expiry->remainingSeconds);
    $this->assertGreaterThanOrEqual(1, $expiry->expiresInMinutes);
    $this->assertTrue($expiry->willExpireWithin(120));
  }

  /**
   * Method testFromSecondsAliasesFromTtl.
   *
   * Tests that fromSeconds behaves like fromTtl.
   */
  #[Test]
  public function testFromSecondsAliasesFromTtl(): void
  {
    $expiry = TokenExpiry::fromSeconds(30);

    $this->assertGreaterThan(0, $expiry->remainingSeconds);
    $this->assertGreaterThan(0, $expiry->secondsRemaining());
  }

  /**
   * Method testFromTimestampCreatesExpiry.
   *
   * Tests that fromTimestamp sets the provided timestamp.
   */
  #[Test]
  public function testFromTimestampCreatesExpiry(): void
  {
    $timestamp = time() + 120;
    $expiry = TokenExpiry::fromTimestamp($timestamp);

    $this->assertSame($timestamp, $expiry->expiresAt->getTimestamp());
    $this->assertFalse($expiry->isExpired());
  }

  /**
   * Method testExtendReturnsNewExpiry.
   *
   * Tests that extend returns a new expiry instance.
   */
  #[Test]
  public function testExtendReturnsNewExpiry(): void
  {
    $initial = new DateTimeImmutable('+1 minute');
    $expiry = new TokenExpiry($initial);

    $extended = $expiry->extend(60);

    $this->assertNotSame($expiry, $extended);
    $this->assertGreaterThan($expiry->expiresAt->getTimestamp(), $extended->expiresAt->getTimestamp());
  }
  // #endregion
}
