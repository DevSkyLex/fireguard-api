<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{InspectionStatisticsPort, NonConformityStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\Support\DashboardDateTimeParser;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;

use function array_sum;
use function in_array;
use function max;
use function round;
use function timezone_identifiers_list;

/**
 * Handler for fetching organization dashboard trend data.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationDashboardTrendHandler implements QueryHandler
{
  public const string METRIC_INSPECTIONS_PERFORMED = 'inspections_performed';

  public const string METRIC_NON_CONFORMITIES_OPENED = 'non_conformities_opened';

  public const string METRIC_NON_CONFORMITIES_RESOLVED = 'non_conformities_resolved';

  private const string DEFAULT_DASHBOARD_TIME_ZONE = 'UTC';

  private const int DEFAULT_TREND_PERIOD_DAYS = 30;

  private const int MAX_TREND_PERIOD_DAYS = 366;

  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private InspectionStatisticsPort $inspectionStatistics,
    private NonConformityStatisticsPort $nonConformityStatistics,
  ) {
  }

  /**
   * Execute the dashboard trend query and return computed results.
   *
   * @since 1.0.0
   */
  public function __invoke(GetOrganizationDashboardTrendQuery $query): GetOrganizationDashboardTrendResult
  {
    $metric = $this->assertSupportedMetric($query->metric);

    $this->assertMetricPermissions($query->userId, $query->organizationId, $metric);

    $organization = $this->organizationRepository->findById(OrganizationId::fromString($query->organizationId));
    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $generatedAt = new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_DASHBOARD_TIME_ZONE));
    $periodFrom = DashboardDateTimeParser::parseNullable($query->periodFrom, 'from');
    $periodTo = DashboardDateTimeParser::parseNullable($query->periodTo, 'to');
    $dashboardTimeZone = $this->resolveDashboardTimeZone($query->timeZone, $periodFrom, $periodTo, $generatedAt);
    $generatedAt = $generatedAt->setTimezone($dashboardTimeZone);
    [$periodStart, $periodEnd] = $this->resolvePeriod($periodFrom, $periodTo, $generatedAt, $dashboardTimeZone);
    $this->assertSupportedPeriod($periodStart, $periodEnd);
    $granularity = $this->resolveGranularity($query->granularity, $periodStart, $periodEnd);
    $comparisonPeriod = $query->compareWithPreviousPeriod ? $this->resolvePreviousPeriod($periodStart, $periodEnd) : null;
    $periodStartFormatted = $this->formatIso8601($periodStart);
    $periodEndFormatted = $this->formatIso8601($periodEnd);
    $currentCounts = $this->loadMetricCounts($metric, $query, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName());
    $currentTotal = $this->sumSeries($currentCounts);

    return new GetOrganizationDashboardTrendResult(
      generatedAt: $this->formatIso8601($generatedAt),
      metric: $metric,
      period: ['from' => $periodStartFormatted, 'to' => $periodEndFormatted, 'granularity' => $granularity, 'comparison' => null !== $comparisonPeriod ? 'previous_period' : 'none', 'timezone' => $dashboardTimeZone->getName()],
      summary: ['total' => $currentTotal],
      series: $this->normalizeSeries($periodStart, $periodEnd, $granularity, $currentCounts),
      comparison: $this->buildComparison($metric, $query, $comparisonPeriod, $granularity, $currentTotal),
    );
  }

  private function assertSupportedMetric(string $metric): string
  {
    if (!in_array($metric, [self::METRIC_INSPECTIONS_PERFORMED, self::METRIC_NON_CONFORMITIES_OPENED, self::METRIC_NON_CONFORMITIES_RESOLVED], true)) {
      throw new InvalidArgumentException('Unsupported dashboard trend metric.');
    }

    return $metric;
  }

  private function assertMetricPermissions(string $userId, string $organizationId, string $metric): void
  {
    foreach (OrganizationPermissionCatalog::dashboardTrendReadDependencies($metric) as $permission) {
      if (!$this->authorization->hasPermission($userId, $organizationId, $permission)) {
        throw OrganizationAccessDeniedException::missingPermission($permission);
      }
    }
  }

  private function resolveDashboardTimeZone(?string $timeZone, ?DateTimeImmutable $periodFrom, ?DateTimeImmutable $periodTo, DateTimeImmutable $fallbackNow): DateTimeZone
  {
    if (null !== $timeZone) {
      if (!in_array($timeZone, timezone_identifiers_list(), true)) {
        throw new InvalidArgumentException('Invalid "timezone" filter. Use a valid IANA timezone such as Europe/Paris.');
      }

      return new DateTimeZone($timeZone);
    }
    if ($periodFrom instanceof DateTimeImmutable && $periodTo instanceof DateTimeImmutable && $periodFrom->getTimezone()->getName() !== $periodTo->getTimezone()->getName()) {
      throw new InvalidArgumentException('Mixed timezone offsets require the "timezone" filter.');
    }

    return $this->normalizeImplicitDashboardTimeZone($periodFrom?->getTimezone() ?? $periodTo?->getTimezone() ?? $fallbackNow->getTimezone(), $periodFrom ?? $periodTo ?? $fallbackNow);
  }

  private function normalizeImplicitDashboardTimeZone(DateTimeZone $timeZone, DateTimeImmutable $reference): DateTimeZone
  {
    if (in_array($timeZone->getName(), timezone_identifiers_list(), true)) {
      return $timeZone;
    }
    if (0 === $timeZone->getOffset($reference)) {
      return new DateTimeZone('UTC');
    }

    throw new InvalidArgumentException('Non-UTC offset datetimes require the "timezone" filter.');
  }

  /**
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
   */
  private function resolvePeriod(?DateTimeImmutable $periodFrom, ?DateTimeImmutable $periodTo, DateTimeImmutable $now, DateTimeZone $dashboardTimeZone): array
  {
    $periodEnd = ($periodTo ?? $now)->setTimezone($dashboardTimeZone);
    $periodStart = null !== $periodFrom ? $periodFrom->setTimezone($dashboardTimeZone) : $periodEnd->sub(new DateInterval('P' . (self::DEFAULT_TREND_PERIOD_DAYS - 1) . 'D'))->setTime(0, 0);

    return [$periodStart, $periodEnd];
  }

  private function assertSupportedPeriod(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): void
  {
    if ($periodStart->getTimestamp() > $periodEnd->getTimestamp()) {
      throw new InvalidArgumentException('The "from" datetime filter must be before or equal to "to".');
    }
    if ((int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1 > self::MAX_TREND_PERIOD_DAYS) {
      throw new InvalidArgumentException('Dashboard period cannot exceed 366 days.');
    }
  }

  private function resolveGranularity(string $granularity, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): string
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
   * @return array{from: DateTimeImmutable, to: DateTimeImmutable}
   */
  private function resolvePreviousPeriod(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): array
  {
    $shift = new DateInterval('P' . max(1, (int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1) . 'D');

    return ['from' => $periodStart->sub($shift), 'to' => $periodEnd->sub($shift)];
  }

  /**
   * @return array<string, int>
   */
  private function loadMetricCounts(string $metric, GetOrganizationDashboardTrendQuery $query, string $periodStart, string $periodEnd, string $timeZone): array
  {
    return match ($metric) {
      self::METRIC_INSPECTIONS_PERFORMED => $this->countInspectionsPerformedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType),
      self::METRIC_NON_CONFORMITIES_OPENED => $this->countNonConformitiesCreatedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->nonConformitySeverity, $query->nonConformityStatus),
      self::METRIC_NON_CONFORMITIES_RESOLVED => $this->countNonConformitiesResolvedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->nonConformitySeverity, $query->nonConformityStatus),
      default => throw new InvalidArgumentException('Unsupported dashboard trend metric.'),
    };
  }

  /**
   * @param ?array{from: DateTimeImmutable, to: DateTimeImmutable} $comparisonPeriod
   *
   * @return array<string, mixed>
   */
  private function buildComparison(string $metric, GetOrganizationDashboardTrendQuery $query, ?array $comparisonPeriod, string $granularity, int $currentTotal): array
  {
    if (null === $comparisonPeriod) {
      return ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []];
    }
    $from = $this->formatIso8601($comparisonPeriod['from']);
    $to = $this->formatIso8601($comparisonPeriod['to']);
    $counts = $this->loadMetricCounts($metric, $query, $from, $to, $comparisonPeriod['from']->getTimezone()->getName());
    $previousTotal = $this->sumSeries($counts);

    return ['mode' => 'previous_period', 'from' => $from, 'to' => $to, 'summary' => ['total' => $previousTotal, 'delta' => $this->relativeDelta($currentTotal, $previousTotal)], 'series' => $this->normalizeSeries($comparisonPeriod['from'], $comparisonPeriod['to'], $granularity, $counts)];
  }

  private function relativeDelta(int $current, int $previous): float
  {
    if (0 === $previous) {
      return $current > 0 ? 100.0 : 0.0;
    }

    return round((($current - $previous) / $previous) * 100, 2);
  }

  /**
   * @param array<string, int> $series
   */
  private function sumSeries(array $series): int
  {
    return (int) array_sum($series);
  }

  /**
   * @param array<string, int> $counts
   *
   * @return list<array{bucket: string, value: int}>
   */
  private function normalizeSeries(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, string $granularity, array $counts): array
  {
    $series = $this->initializeSeriesBuckets($periodStart, $periodEnd, $granularity);
    for ($cursor = $periodStart->setTime(0, 0), $lastDay = $periodEnd->setTime(0, 0); $cursor <= $lastDay; $cursor = $cursor->add(new DateInterval('P1D'))) {
      $bucket = $this->bucketKeyForDate($cursor, $granularity);
      $series[$bucket] += $counts[$cursor->format('Y-m-d')] ?? 0;
    }
    $normalized = [];
    foreach ($series as $bucket => $value) {
      $normalized[] = ['bucket' => $bucket, 'value' => $value];
    }

    return $normalized;
  }

  /**
   * @return array<string, int>
   */
  private function initializeSeriesBuckets(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, string $granularity): array
  {
    $series = [];
    for ($cursor = $this->bucketStartForDate($periodStart, $granularity), $lastBucket = $this->bucketStartForDate($periodEnd, $granularity); $cursor <= $lastBucket; $cursor = $this->advanceBucket($cursor, $granularity)) {
      $series[$this->bucketKeyForDate($cursor, $granularity)] = 0;
    }

    return $series;
  }

  private function bucketStartForDate(DateTimeImmutable $date, string $granularity): DateTimeImmutable
  {
    return match ($granularity) {
      'week' => $date->setISODate((int) $date->format('o'), (int) $date->format('W'))->setTime(0, 0),
      'month' => $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 1)->setTime(0, 0),
      default => $date->setTime(0, 0),
    };
  }

  private function advanceBucket(DateTimeImmutable $bucketStart, string $granularity): DateTimeImmutable
  {
    return match ($granularity) {
      'week' => $bucketStart->add(new DateInterval('P7D')),
      'month' => $bucketStart->add(new DateInterval('P1M')),
      default => $bucketStart->add(new DateInterval('P1D')),
    };
  }

  private function bucketKeyForDate(DateTimeImmutable $date, string $granularity): string
  {
    return match ($granularity) {
      'week' => $date->format('o-\\WW'),
      'month' => $date->format('Y-m'),
      default => $date->format('Y-m-d'),
    };
  }

  private function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u') ? $value->format('Y-m-d\\TH:i:sP') : $value->format('Y-m-d\\TH:i:s.uP');
  }

  /**
   * @return array<string, int>
   */
  private function countInspectionsPerformedByDay(string $organizationId, string $performedAtFrom, string $performedAtTo, ?string $timeZone = null, ?string $status = null, ?string $result = null, ?string $inspectorType = null): array
  {
    $arguments = [$organizationId, $performedAtFrom, $performedAtTo];
    if (null !== $timeZone) {
      $arguments['timeZone'] = $timeZone;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }
    if (null !== $result) {
      $arguments['result'] = $result;
    }
    if (null !== $inspectorType) {
      $arguments['inspectorType'] = $inspectorType;
    }

    return $this->inspectionStatistics->countInspectionsPerformedByDay(...$arguments);
  }

  /**
   * @return array<string, int>
   */
  private function countNonConformitiesCreatedByDay(string $organizationId, string $createdAtFrom, string $createdAtTo, ?string $timeZone = null, ?string $severity = null, ?string $status = null): array
  {
    $arguments = [$organizationId, $createdAtFrom, $createdAtTo];
    if (null !== $timeZone) {
      $arguments['timeZone'] = $timeZone;
    }
    if (null !== $severity) {
      $arguments['severity'] = $severity;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }

    return $this->nonConformityStatistics->countNonConformitiesCreatedByDay(...$arguments);
  }

  /**
   * @return array<string, int>
   */
  private function countNonConformitiesResolvedByDay(string $organizationId, string $resolvedAtFrom, string $resolvedAtTo, ?string $timeZone = null, ?string $severity = null, ?string $status = null): array
  {
    $arguments = [$organizationId, $resolvedAtFrom, $resolvedAtTo];
    if (null !== $timeZone) {
      $arguments['timeZone'] = $timeZone;
    }
    if (null !== $severity) {
      $arguments['severity'] = $severity;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }

    return $this->nonConformityStatistics->countNonConformitiesResolvedByDay(...$arguments);
  }
}
