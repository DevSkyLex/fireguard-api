<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Domain\ValueObject;

use DateTimeImmutable;
use Maintenance\Domain\ValueObject\PeriodicityInterval;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PeriodicityIntervalTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PeriodicityInterval::class)]
final class PeriodicityIntervalTest extends TestCase
{
  #[Test]
  public function testFromStringAcceptsValueWithinBounds(): void
  {
    $interval = PeriodicityInterval::fromString('P90D');

    self::assertSame('P90D', $interval->value);
  }

  #[Test]
  public function testFromStringRejectsMalformedDuration(): void
  {
    $this->expectException(InvalidValueException::class);

    PeriodicityInterval::fromString('not-a-duration');
  }

  #[Test]
  public function testFromStringRejectsDurationBelowOneMonth(): void
  {
    $this->expectException(InvalidValueException::class);

    PeriodicityInterval::fromString('P1D');
  }

  #[Test]
  public function testFromStringRejectsDurationAboveTenYears(): void
  {
    $this->expectException(InvalidValueException::class);

    PeriodicityInterval::fromString('P11Y');
  }

  #[Test]
  public function testFromStringAcceptsLowerBound(): void
  {
    $interval = PeriodicityInterval::fromString('P28D');

    self::assertSame('P28D', $interval->value);
  }

  #[Test]
  public function testFromStringAcceptsUpperBound(): void
  {
    $interval = PeriodicityInterval::fromString('P10Y');

    self::assertSame('P10Y', $interval->value);
  }

  #[Test]
  public function testAddToAddsTheDurationToAReferenceDate(): void
  {
    $interval = PeriodicityInterval::fromString('P90D');
    $reference = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $result = $interval->addTo($reference);

    self::assertSame('2026-04-01T00:00:00+00:00', $result->format('c'));
  }
}
