<?php

declare(strict_types=1);

namespace Organization\Application\Support;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Shared\Domain\Exception\InvalidValueException;

use function array_sum;
use function in_array;
use function max;
use function round;
use function timezone_identifiers_list;

/**
 * Support DashboardSeriesBuilder.
 *
 * Period resolution, bucketing and delta arithmetic shared by the dashboard
 * overview and the per-metric trend endpoints.
 *
 * These helpers previously existed as byte-identical private methods in BOTH
 * `GetOrganizationDashboardHandler` and `GetOrganizationDashboardTrendHandler`.
 * They are extracted here so the two handlers stop drifting apart, and so that
 * later work on either one does not have to merge inside a thousand-line class.
 *
 * Stateless by design — `public static` methods and no constructor, matching
 * the {@see DashboardDateTimeParser} precedent. Nothing here performs I/O; the
 * `count*ByDay` wrappers stayed in the handlers because they depend on injected
 * statistics ports.
 *
 * @category Support
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class DashboardSeriesBuilder
{
  // #region Constants
  /**
   * Default dashboard period length, in days, when no `from` filter is given.
   */
  public const int DEFAULT_TREND_PERIOD_DAYS = 30;

  /**
   * Longest period the dashboard will serve, in days.
   */
  public const int MAX_TREND_PERIOD_DAYS = 366;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Private to prevent instantiation: this support class is stateless.
   *
   * @since 1.0.0
   *
   * @codeCoverageIgnore Never executed: the constructor exists only to
   * forbid instantiation of this static catalogue.
   */
  private function __construct()
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method resolveDashboardTimeZone.
   *
   * @static
   *
   * Resolves the timezone every bucket boundary is computed in: the explicit
   * filter when given, otherwise the one implied by the period filters.
   *
   * @since 1.0.0
   *
   * @param ?string $timeZone the explicit IANA timezone filter, if any
   * @param ?DateTimeImmutable $periodFrom the period start filter, if any
   * @param ?DateTimeImmutable $periodTo the period end filter, if any
   * @param DateTimeImmutable $fallbackNow the reference used when no filter carries a timezone
   *
   * @return DateTimeZone the resolved timezone
   */
  public static function resolveDashboardTimeZone(?string $timeZone, ?DateTimeImmutable $periodFrom, ?DateTimeImmutable $periodTo, DateTimeImmutable $fallbackNow): DateTimeZone
  {
    if (null !== $timeZone) {
      if (!in_array($timeZone, timezone_identifiers_list(), true)) {
        throw InvalidValueException::because('Invalid "timezone" filter. Use a valid IANA timezone such as Europe/Paris.');
      }

      return new DateTimeZone($timeZone);
    }
    if ($periodFrom instanceof DateTimeImmutable && $periodTo instanceof DateTimeImmutable && $periodFrom->getTimezone()->getName() !== $periodTo->getTimezone()->getName()) {
      throw InvalidValueException::because('Mixed timezone offsets require the "timezone" filter.');
    }

    return self::normalizeImplicitDashboardTimeZone($periodFrom?->getTimezone() ?? $periodTo?->getTimezone() ?? $fallbackNow->getTimezone(), $periodFrom ?? $periodTo ?? $fallbackNow);
  }

  /**
   * Method resolvePeriod.
   *
   * @static
   *
   * Resolves the inclusive period the dashboard reports on.
   *
   * @since 1.0.0
   *
   * @param ?DateTimeImmutable $periodFrom the period start filter, if any
   * @param ?DateTimeImmutable $periodTo the period end filter, if any
   * @param DateTimeImmutable $now the current time
   * @param DateTimeZone $dashboardTimeZone the resolved dashboard timezone
   *
   * @return array{DateTimeImmutable, DateTimeImmutable} the period start and end
   */
  public static function resolvePeriod(?DateTimeImmutable $periodFrom, ?DateTimeImmutable $periodTo, DateTimeImmutable $now, DateTimeZone $dashboardTimeZone): array
  {
    $periodEnd = ($periodTo ?? $now)->setTimezone($dashboardTimeZone);
    $periodStart = null !== $periodFrom ? $periodFrom->setTimezone($dashboardTimeZone) : $periodEnd->sub(new DateInterval('P' . (self::DEFAULT_TREND_PERIOD_DAYS - 1) . 'D'))->setTime(0, 0);

    return [$periodStart, $periodEnd];
  }

  /**
   * Method assertSupportedPeriod.
   *
   * @static
   *
   * Rejects inverted and over-long periods.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the period start
   * @param DateTimeImmutable $periodEnd the period end
   */
  public static function assertSupportedPeriod(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): void
  {
    if ($periodStart->getTimestamp() > $periodEnd->getTimestamp()) {
      throw InvalidValueException::because('The "from" datetime filter must be before or equal to "to".');
    }
    if ((int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1 > self::MAX_TREND_PERIOD_DAYS) {
      throw InvalidValueException::because('Dashboard period cannot exceed 366 days.');
    }
  }

  /**
   * Method resolvePreviousPeriod.
   *
   * @static
   *
   * Returns the immediately preceding period of equal length, used as the
   * comparison baseline.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the current period start
   * @param DateTimeImmutable $periodEnd the current period end
   *
   * @return array{from: DateTimeImmutable, to: DateTimeImmutable} the previous period bounds
   */
  public static function resolvePreviousPeriod(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): array
  {
    $shift = new DateInterval('P' . max(1, (int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1) . 'D');

    return ['from' => $periodStart->sub($shift), 'to' => $periodEnd->sub($shift)];
  }

  /**
   * Method relativeDelta.
   *
   * @static
   *
   * Relative change between two counts, as a percentage. A zero baseline
   * yields 100.0 when the current value grew and 0.0 when both are zero —
   * never a division by zero.
   *
   * @since 1.0.0
   *
   * @param int $current the current-period count
   * @param int $previous the previous-period count
   *
   * @return float the relative delta, in percent
   */
  public static function relativeDelta(int $current, int $previous): float
  {
    if (0 === $previous) {
      return $current > 0 ? 100.0 : 0.0;
    }

    return round((($current - $previous) / $previous) * 100, 2);
  }

  /**
   * Method sumSeries.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param array<array-key, int> $series the per-bucket counts
   *
   * @return int the total
   */
  public static function sumSeries(array $series): int
  {
    return (int) array_sum($series);
  }

  /**
   * Method formatIso8601.
   *
   * @static
   *
   * Formats a datetime, omitting the microsecond fragment when it is zero so
   * the serialized value stays stable.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $value the datetime
   *
   * @return string the ISO-8601 representation
   */
  public static function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u') ? $value->format('Y-m-d\TH:i:sP') : $value->format('Y-m-d\TH:i:s.uP');
  }

  /**
   * Method resolveGranularity.
   *
   * @static
   *
   * Resolves the bucket granularity, widening it automatically for long
   * periods when the caller asked for `auto`.
   *
   * @since 1.0.0
   *
   * @param string $granularity the requested granularity (`day`, `week`, `month` or `auto`)
   * @param DateTimeImmutable $periodStart the period start
   * @param DateTimeImmutable $periodEnd the period end
   *
   * @return string the effective granularity
   */
  public static function resolveGranularity(string $granularity, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): string
  {
    if ('auto' !== $granularity) {
      return match ($granularity) {
        'week', 'month' => $granularity, default => 'day'
      };
    }
    $days = (int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1;

    return $days > 180 ? 'month' : ($days > 45 ? 'week' : 'day');
  }

  /**
   * Method normalizeSeries.
   *
   * @static
   *
   * Folds per-day counts into the requested granularity, emitting every
   * bucket in the period — including the empty ones, so a chart never has to
   * infer gaps.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the period start
   * @param DateTimeImmutable $periodEnd the period end
   * @param string $granularity the effective granularity
   * @param array<string, int> $counts the per-day counts, keyed `Y-m-d`
   *
   * @return list<array{bucket: string, value: int}> the normalized series
   */
  public static function normalizeSeries(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, string $granularity, array $counts): array
  {
    $series = self::initializeSeriesBuckets($periodStart, $periodEnd, $granularity);
    for ($cursor = $periodStart->setTime(0, 0), $lastDay = $periodEnd->setTime(0, 0); $cursor <= $lastDay; $cursor = $cursor->add(new DateInterval('P1D'))) {
      $bucket = self::bucketKeyForDate($cursor, $granularity);
      $series[$bucket] += $counts[$cursor->format('Y-m-d')] ?? 0;
    }
    $normalized = [];
    foreach ($series as $bucket => $value) {
      $normalized[] = ['bucket' => $bucket, 'value' => $value];
    }

    return $normalized;
  }

  /**
   * Method normalizeImplicitDashboardTimeZone.
   *
   * @static
   *
   * Accepts a named IANA zone as-is, tolerates a zero offset as UTC, and
   * refuses anything else rather than silently guessing.
   *
   * @since 1.0.0
   *
   * @param DateTimeZone $timeZone the implied timezone
   * @param DateTimeImmutable $reference the reference datetime for offset resolution
   *
   * @return DateTimeZone the normalized timezone
   */
  private static function normalizeImplicitDashboardTimeZone(DateTimeZone $timeZone, DateTimeImmutable $reference): DateTimeZone
  {
    if (in_array($timeZone->getName(), timezone_identifiers_list(), true)) {
      return $timeZone;
    }
    if (0 === $timeZone->getOffset($reference)) {
      return new DateTimeZone('UTC');
    }

    throw InvalidValueException::because('Non-UTC offset datetimes require the "timezone" filter.');
  }

  /**
   * Method initializeSeriesBuckets.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the period start
   * @param DateTimeImmutable $periodEnd the period end
   * @param string $granularity the effective granularity
   *
   * @return array<string, int> the zero-filled buckets, in order
   */
  private static function initializeSeriesBuckets(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, string $granularity): array
  {
    $series = [];
    for ($cursor = self::bucketStartForDate($periodStart, $granularity), $lastBucket = self::bucketStartForDate($periodEnd, $granularity); $cursor <= $lastBucket; $cursor = self::advanceBucket($cursor, $granularity)) {
      $series[self::bucketKeyForDate($cursor, $granularity)] = 0;
    }

    return $series;
  }

  /**
   * Method bucketStartForDate.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $date the date
   * @param string $granularity the effective granularity
   *
   * @return DateTimeImmutable the start of the bucket containing the date
   */
  private static function bucketStartForDate(DateTimeImmutable $date, string $granularity): DateTimeImmutable
  {
    return match ($granularity) {
      'week' => $date->setISODate((int) $date->format('o'), (int) $date->format('W'))->setTime(0, 0),
      'month' => $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 1)->setTime(0, 0),
      default => $date->setTime(0, 0),
    };
  }

  /**
   * Method advanceBucket.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $bucketStart the current bucket start
   * @param string $granularity the effective granularity
   *
   * @return DateTimeImmutable the next bucket start
   */
  private static function advanceBucket(DateTimeImmutable $bucketStart, string $granularity): DateTimeImmutable
  {
    return match ($granularity) {
      'week' => $bucketStart->add(new DateInterval('P7D')),
      'month' => $bucketStart->add(new DateInterval('P1M')),
      default => $bucketStart->add(new DateInterval('P1D')),
    };
  }

  /**
   * Method bucketKeyForDate.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $date the date
   * @param string $granularity the effective granularity
   *
   * @return string the bucket key
   */
  private static function bucketKeyForDate(DateTimeImmutable $date, string $granularity): string
  {
    return match ($granularity) {
      'week' => $date->format('o-\WW'),
      'month' => $date->format('Y-m'),
      default => $date->format('Y-m-d'),
    };
  }
  // #endregion
}
