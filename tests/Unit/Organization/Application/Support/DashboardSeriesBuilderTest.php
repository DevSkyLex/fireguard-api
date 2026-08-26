<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Support;

use DateTimeImmutable;
use DateTimeZone;
use Organization\Application\Support\DashboardSeriesBuilder;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test DashboardSeriesBuilder.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DashboardSeriesBuilder::class)]
final class DashboardSeriesBuilderTest extends TestCase
{
  #[Test]
  public function testResolveDashboardTimeZonePrefersTheExplicitFilter(): void
  {
    $zone = DashboardSeriesBuilder::resolveDashboardTimeZone(
      'Europe/Paris',
      null,
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );

    self::assertSame('Europe/Paris', $zone->getName());
  }

  #[Test]
  public function testResolveDashboardTimeZoneRejectsAnUnknownIdentifier(): void
  {
    $this->expectException(InvalidValueException::class);

    DashboardSeriesBuilder::resolveDashboardTimeZone(
      'Mars/Olympus',
      null,
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );
  }

  #[Test]
  public function testResolveDashboardTimeZoneRejectsMixedBoundaryOffsets(): void
  {
    $this->expectException(InvalidValueException::class);

    DashboardSeriesBuilder::resolveDashboardTimeZone(
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-05T00:00:00+02:00'),
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );
  }

  #[Test]
  public function testResolveDashboardTimeZoneNormalizesAZeroOffsetToUtc(): void
  {
    $zone = DashboardSeriesBuilder::resolveDashboardTimeZone(
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );

    self::assertSame('UTC', $zone->getName());
  }

  #[Test]
  public function testResolveDashboardTimeZoneRejectsANonUtcImplicitOffset(): void
  {
    $this->expectException(InvalidValueException::class);

    DashboardSeriesBuilder::resolveDashboardTimeZone(
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+02:00'),
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+02:00'),
    );
  }

  #[Test]
  public function testResolveDashboardTimeZoneKeepsAnIanaBoundaryZone(): void
  {
    $zone = DashboardSeriesBuilder::resolveDashboardTimeZone(
      null,
      new DateTimeImmutable('2026-03-01T00:00:00', new DateTimeZone('Europe/Paris')),
      null,
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );

    self::assertSame('Europe/Paris', $zone->getName());
  }

