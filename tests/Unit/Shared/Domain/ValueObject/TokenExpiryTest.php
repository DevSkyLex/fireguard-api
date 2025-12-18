<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use DateTimeImmutable;
use Shared\Domain\Exception\InvalidValueException;
use OAuth\Domain\ValueObject\TokenExpiry;

/**
 * Class TokenExpiryTest
 *
 * Unit tests for the TokenExpiry Value Object with PHP 8.4 property hooks.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TokenExpiry::class)]
final class TokenExpiryTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanCreateWithExpiresAt
   *
   * Tests that a TokenExpiry can be created with a DateTimeImmutable.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testCanCreateWithExpiresAt(): void
  {
    $expiresAt = new DateTimeImmutable('+1 hour');
    $expiry = new TokenExpiry($expiresAt);

    $this->assertEquals(expected: $expiresAt, actual: $expiry->expiresAt);
  }

  /**
   * Method testFromTtlCreatesCorrectExpiry
   *
   * Tests creating a TokenExpiry from TTL in seconds.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFromTtlCreatesCorrectExpiry(): void
  {
    $ttl = 3600; // 1 hour
    $before = time();

    $expiry = TokenExpiry::fromTtl($ttl);

    $after = time();
    $expiryTimestamp = $expiry->expiresAt->getTimestamp();

    $this->assertGreaterThanOrEqual($before + $ttl, $expiryTimestamp);
    $this->assertLessThanOrEqual($after + $ttl, $expiryTimestamp);
  }

  /**
   * Method testFromTtlWithZeroThrowsException
   *
   * Tests that fromTtl throws an exception for zero TTL.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFromTtlWithZeroThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('TTL must be positive.');

    TokenExpiry::fromTtl(0);
  }

  /**
   * Method testFromTtlWithNegativeThrowsException
   *
   * Tests that fromTtl throws an exception for negative TTL.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFromTtlWithNegativeThrowsException(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('TTL must be positive.');

    TokenExpiry::fromTtl(-100);
  }

  /**
   * Method testFromTimestamp
   *
   * Tests creating a TokenExpiry from a Unix timestamp.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testFromTimestamp(): void
  {
    $timestamp = time() + 7200;
    $expiry = TokenExpiry::fromTimestamp($timestamp);

    $this->assertEquals(expected: $timestamp, actual: $expiry->expiresAt->getTimestamp());
  }

  /**
   * Method testIsExpiredPropertyHook
   *
   * Tests the isExpired property hook for non-expired token.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testIsExpiredPropertyHookForValidToken(): void
  {
    $expiry = TokenExpiry::fromTtl(3600);

    $this->assertFalse($expiry->isExpired);
  }

  /**
   * Method testIsExpiredPropertyHookForExpiredToken
   *
   * Tests the isExpired property hook for expired token.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testIsExpiredPropertyHookForExpiredToken(): void
  {
    $expiry = new TokenExpiry(new DateTimeImmutable('-1 hour'));

    $this->assertTrue($expiry->isExpired);
  }

  /**
   * Method testRemainingSecondsPropertyHook
   *
   * Tests the remainingSeconds property hook.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRemainingSecondsPropertyHook(): void
  {
    $expiry = TokenExpiry::fromTtl(3600);

    // Should be close to 3600, allowing for test execution time
    $this->assertGreaterThan(3590, $expiry->remainingSeconds);
    $this->assertLessThanOrEqual(3600, $expiry->remainingSeconds);
  }

  /**
   * Method testRemainingSecondsForExpiredToken
   *
   * Tests that remainingSeconds is 0 for expired tokens.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testRemainingSecondsForExpiredToken(): void
  {
    $expiry = new TokenExpiry(new DateTimeImmutable('-1 hour'));

    $this->assertEquals(expected: 0, actual: $expiry->remainingSeconds);
  }

  /**
   * Method testExpiresInMinutesPropertyHook
   *
   * Tests the expiresInMinutes property hook.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testExpiresInMinutesPropertyHook(): void
  {
    $expiry = TokenExpiry::fromTtl(3600); // 60 minutes

    $this->assertEquals(expected: 60, actual: $expiry->expiresInMinutes);
  }

  /**
   * Method testWillExpireWithin
   *
   * Tests the willExpireWithin method.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testWillExpireWithin(): void
  {
    $expiry = TokenExpiry::fromTtl(300); // 5 minutes

    $this->assertTrue($expiry->willExpireWithin(600)); // Within 10 minutes
    $this->assertFalse($expiry->willExpireWithin(60)); // Within 1 minute
  }

  /**
   * Method testExtend
   *
   * Tests extending the expiry time.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testExtend(): void
  {
    $original = TokenExpiry::fromTtl(3600);
    $extended = $original->extend(1800); // Add 30 minutes

    // Original should be unchanged
    $this->assertLessThan(
      $extended->expiresAt->getTimestamp(),
      $original->expiresAt->getTimestamp()
    );

    // Extended should be 30 minutes later
    $diff = $extended->expiresAt->getTimestamp() - $original->expiresAt->getTimestamp();
    $this->assertEquals(expected: 1800, actual: $diff);
  }
  //#endregion
}
