<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, NonConformityStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\Support\{DashboardDateTimeParser, DashboardSeriesBuilder};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\OrganizationId;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function count;
use function hash;
use function in_array;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

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

  public const string METRIC_EQUIPMENT_CREATED = 'equipment_created';

  public const string METRIC_FACILITIES_CREATED = 'facilities_created';

  public const string METRIC_NON_CONFORMITIES_OPENED = 'non_conformities_opened';

  public const string METRIC_NON_CONFORMITIES_RESOLVED = 'non_conformities_resolved';

  private const string DEFAULT_DASHBOARD_TIME_ZONE = 'UTC';

  private const int DEFAULT_CACHE_TTL_SECONDS = 30;

  /**
   * Largest number of distinct metrics a single trend request may combine
   * (primary metric + `additionalMetrics`), e.g. the two-series non-conformity
   * chart (opened + resolved). Bounds the per-request fan-out into the
   * statistics ports regardless of how many metrics the catalog grows to.
   */
  private const int MAX_REQUESTED_METRICS = 4;

  /**
   * Constructor.
   *
   * @since 1.0.0
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private EquipmentStatisticsPort $equipmentStatistics,
    private FacilityStatisticsPort $facilityStatistics,
    private InspectionStatisticsPort $inspectionStatistics,
    private NonConformityStatisticsPort $nonConformityStatistics,
    private ?CachePort $cache = null,
    private int $cacheTtl = self::DEFAULT_CACHE_TTL_SECONDS,
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
    $requestedMetrics = $this->resolveRequestedMetrics($metric, $query->additionalMetrics);

    $this->assertMetricsPermissions($query->userId, $query->organizationId, $requestedMetrics);

    $organization = $this->organizationRepository->findById(OrganizationId::fromString($query->organizationId));
    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $cacheKey = $this->buildCacheKey($query, $requestedMetrics);
    $cached = $this->readCache($cacheKey);
    if ($cached instanceof GetOrganizationDashboardTrendResult) {
      return $cached;
    }

    $generatedAt = new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_DASHBOARD_TIME_ZONE));
    $periodFrom = DashboardDateTimeParser::parseNullable($query->periodFrom, 'from');
    $periodTo = DashboardDateTimeParser::parseNullable($query->periodTo, 'to');
    $dashboardTimeZone = DashboardSeriesBuilder::resolveDashboardTimeZone($query->timeZone, $periodFrom, $periodTo, $generatedAt);
    $generatedAt = $generatedAt->setTimezone($dashboardTimeZone);
    [$periodStart, $periodEnd] = DashboardSeriesBuilder::resolvePeriod($periodFrom, $periodTo, $generatedAt, $dashboardTimeZone);
    DashboardSeriesBuilder::assertSupportedPeriod($periodStart, $periodEnd);
    $granularity = DashboardSeriesBuilder::resolveGranularity($query->granularity, $periodStart, $periodEnd);
    $comparisonPeriod = $query->compareWithPreviousPeriod ? DashboardSeriesBuilder::resolvePreviousPeriod($periodStart, $periodEnd) : null;
    $periodStartFormatted = DashboardSeriesBuilder::formatIso8601($periodStart);
    $periodEndFormatted = DashboardSeriesBuilder::formatIso8601($periodEnd);
    $currentCounts = $this->loadMetricCounts($metric, $query, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName());
    $currentTotal = DashboardSeriesBuilder::sumSeries($currentCounts);
    $primarySeries = DashboardSeriesBuilder::normalizeSeries($periodStart, $periodEnd, $granularity, $currentCounts);

    $result = new GetOrganizationDashboardTrendResult(
      generatedAt: DashboardSeriesBuilder::formatIso8601($generatedAt),
      metric: $metric,
      period: ['from' => $periodStartFormatted, 'to' => $periodEndFormatted, 'granularity' => $granularity, 'comparison' => null !== $comparisonPeriod ? 'previous_period' : 'none', 'timezone' => $dashboardTimeZone->getName()],
      summary: ['total' => $currentTotal],
      series: $primarySeries,
      comparison: $this->buildComparison($metric, $query, $comparisonPeriod, $granularity, $currentTotal),
      seriesByMetric: $this->buildSeriesByMetric($requestedMetrics, $metric, $primarySeries, $query, $periodStart, $periodEnd, $granularity, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName()),
    );
    $this->writeCache($cacheKey, $result);

    return $result;
  }

  private function assertSupportedMetric(string $metric): string
  {
    if (!in_array($metric, [self::METRIC_INSPECTIONS_PERFORMED, self::METRIC_EQUIPMENT_CREATED, self::METRIC_FACILITIES_CREATED, self::METRIC_NON_CONFORMITIES_OPENED, self::METRIC_NON_CONFORMITIES_RESOLVED], true)) {
      throw InvalidValueException::because('Unsupported dashboard trend metric.');
    }

    return $metric;
  }

  /**
   * Resolves the deduplicated, capped list of metrics this request must
   * compute a series for: the primary metric first, followed by every
   * distinct, supported entry from `additionalMetrics` (the `metrics` filter).
   *
   * @param list<string> $additionalMetrics
   *
   * @return list<string>
   */
  private function resolveRequestedMetrics(string $metric, array $additionalMetrics): array
  {
    $metrics = [$metric];
    foreach ($additionalMetrics as $additionalMetric) {
      $additionalMetric = $this->assertSupportedMetric($additionalMetric);
      if (!in_array($additionalMetric, $metrics, true)) {
        $metrics[] = $additionalMetric;
      }
    }

    if (count($metrics) > self::MAX_REQUESTED_METRICS) {
      throw InvalidValueException::because(sprintf('At most %d dashboard trend metrics may be requested at once.', self::MAX_REQUESTED_METRICS));
    }

    return $metrics;
  }

  /**
   * Checks every permission required by every requested metric — a loop over
   * {@see OrganizationPermissionCatalog::dashboardTrendReadDependencies()} so a
   * caller cannot read a metric it lacks rights to by hiding it behind one it
   * is allowed to see.
   *
   * @param list<string> $metrics
   */
  private function assertMetricsPermissions(string $userId, string $organizationId, array $metrics): void
  {
    foreach ($metrics as $metric) {
      foreach (OrganizationPermissionCatalog::dashboardTrendReadDependencies($metric) as $permission) {
        if (!$this->authorization->hasPermission($userId, $organizationId, $permission)) {
          throw OrganizationAccessDeniedException::missingPermission($permission);
        }
      }
    }
  }

  /**
   * Builds the `seriesByMetric` map when more than the primary metric was
   * requested, so a chart combining several metrics (e.g. non-conformities
   * opened vs resolved) can render from a single call. Every entry shares the
   * same resolved period, timezone and granularity as the primary series.
   *
   * @param list<string> $requestedMetrics
   * @param list<array{bucket: string, value: int}> $primarySeries
   *
   * @return array<string, list<array{bucket: string, value: int}>>
   */
  private function buildSeriesByMetric(
    array $requestedMetrics,
    string $primaryMetric,
    array $primarySeries,
    GetOrganizationDashboardTrendQuery $query,
    DateTimeImmutable $periodStart,
    DateTimeImmutable $periodEnd,
    string $granularity,
    string $periodStartFormatted,
    string $periodEndFormatted,
    string $timeZone,
  ): array {
    if (count($requestedMetrics) <= 1) {
      return [];
    }

    $seriesByMetric = [$primaryMetric => $primarySeries];
    foreach ($requestedMetrics as $additionalMetric) {
      if ($additionalMetric === $primaryMetric) {
        continue;
      }
      $counts = $this->loadMetricCounts($additionalMetric, $query, $periodStartFormatted, $periodEndFormatted, $timeZone);
      $seriesByMetric[$additionalMetric] = DashboardSeriesBuilder::normalizeSeries($periodStart, $periodEnd, $granularity, $counts);
    }

    return $seriesByMetric;
  }

  /**
   * @param list<string> $requestedMetrics
   */
  private function buildCacheKey(GetOrganizationDashboardTrendQuery $query, array $requestedMetrics): string
  {
    try {
      $payload = json_encode([
        'organizationId' => $query->organizationId,
        'metric' => $query->metric,
        'requestedMetrics' => $requestedMetrics,
        'periodFrom' => $query->periodFrom,
        'periodTo' => $query->periodTo,
        'compareWithPreviousPeriod' => $query->compareWithPreviousPeriod,
        'granularity' => $query->granularity,
        'timeZone' => $query->timeZone,
        'facilityType' => $query->facilityType,
        'equipmentType' => $query->equipmentType,
        'equipmentStatus' => $query->equipmentStatus,
        'inspectionStatus' => $query->inspectionStatus,
        'inspectionResult' => $query->inspectionResult,
        'inspectorType' => $query->inspectorType,
        'nonConformityStatus' => $query->nonConformityStatus,
        'nonConformitySeverity' => $query->nonConformitySeverity,
      ], JSON_THROW_ON_ERROR);
    } catch (Throwable) {
      $payload = $query->organizationId . '|' . $query->metric;
    }

    return 'organization.dashboard_trend.' . hash('sha256', $payload);
  }

  private function readCache(string $cacheKey): ?GetOrganizationDashboardTrendResult
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    try {
      $cached = $this->cache->get($cacheKey);
    } catch (Throwable) {
      return null;
    }

    return $cached instanceof GetOrganizationDashboardTrendResult ? $cached : null;
  }

  private function writeCache(string $cacheKey, GetOrganizationDashboardTrendResult $result): void
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return;
    }

    try {
      $this->cache->set($cacheKey, $result, $this->cacheTtl);
    } catch (Throwable) {
      // Trend cache failures should not block fresh trend generation.
    }
  }

  /**
   * @return array<string, int>
   */
  private function loadMetricCounts(string $metric, GetOrganizationDashboardTrendQuery $query, string $periodStart, string $periodEnd, string $timeZone): array
  {
    return match ($metric) {
      self::METRIC_INSPECTIONS_PERFORMED => $this->countInspectionsPerformedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType),
      self::METRIC_EQUIPMENT_CREATED => $this->countEquipmentCreatedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->equipmentType, $query->equipmentStatus),
      self::METRIC_FACILITIES_CREATED => $this->countFacilitiesCreatedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->facilityType),
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
    $from = DashboardSeriesBuilder::formatIso8601($comparisonPeriod['from']);
    $to = DashboardSeriesBuilder::formatIso8601($comparisonPeriod['to']);
    $counts = $this->loadMetricCounts($metric, $query, $from, $to, $comparisonPeriod['from']->getTimezone()->getName());
    $previousTotal = DashboardSeriesBuilder::sumSeries($counts);

    return ['mode' => 'previous_period', 'from' => $from, 'to' => $to, 'summary' => ['total' => $previousTotal, 'delta' => DashboardSeriesBuilder::relativeDelta($currentTotal, $previousTotal)], 'series' => DashboardSeriesBuilder::normalizeSeries($comparisonPeriod['from'], $comparisonPeriod['to'], $granularity, $counts)];
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
  private function countEquipmentCreatedByDay(string $organizationId, string $createdAtFrom, string $createdAtTo, ?string $timeZone = null, ?string $type = null, ?string $status = null): array
  {
    $arguments = [$organizationId, $createdAtFrom, $createdAtTo];
    if (null !== $timeZone) {
      $arguments['timeZone'] = $timeZone;
    }
    if (null !== $type) {
      $arguments['type'] = $type;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }

    return $this->equipmentStatistics->countEquipmentCreatedByDay(...$arguments);
  }

  /**
   * @return array<string, int>
   */
  private function countFacilitiesCreatedByDay(string $organizationId, string $createdAtFrom, string $createdAtTo, ?string $timeZone = null, ?string $type = null): array
  {
    $arguments = [$organizationId, $createdAtFrom, $createdAtTo];
    if (null !== $timeZone) {
      $arguments['timeZone'] = $timeZone;
    }
    if (null !== $type) {
      $arguments['type'] = $type;
    }

    return $this->facilityStatistics->countFacilitiesCreatedByDay(...$arguments);
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