  #[Test]
  public function testResolvePeriodDefaultsToATrailingThirtyDayWindow(): void
  {
    [$start, $end] = DashboardSeriesBuilder::resolvePeriod(
      null,
      null,
      new DateTimeImmutable('2026-03-30T15:30:00+00:00'),
      new DateTimeZone('UTC'),
    );

    self::assertSame('2026-03-01 00:00:00', $start->format('Y-m-d H:i:s'));
    self::assertSame('2026-03-30 15:30:00', $end->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testResolvePeriodHonoursExplicitBoundsAndConvertsTheTimeZone(): void
  {
    [$start, $end] = DashboardSeriesBuilder::resolvePeriod(
      new DateTimeImmutable('2026-03-10T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-12T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-30T00:00:00+00:00'),
      new DateTimeZone('Europe/Paris'),
    );

    self::assertSame('Europe/Paris', $start->getTimezone()->getName());
    self::assertSame('2026-03-10 01:00:00', $start->format('Y-m-d H:i:s'));
    self::assertSame('2026-03-12 01:00:00', $end->format('Y-m-d H:i:s'));
  }

  #[Test]
  public function testAssertSupportedPeriodAcceptsAValidWindow(): void
  {
    DashboardSeriesBuilder::assertSupportedPeriod(
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-31T00:00:00+00:00'),
    );

    $this->expectNotToPerformAssertions();
  }

  #[Test]
  public function testAssertSupportedPeriodRejectsAnInvertedWindow(): void
  {
    $this->expectException(InvalidValueException::class);

    DashboardSeriesBuilder::assertSupportedPeriod(
      new DateTimeImmutable('2026-03-31T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
    );
  }

  #[Test]
  public function testAssertSupportedPeriodRejectsAWindowLongerThanTheMaximum(): void
  {
    $this->expectException(InvalidValueException::class);

    DashboardSeriesBuilder::assertSupportedPeriod(
      new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-12-31T00:00:00+00:00'),
    );
  }

  #[Test]
  public function testResolvePreviousPeriodShiftsByTheWindowLength(): void
  {
    $previous = DashboardSeriesBuilder::resolvePreviousPeriod(
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-10T00:00:00+00:00'),
    );

    self::assertSame('2026-02-19', $previous['from']->format('Y-m-d'));
    self::assertSame('2026-02-28', $previous['to']->format('Y-m-d'));
  }

  #[Test]
  public function testRelativeDeltaHandlesAZeroBaseline(): void
  {
    self::assertSame(100.0, DashboardSeriesBuilder::relativeDelta(7, 0));
    self::assertSame(0.0, DashboardSeriesBuilder::relativeDelta(0, 0));
  }

  #[Test]
  public function testRelativeDeltaRoundsToTwoDecimals(): void
  {
    self::assertSame(50.0, DashboardSeriesBuilder::relativeDelta(15, 10));
    self::assertSame(-33.33, DashboardSeriesBuilder::relativeDelta(2, 3));
  }

  #[Test]
  public function testSumSeriesAddsEveryBucketValue(): void
  {
    self::assertSame(6, DashboardSeriesBuilder::sumSeries([1, 2, 3]));
    self::assertSame(0, DashboardSeriesBuilder::sumSeries([]));
  }

  #[Test]
  public function testFormatIso8601OmitsAZeroMicrosecondFraction(): void
  {
    self::assertSame(
      '2026-03-01T10:00:00+00:00',
      DashboardSeriesBuilder::formatIso8601(new DateTimeImmutable('2026-03-01T10:00:00+00:00')),
    );
  }

  #[Test]
  public function testFormatIso8601KeepsANonZeroMicrosecondFraction(): void
  {
    self::assertSame(
      '2026-03-01T10:00:00.123456+00:00',
      DashboardSeriesBuilder::formatIso8601(new DateTimeImmutable('2026-03-01T10:00:00.123456+00:00')),
    );
  }

  #[Test]
  public function testResolveGranularityNormalizesExplicitValues(): void
  {
    $start = new DateTimeImmutable('2026-03-01T00:00:00+00:00');
    $end = new DateTimeImmutable('2026-03-10T00:00:00+00:00');

    self::assertSame('week', DashboardSeriesBuilder::resolveGranularity('week', $start, $end));
    self::assertSame('month', DashboardSeriesBuilder::resolveGranularity('month', $start, $end));
    self::assertSame('day', DashboardSeriesBuilder::resolveGranularity('quarter', $start, $end));
  }

  #[Test]
  public function testResolveGranularityAutoScalesWithTheWindowLength(): void
  {
    $start = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    self::assertSame(
      'day',
      DashboardSeriesBuilder::resolveGranularity('auto', $start, new DateTimeImmutable('2026-01-20T00:00:00+00:00')),
    );
    self::assertSame(
      'week',
      DashboardSeriesBuilder::resolveGranularity('auto', $start, new DateTimeImmutable('2026-03-01T00:00:00+00:00')),
    );
    self::assertSame(
      'month',
      DashboardSeriesBuilder::resolveGranularity('auto', $start, new DateTimeImmutable('2026-12-01T00:00:00+00:00')),
    );
  }

  #[Test]
  public function testNormalizeSeriesFillsEmptyDailyBuckets(): void
  {
    $series = DashboardSeriesBuilder::normalizeSeries(
      new DateTimeImmutable('2026-03-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-03T23:59:59+00:00'),
      'day',
      ['2026-03-02' => 4],
    );

    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 0],
      ['bucket' => '2026-03-02', 'value' => 4],
      ['bucket' => '2026-03-03', 'value' => 0],
    ], $series);
  }

  #[Test]
  public function testNormalizeSeriesAggregatesIntoWeeklyBuckets(): void
  {
    $series = DashboardSeriesBuilder::normalizeSeries(
      new DateTimeImmutable('2026-03-02T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-22T00:00:00+00:00'),
      'week',
      ['2026-03-02' => 1, '2026-03-04' => 2, '2026-03-10' => 5],
    );

    self::assertSame([
      ['bucket' => '2026-W10', 'value' => 3],
      ['bucket' => '2026-W11', 'value' => 5],
      ['bucket' => '2026-W12', 'value' => 0],
    ], $series);
  }

  #[Test]
  public function testNormalizeSeriesAggregatesIntoMonthlyBuckets(): void
  {
    $series = DashboardSeriesBuilder::normalizeSeries(
      new DateTimeImmutable('2026-01-15T00:00:00+00:00'),
      new DateTimeImmutable('2026-03-05T00:00:00+00:00'),
      'month',
      ['2026-01-20' => 2, '2026-03-01' => 7],
    );

    self::assertSame([
      ['bucket' => '2026-01', 'value' => 2],
      ['bucket' => '2026-02', 'value' => 0],
      ['bucket' => '2026-03', 'value' => 7],
    ], $series);
  }

  #[Test]
  public function testTrendPeriodBoundsAreExposedAsConstants(): void
  {
    self::assertSame(30, DashboardSeriesBuilder::DEFAULT_TREND_PERIOD_DAYS);
    self::assertSame(366, DashboardSeriesBuilder::MAX_TREND_PERIOD_DAYS);
  }
}
