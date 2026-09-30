<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend;

use DateTimeImmutable;
use DateTimeZone;
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
 * Class GetOrganizationDashboardTrendHandler
 *
 * Authorizes dashboard trend metrics and combines their bounded time series for an organization.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationDashboardTrendHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant METRIC_INSPECTIONS_PERFORMED
   *
   * Metric identifier for inspections completed over time.
   *
   * @access public
   */
  public const string METRIC_INSPECTIONS_PERFORMED = 'inspections_performed';

  /**
   * Constant METRIC_EQUIPMENT_CREATED
   *
   * Metric identifier for equipment created over time.
   *
   * @access public
   */
  public const string METRIC_EQUIPMENT_CREATED = 'equipment_created';

  /**
   * Constant METRIC_FACILITIES_CREATED
   *
   * Metric identifier for facilities created over time.
   *
   * @access public
   */
  public const string METRIC_FACILITIES_CREATED = 'facilities_created';

  /**
   * Constant METRIC_NON_CONFORMITIES_OPENED
   *
   * Metric identifier for non-conformities created over time.
   *
   * @access public
   */
  public const string METRIC_NON_CONFORMITIES_OPENED = 'non_conformities_opened';

  /**
   * Constant METRIC_NON_CONFORMITIES_RESOLVED
   *
   * Metric identifier for non-conformities resolved over time.
   *
   * @access public
   */
  public const string METRIC_NON_CONFORMITIES_RESOLVED = 'non_conformities_resolved';

  /**
   * Constant DEFAULT_DASHBOARD_TIME_ZONE
   *
   * Timezone used to establish dashboard generation time before request resolution.
   *
   * @access private
   */
  private const string DEFAULT_DASHBOARD_TIME_ZONE = 'UTC';

  /**
   * Constant DEFAULT_CACHE_TTL_SECONDS
   *
   * Cache duration used when no alternate TTL is injected.
   *
   * @access private
   */
  private const int DEFAULT_CACHE_TTL_SECONDS = 30;

  /**
   * Constant MAX_REQUESTED_METRICS
   *
   * Largest number of distinct metrics a single trend request may combine
   * (primary metric + `additionalMetrics`), e.g. the two-series non-conformity
   * chart (opened + resolved). Bounds the per-request fan-out into the
   * statistics ports regardless of how many metrics the catalog grows to.
   *
   * @access private
   */
  private const int MAX_REQUESTED_METRICS = 4;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies authorization, organization and metric statistics ports with optional caching.
   *
   * @access public
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization checks each requested metric permission
   * @param OrganizationRepositoryPort $organizationRepository checks organization existence
   * @param EquipmentStatisticsPort $equipmentStatistics reads equipment series
   * @param FacilityStatisticsPort $facilityStatistics reads facility series
   * @param InspectionStatisticsPort $inspectionStatistics reads inspection series
   * @param NonConformityStatisticsPort $nonConformityStatistics reads non-conformity series
   * @param CachePort|null $cache optional cache for completed trend results
   * @param int $cacheTtl cache lifetime in seconds
   *
   * @return void
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
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Authorizes and computes the requested dashboard trend, using cached results when available.
   *
   * @access public
   * @since 1.0.0
   *
   * @param GetOrganizationDashboardTrendQuery $query organization, filters and requested metrics
   *
   * @return GetOrganizationDashboardTrendResult generated dashboard trend response
   *
   * @throws OrganizationAccessDeniedException when the user lacks any requested metric permission
   * @throws OrganizationNotFoundException when the organization does not exist
   * @throws InvalidValueException when a metric or requested period is invalid
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
      seriesByMetric: $this->buildSeriesByMetric($requestedMetrics, $metric, $primarySeries, $query, $periodStart, $periodEnd, $granularity),
    );
    $this->writeCache($cacheKey, $result);

    return $result;
  }

  /**
   * Method assertSupportedMetric
   *
   * Accepts only metric identifiers implemented by the dashboard statistics adapters.
   *
   * @access private
   *
   * @param string $metric metric identifier supplied by the query
   *
   * @return string supported metric identifier
   *
   * @throws InvalidValueException when the metric is unsupported
   */
  private function assertSupportedMetric(string $metric): string
  {
    if (!in_array($metric, [self::METRIC_INSPECTIONS_PERFORMED, self::METRIC_EQUIPMENT_CREATED, self::METRIC_FACILITIES_CREATED, self::METRIC_NON_CONFORMITIES_OPENED, self::METRIC_NON_CONFORMITIES_RESOLVED], true)) {
      throw InvalidValueException::because('Unsupported dashboard trend metric.');
    }

    return $metric;
  }

  /**
   * Method resolveRequestedMetrics
   *
   * Resolves the deduplicated, capped list of metrics this request must
   * compute a series for: the primary metric first, followed by every
   * distinct, supported entry from `additionalMetrics` (the `metrics` filter).
   *
   * @access private
   *
   * @param string $metric primary metric identifier
   * @param list<string> $additionalMetrics other metric identifiers from the query
   *
   * @return list<string> distinct requested metrics with the primary metric first
   *
   * @throws InvalidValueException when an identifier is unsupported or the request exceeds the metric cap
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
   * Method assertMetricsPermissions
   *
   * Checks every permission required by every requested metric — a loop over
   * {@see OrganizationPermissionCatalog::dashboardTrendReadDependencies()} so a
   * caller cannot read a metric it lacks rights to by hiding it behind one it
   * is allowed to see.
   *
   * @access private
   *
   * @param string $userId requesting user identifier
   * @param string $organizationId organization whose metrics are read
   * @param list<string> $metrics requested metric identifiers
   *
   * @return void
   *
   * @throws OrganizationAccessDeniedException when any requested metric permission is missing
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
   * Method buildSeriesByMetric
   *
   * Builds the `seriesByMetric` map when more than the primary metric was
   * requested, so a chart combining several metrics (e.g. non-conformities
   * opened vs resolved) can render from a single call. Every entry shares the
   * same resolved period, timezone and granularity as the primary series.
   *
   * @access private
   *
   * @param list<string> $requestedMetrics metrics to include in the map
   * @param string $primaryMetric primary metric identifier
   * @param list<array{bucket: string, value: int}> $primarySeries
   * @param GetOrganizationDashboardTrendQuery $query filters and organization context
   * @param DateTimeImmutable $periodStart start of the resolved period
   * @param DateTimeImmutable $periodEnd end of the resolved period
   * @param string $granularity bucket granularity for the series
   *
   * @return array<string, list<array{bucket: string, value: int}>> series keyed by metric identifier
   */
  private function buildSeriesByMetric(
    array $requestedMetrics,
    string $primaryMetric,
    array $primarySeries,
    GetOrganizationDashboardTrendQuery $query,
    DateTimeImmutable $periodStart,
    DateTimeImmutable $periodEnd,
    string $granularity,
  ): array {
    if (count($requestedMetrics) <= 1) {
      return [];
    }

    $seriesByMetric = [$primaryMetric => $primarySeries];
    $periodStartFormatted = DashboardSeriesBuilder::formatIso8601($periodStart);
    $periodEndFormatted = DashboardSeriesBuilder::formatIso8601($periodEnd);
    $timeZone = $periodStart->getTimezone()->getName();
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
   * Method buildCacheKey
   *
   * Hashes all organization, metric, period and filter inputs into a stable result cache key.
   *
   * @access private
   *
   * @param GetOrganizationDashboardTrendQuery $query trend request whose inputs form the key
   * @param list<string> $requestedMetrics distinct metrics included in the response
   *
   * @return string cache key for the exact request
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

  /**
   * Method readCache
   *
   * Returns a cached trend result when caching is enabled and the entry has the expected type.
   * Cache failures are treated as misses.
   *
   * @access private
   *
   * @param string $cacheKey cache entry to read
   *
   * @return GetOrganizationDashboardTrendResult|null cached result, or null on a miss
   */
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

  /**
   * Method writeCache
   *
   * Stores the trend result when caching is enabled; cache failures do not stop response generation.
   *
   * @access private
   *
   * @param string $cacheKey cache entry to write
   * @param GetOrganizationDashboardTrendResult $result generated trend result
   *
   * @return void
   */
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
   * Method loadMetricCounts
   *
   * Dispatches a metric count request to the owning statistics port.
   *
   * @access private
   *
   * @param string $metric supported metric identifier
   * @param GetOrganizationDashboardTrendQuery $query organization and metric filters
   * @param string $periodStart inclusive period start in ISO 8601 form
   * @param string $periodEnd inclusive period end in ISO 8601 form
   * @param string $timeZone timezone used to bucket results
   *
   * @return array<string, int> counts keyed by bucket
   *
   * @throws InvalidValueException when the metric is unsupported
   */
  private function loadMetricCounts(string $metric, GetOrganizationDashboardTrendQuery $query, string $periodStart, string $periodEnd, string $timeZone): array
  {
    return match ($metric) {
      self::METRIC_INSPECTIONS_PERFORMED => $this->countInspectionsPerformedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType),
      self::METRIC_EQUIPMENT_CREATED => $this->countEquipmentCreatedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->equipmentType, $query->equipmentStatus),
      self::METRIC_FACILITIES_CREATED => $this->countFacilitiesCreatedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->facilityType),
      self::METRIC_NON_CONFORMITIES_OPENED => $this->countNonConformitiesCreatedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->nonConformitySeverity, $query->nonConformityStatus),
      self::METRIC_NON_CONFORMITIES_RESOLVED => $this->countNonConformitiesResolvedByDay($query->organizationId, $periodStart, $periodEnd, $timeZone, $query->nonConformitySeverity, $query->nonConformityStatus),
      default => throw InvalidValueException::because('Unsupported dashboard trend metric.'),
    };
  }

  /**
   * Method buildComparison
   *
   * Builds the previous-period series and summary when comparison is requested.
   *
   * @access private
   *
   * @param string $metric primary metric identifier
   * @param GetOrganizationDashboardTrendQuery $query request filters for the previous period
   * @param ?array{from: DateTimeImmutable, to: DateTimeImmutable} $comparisonPeriod
   * @param string $granularity bucket granularity for the comparison series
   * @param int $currentTotal total for the current period
   *
   * @return array<string, mixed> comparison mode, bounds, summary and normalized series
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
   * Method countInspectionsPerformedByDay
   *
   * Reads daily inspection counts with the supplied inspection filters.
   *
   * @access private
   *
   * @param string $organizationId organization identifier
   * @param string $performedAtFrom inclusive lower bound
   * @param string $performedAtTo inclusive upper bound
   * @param string|null $timeZone timezone for daily buckets
   * @param string|null $status optional inspection status filter
   * @param string|null $result optional inspection result filter
   * @param string|null $inspectorType optional inspector type filter
   *
   * @return array<string, int> counts keyed by day bucket
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
   * Method countEquipmentCreatedByDay
   *
   * Reads daily equipment creation counts with optional type and status filters.
   *
   * @access private
   *
   * @param string $organizationId organization identifier
   * @param string $createdAtFrom inclusive lower bound
   * @param string $createdAtTo inclusive upper bound
   * @param string|null $timeZone timezone for daily buckets
   * @param string|null $type optional equipment type filter
   * @param string|null $status optional equipment status filter
   *
   * @return array<string, int> counts keyed by day bucket
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
   * Method countFacilitiesCreatedByDay
   *
   * Reads daily facility creation counts with an optional facility type filter.
   *
   * @access private
   *
   * @param string $organizationId organization identifier
   * @param string $createdAtFrom inclusive lower bound
   * @param string $createdAtTo inclusive upper bound
   * @param string|null $timeZone timezone for daily buckets
   * @param string|null $type optional facility type filter
   *
   * @return array<string, int> counts keyed by day bucket
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
   * Method countNonConformitiesCreatedByDay
   *
   * Reads daily non-conformity creation counts with optional severity and status filters.
   *
   * @access private
   *
   * @param string $organizationId organization identifier
   * @param string $createdAtFrom inclusive lower bound
   * @param string $createdAtTo inclusive upper bound
   * @param string|null $timeZone timezone for daily buckets
   * @param string|null $severity optional severity filter
   * @param string|null $status optional status filter
   *
   * @return array<string, int> counts keyed by day bucket
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
   * Method countNonConformitiesResolvedByDay
   *
   * Reads daily non-conformity resolution counts with optional severity and status filters.
   *
   * @access private
   *
   * @param string $organizationId organization identifier
   * @param string $resolvedAtFrom inclusive lower bound
   * @param string $resolvedAtTo inclusive upper bound
   * @param string|null $timeZone timezone for daily buckets
   * @param string|null $severity optional severity filter
   * @param string|null $status optional status filter
   *
   * @return array<string, int> counts keyed by day bucket
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
  // #endregion
}
