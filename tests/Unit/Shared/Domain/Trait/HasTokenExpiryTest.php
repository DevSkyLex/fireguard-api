<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Trait;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversTrait, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\Trait\HasTokenExpiry;

use function time;

/**
 * Class HasTokenExpiryTest
 *
 * Pins reuse by another domain type without changing factory identity or absolute deadlines.
 *
 * @category Unit Test
 */
#[CoversTrait(HasTokenExpiry::class)]
final class HasTokenExpiryTest extends TestCase
{
  // #region Methods
  /**
   * Method factoriesRetainTheConsumingClass
   *
   * @access public
   *
   * @param 'ttl'|'seconds'|'timestamp' $factory published creation path
   *
   * @return void
   */
  #[Test]
  #[DataProvider('factories')]
  public function factoriesRetainTheConsumingClass(string $factory): void
  {
    $consumer = new class (new DateTimeImmutable()) {
      use HasTokenExpiry;
    };
    $before = time();
    $expiry = match ($factory) {
      'ttl' => $consumer::fromTtl(90),
      'seconds' => $consumer::fromSeconds(90),
      'timestamp' => $consumer::fromTimestamp($before + 90),
    };
    $after = time();

    self::assertSame($consumer::class, $expiry::class);
    self::assertGreaterThanOrEqual($before + 90, $expiry->expiresAt->getTimestamp());
    self::assertLessThanOrEqual($after + 90, $expiry->expiresAt->getTimestamp());
    self::assertFalse($expiry->isExpired);
    self::assertFalse($expiry->isExpired());
    self::assertSame(2, $expiry->expiresInMinutes);
    self::assertTrue($expiry->willExpireWithin(90));
    self::assertFalse($expiry->willExpireWithin(0));
  }

  /**
   * Method nonPositiveTtlKeepsItsOriginalError
   *
   * @access public
   *
   * @param int $ttl non-positive duration
   * @param bool $alias whether creation uses the seconds alias
   *
   * @return void
   */
  #[Test]
  #[DataProvider('nonPositiveDurations')]
  public function nonPositiveTtlKeepsItsOriginalError(int $ttl, bool $alias): void
  {
    $consumer = new class (new DateTimeImmutable()) {
      use HasTokenExpiry;
    };
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('TTL must be positive.');

    if ($alias) {
      $consumer::fromSeconds($ttl);
    } else {
      $consumer::fromTtl($ttl);
    }
  }

  /**
   * Method expiredAbsoluteTimestampsClampRemainingTime
   *
   * @access public
   *
   * @param int $timestamp original Unix deadline, including the epoch and pre-epoch values
   *
   * @return void
   */
  #[Test]
  #[DataProvider('pastTimestamps')]
  public function expiredAbsoluteTimestampsClampRemainingTime(int $timestamp): void
  {
    $consumer = new class (new DateTimeImmutable()) {
      use HasTokenExpiry;
    };
    $expiry = $consumer::fromTimestamp($timestamp);

    self::assertSame($consumer::class, $expiry::class);
    self::assertSame($timestamp, $expiry->expiresAt->getTimestamp());
    self::assertTrue($expiry->isExpired);
    self::assertTrue($expiry->isExpired());
    self::assertSame(0, $expiry->remainingSeconds);
    self::assertSame(0, $expiry->secondsRemaining());
    self::assertSame(0, $expiry->expiresInMinutes);
    self::assertTrue($expiry->willExpireWithin(0));
    self::assertFalse($expiry->willExpireWithin(-1));
  }

  /**
   * Method extensionRemainsAnchoredToTheOriginalDeadline
   *
   * @access public
   *
   * @param int $seconds extension applied to the retained deadline
   * @param string $expected expected wall time in the original fixed-offset timezone
   *
   * @return void
   */
  #[Test]
  #[DataProvider('extensions')]
  public function extensionRemainsAnchoredToTheOriginalDeadline(int $seconds, string $expected): void
  {
    $deadline = new DateTimeImmutable('2020-03-04T12:34:56+05:45');
    $expiry = new class ($deadline) {
      use HasTokenExpiry;
    };
    $extended = $expiry->extend($seconds);

    self::assertSame($expiry::class, $extended::class);
    self::assertNotSame($expiry, $extended);
    self::assertSame($deadline, $expiry->expiresAt);
    self::assertSame('2020-03-04T12:34:56+05:45', $expiry->expiresAt->format('c'));
    self::assertSame($expected, $extended->expiresAt->format('c'));
    self::assertSame('+05:45', $extended->expiresAt->getTimezone()->getName());
    self::assertTrue($extended->isExpired);
    self::assertSame(0, $extended->remainingSeconds);
  }

  /**
   * Method factories
   *
   * @access public
   *
   * @return iterable<string, array{'ttl'|'seconds'|'timestamp'}> all published factory paths
   */
  public static function factories(): iterable
  {
    yield 'positive TTL' => ['ttl'];
    yield 'seconds alias' => ['seconds'];
    yield 'absolute Unix timestamp' => ['timestamp'];
  }

  /**
   * Method nonPositiveDurations
   *
   * @access public
   *
   * @return iterable<string, array{int, bool}> zero and negative TTL through both factory names
   */
  public static function nonPositiveDurations(): iterable
  {
    yield 'zero TTL' => [0, false];
    yield 'negative TTL' => [-1, false];
    yield 'zero alias' => [0, true];
    yield 'negative alias' => [-1, true];
  }

  /**
   * Method pastTimestamps
   *
   * @access public
   *
   * @return iterable<string, array{int}> absolute deadlines retained without TTL validation
   */
  public static function pastTimestamps(): iterable
  {
    yield 'pre-epoch' => [-1];
    yield 'epoch' => [0];
    yield 'after epoch' => [1];
  }

  /**
   * Method extensions
   *
   * @access public
   *
   * @return iterable<string, array{int, string}> shorter, unchanged and longer absolute deadlines
   */
  public static function extensions(): iterable
  {
    yield 'shorten' => [-3600, '2020-03-04T11:34:56+05:45'];
    yield 'unchanged deadline in a new value' => [0, '2020-03-04T12:34:56+05:45'];
    yield 'extend' => [3600, '2020-03-04T13:34:56+05:45'];
  }
  // #endregion
}
