<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, InterventionStatisticsPort, NonConformityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Support\{DashboardDateTimeParser, DashboardSeriesBuilder};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationStatus};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

use function array_keys;
use function count;
use function hash;
use function json_encode;
use function max;
use function round;

use const JSON_THROW_ON_ERROR;

/**
 * Handler for fetching organization dashboard data.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetOrganizationDashboardHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant DEFAULT_DASHBOARD_TIME_ZONE.
   *
   * Default time zone used for dashboard generation when
   * no other time zone information is available.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_DASHBOARD_TIME_ZONE = 'UTC';

  /**
   * Constant STORAGE_TIME_ZONE.
   *
   * Internal persistence timezone used for timestamp-without-time-zone
   * organization member records.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string STORAGE_TIME_ZONE = 'UTC';

  private const int DEFAULT_CACHE_TTL_SECONDS = 30;

  /**
   * Constant INTERVENTIONS_READ_PERMISSION.
   *
   * Permission gating the `recentInterventions` dashboard section.
   * Deliberately NOT part of `OrganizationPermissionCatalog::dashboardReadDependencies()`
   * so the section degrades to an empty list instead of a 403 for
   * organizations/members without field-intervention access.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string INTERVENTIONS_READ_PERMISSION = 'organization.interventions.read';

  /**
   * Constant RECENT_INTERVENTIONS_LIMIT.
   *
   * Maximum number of recent interventions surfaced on the dashboard.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int RECENT_INTERVENTIONS_LIMIT = 5;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * GetOrganizationDashboardHandler class.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization service
   * @param OrganizationRepositoryPort $organizationRepository the organization repository
   * @param OrganizationMemberRepositoryPort $memberRepository the organization member repository
   * @param OrganizationRoleRepositoryPort $roleRepository the organization role repository
   * @param OrganizationInvitationRepositoryPort $invitationRepository the organization invitation repository
   * @param FacilityStatisticsPort $facilityStatistics the facility statistics service
   * @param EquipmentStatisticsPort $equipmentStatistics the equipment statistics service
   * @param InspectionStatisticsPort $inspectionStatistics the inspection statistics service
   * @param NonConformityStatisticsPort $nonConformityStatistics the non-conformity statistics service
   * @param InterventionStatisticsPort $interventionStatistics the intervention statistics service
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private OrganizationRepositoryPort $organizationRepository,
    private OrganizationMemberRepositoryPort $memberRepository,
    private OrganizationRoleRepositoryPort $roleRepository,
    private OrganizationInvitationRepositoryPort $invitationRepository,
    private FacilityStatisticsPort $facilityStatistics,
    private EquipmentStatisticsPort $equipmentStatistics,
    private InspectionStatisticsPort $inspectionStatistics,
    private NonConformityStatisticsPort $nonConformityStatistics,
    private InterventionStatisticsPort $interventionStatistics,
    private ?CachePort $cache = null,
    private int $cacheTtl = self::DEFAULT_CACHE_TTL_SECONDS,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * Execute the dashboard query and return
   * computed results.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationDashboardQuery $query the dashboard query with filters and options
   *
   * @throws OrganizationNotFoundException if the organization does not exist
   *
   * @return GetOrganizationDashboardResult the computed dashboard data for the organization
   */
  public function __invoke(GetOrganizationDashboardQuery $query): GetOrganizationDashboardResult
  {
    $this->assertDashboardPermissions($query->userId, $query->organizationId);

    $organization = $this->organizationRepository->findById(OrganizationId::fromString($query->organizationId));
    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    // Computed before the cache read so the permission flag can discriminate
    // the cache key: cached payloads must never leak `recentInterventions`
    // across differently-permissioned users. `overview.nonConformities.severity*`
    // (L3.10) needs NO such discriminator: it is gated by
    // `organization.inspection.read`, which is already a hard, non-optional
    // dependency in `OrganizationPermissionCatalog::dashboardReadDependencies()`
    // (asserted above, before this method even runs) — every caller who can
    // reach the cache already holds it, unlike `organization.interventions.read`
    // below, which is deliberately NOT a dashboard dependency.
    $includeInterventions = $this->authorization->hasPermission($query->userId, $query->organizationId, self::INTERVENTIONS_READ_PERMISSION);

    $cacheKey = $this->buildCacheKey($query, $includeInterventions);
    $cached = $this->readCache($cacheKey);
    if ($cached instanceof GetOrganizationDashboardResult) {
      return $cached;
    }

    $generatedAt = new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_DASHBOARD_TIME_ZONE));
    $periodFrom = DashboardDateTimeParser::parseNullable($query->periodFrom, 'from');
    $periodTo = DashboardDateTimeParser::parseNullable($query->periodTo, 'to');
    $dashboardTimeZone = DashboardSeriesBuilder::resolveDashboardTimeZone($query->timeZone, $periodFrom, $periodTo, $generatedAt);
    $generatedAt = $generatedAt->setTimezone($dashboardTimeZone);
    [$periodStart, $periodEnd] = DashboardSeriesBuilder::resolvePeriod($periodFrom, $periodTo, $generatedAt, $dashboardTimeZone);
    DashboardSeriesBuilder::assertSupportedPeriod($periodStart, $periodEnd);
    $comparisonPeriod = $query->compareWithPreviousPeriod ? DashboardSeriesBuilder::resolvePreviousPeriod($periodStart, $periodEnd) : null;

    $organizationId = OrganizationId::fromString($query->organizationId);
    $memberCount = $this->memberRepository->countByOrganizationId($organizationId);
    $activeMemberCount = $this->memberRepository->countActiveByOrganizationId($organizationId);
    $roleCount = $this->roleRepository->countByOrganizationId($organizationId);
    $systemRoleCount = $this->roleRepository->countSystemByOrganizationId($organizationId);
    $invitationCount = $this->invitationRepository->countByOrganizationId($organizationId);
    $invitationCountsByStatus = $this->normalizeBreakdown(
      $this->invitationRepository->countByStatusForOrganizationId($organizationId),
      OrganizationInvitationStatus::cases(),
    );

    $generatedAtFormatted = DashboardSeriesBuilder::formatIso8601($generatedAt);
    $facilityOverview = $this->facilityStatistics->countFacilityOverview($query->organizationId, $query->facilityType);
    $equipmentOverview = $this->equipmentStatistics->countEquipmentOverview($query->organizationId, $query->equipmentType, $query->equipmentStatus);
    $inspectionOverview = $this->inspectionStatistics->countInspectionOverview($query->organizationId, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $nonConformityOverview = $this->nonConformityStatistics->countNonConformityOverview($query->organizationId, $generatedAtFormatted, $query->nonConformitySeverity, $query->nonConformityStatus);
    // L3.10: unconditional (no severity/status filter) org-wide breakdown, sourced
    // from the same port used for `nonConformities.total`/status counts — single
    // extra call, no new port method. Unlike the filtered `nonConformityOverview`
    // above, this always reflects EVERY non-conformity regardless of the
    // `nonConformityStatus`/`nonConformitySeverity` query filters.
    $nonConformitySeverityOverview = $this->nonConformityStatistics->countNonConformitiesBySeverity($query->organizationId);

    $facilityCount = $facilityOverview['total'];
    $activeFacilityCount = $facilityOverview['active'];
    $equipmentTotalCount = $equipmentOverview['total'];
    $inStockEquipmentCount = $equipmentOverview['in_stock'];
    $operationalEquipmentCount = $equipmentOverview['operational'];
    $underMaintenanceEquipmentCount = $equipmentOverview['under_maintenance'];
    $decommissionedEquipmentCount = $equipmentOverview['decommissioned'];
    $inspectionTotalCount = $inspectionOverview['total'];
    $draftInspectionCount = $inspectionOverview['draft'];
    $submittedInspectionCount = $inspectionOverview['submitted'];
    $closedInspectionCount = $inspectionOverview['closed'];
    $passInspectionCount = $inspectionOverview['pass'];
    $failInspectionCount = $inspectionOverview['fail'];
    $partialInspectionCount = $inspectionOverview['partial'];
    $nonConformityTotalCount = $nonConformityOverview['total'];
    $openNonConformityCount = $nonConformityOverview['open'];
    $inProgressNonConformityCount = $nonConformityOverview['in_progress'];
    $doneNonConformityCount = $nonConformityOverview['done'];
    $waivedNonConformityCount = $nonConformityOverview['waived'];
    $overdueNonConformityCount = $nonConformityOverview['overdue'];
    $openCriticalNonConformityCount = $nonConformityOverview['critical_open'];

    $periodStartFormatted = DashboardSeriesBuilder::formatIso8601($periodStart);
    $periodEndFormatted = DashboardSeriesBuilder::formatIso8601($periodEnd);
    $currentPeriodInspectionMetrics = $this->buildInspectionPeriodMetrics($query->organizationId, $periodStart, $periodEnd, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $currentNonConformityPeriodMetrics = $this->buildNonConformityPeriodMetrics($query->organizationId, $periodStart, $periodEnd, $query->nonConformitySeverity, $query->nonConformityStatus);
    $currentNonConformityOpenedCount = $currentNonConformityPeriodMetrics['opened'];
    $currentNonConformityResolvedCount = $currentNonConformityPeriodMetrics['resolved'];
    $currentPeriodHealth = [
      'inspectionCompletionRate' => $this->percentage($currentPeriodInspectionMetrics['closed'], $currentPeriodInspectionMetrics['total']),
      'inspectionPassRate' => $this->percentage($currentPeriodInspectionMetrics['pass'], $currentPeriodInspectionMetrics['pass'] + $currentPeriodInspectionMetrics['fail'] + $currentPeriodInspectionMetrics['partial']),
      'nonConformityResolutionRate' => $this->buildNonConformityPeriodResolutionRate($currentNonConformityOpenedCount, $currentNonConformityResolvedCount, $currentNonConformityPeriodMetrics['activeAtStart']),
    ];

    // Per-day maps are always computed (regardless of the `compare` filter):
    // the dashboard sparklines (`trends`) need them unconditionally, and
    // their sums are reused for the period-over-period comparison "current"
    // values below.
    $facilitiesCreatedByDay = $this->countFacilitiesCreatedByDay($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->facilityType);
    $currentFacilityCreatedCount = DashboardSeriesBuilder::sumSeries($facilitiesCreatedByDay);
    $membersJoinedByDay = $this->memberRepository->countJoinedByDay($organizationId, $periodStart, $periodEnd, $dashboardTimeZone->getName());
    $currentMemberJoinedCount = null !== $comparisonPeriod
      ? $this->countMembersJoinedBetween($organizationId, $periodStart, $periodEnd)
      : 0;
    $equipmentCreatedByDay = $this->countEquipmentCreatedByDay($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->equipmentType, $query->equipmentStatus);
    $currentEquipmentCreatedCount = DashboardSeriesBuilder::sumSeries($equipmentCreatedByDay);
    $inspectionsPerformedByDay = $this->countInspectionsPerformedByDay($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);

    $overview = [
      'members' => [
        'total' => $memberCount,
        'active' => $activeMemberCount,
        'inactive' => max(0, $memberCount - $activeMemberCount),
      ],
      'roles' => [
        'total' => $roleCount,
        'system' => $systemRoleCount,
        'custom' => max(0, $roleCount - $systemRoleCount),
      ],
      'invitations' => [
        'total' => $invitationCount,
        'pending' => $invitationCountsByStatus['pending'] ?? 0,
        'accepted' => $invitationCountsByStatus['accepted'] ?? 0,
        'revoked' => $invitationCountsByStatus['revoked'] ?? 0,
        'expired' => $invitationCountsByStatus['expired'] ?? 0,
      ],
      'facilities' => [
        'total' => $facilityCount,
        'active' => $activeFacilityCount,
        'archived' => max(0, $facilityCount - $activeFacilityCount),
      ],
      'equipment' => [
        'total' => $equipmentTotalCount,
        'inStock' => $inStockEquipmentCount,
        'operational' => $operationalEquipmentCount,
        'underMaintenance' => $underMaintenanceEquipmentCount,
        'decommissioned' => $decommissionedEquipmentCount,
      ],
      'inspections' => [
        'total' => $inspectionTotalCount,
        'draft' => $draftInspectionCount,
        'submitted' => $submittedInspectionCount,
        'closed' => $closedInspectionCount,
        'pass' => $passInspectionCount,
        'fail' => $failInspectionCount,
        'partial' => $partialInspectionCount,
      ],
      'nonConformities' => [
        'total' => $nonConformityTotalCount,
        'open' => $openNonConformityCount,
        'inProgress' => $inProgressNonConformityCount,
        'done' => $doneNonConformityCount,
        'waived' => $waivedNonConformityCount,
        'overdue' => $overdueNonConformityCount,
        'criticalOpen' => $openCriticalNonConformityCount,
        'severityLow' => $nonConformitySeverityOverview['low'] ?? 0,
        'severityMedium' => $nonConformitySeverityOverview['medium'] ?? 0,
        'severityHigh' => $nonConformitySeverityOverview['high'] ?? 0,
        'severityCritical' => $nonConformitySeverityOverview['critical'] ?? 0,
      ],
    ];
    $health = ['memberActivationRate' => $this->percentage($activeMemberCount, $memberCount), 'inspectionCompletionRate' => $this->percentage($closedInspectionCount, $inspectionTotalCount), 'inspectionPassRate' => $this->percentage($passInspectionCount, $passInspectionCount + $failInspectionCount + $partialInspectionCount), 'equipmentAvailabilityRate' => $this->percentage($operationalEquipmentCount, max(0, $equipmentTotalCount - $decommissionedEquipmentCount)), 'nonConformityResolutionRate' => $this->percentage($doneNonConformityCount + $waivedNonConformityCount, $nonConformityTotalCount), 'periodInspectionCompletionRate' => $currentPeriodHealth['inspectionCompletionRate'], 'periodInspectionPassRate' => $currentPeriodHealth['inspectionPassRate'], 'periodNonConformityResolutionRate' => $currentPeriodHealth['nonConformityResolutionRate']];
    $alerts = [];
    if ($openCriticalNonConformityCount > 0) {
      $alerts[] = ['code' => 'critical_non_conformities_open', 'severity' => 'high', 'count' => $openCriticalNonConformityCount];
    }
    if ($overdueNonConformityCount > 0) {
      $alerts[] = ['code' => 'non_conformities_overdue', 'severity' => 'high', 'count' => $overdueNonConformityCount];
    }
    if (($invitationCountsByStatus['expired'] ?? 0) > 0) {
      $alerts[] = ['code' => 'expired_invitations', 'severity' => 'medium', 'count' => $invitationCountsByStatus['expired']];
    }
    if ($underMaintenanceEquipmentCount > 0) {
      $alerts[] = ['code' => 'equipment_under_maintenance', 'severity' => 'medium', 'count' => $underMaintenanceEquipmentCount];
    }

    $trends = [
      'facilities' => $this->buildRunningTotalSeries($facilitiesCreatedByDay, $facilityCount, $periodStart, $periodEnd, $dashboardTimeZone),
      'members' => $this->buildRunningTotalSeries($membersJoinedByDay, $memberCount, $periodStart, $periodEnd, $dashboardTimeZone),
      'equipment' => $this->buildRunningTotalSeries($equipmentCreatedByDay, $equipmentTotalCount, $periodStart, $periodEnd, $dashboardTimeZone),
      'inspections' => $this->buildRunningTotalSeries($inspectionsPerformedByDay, $inspectionTotalCount, $periodStart, $periodEnd, $dashboardTimeZone),
    ];

    $recentInterventions = $includeInterventions
      ? $this->buildRecentInterventions($query->organizationId, $organizationId)
      : [];

    $result = new GetOrganizationDashboardResult(
      generatedAt: $generatedAtFormatted,
      period: [
        'from' => $periodStartFormatted,
        'to' => $periodEndFormatted,
        'comparison' => null !== $comparisonPeriod ? 'previous_period' : 'none',
        'timezone' => $dashboardTimeZone->getName(),
      ],
      overview: $overview,
      health: $health,
      alerts: $alerts,
      comparison: $this->buildComparison(
        $query,
        $currentPeriodInspectionMetrics['total'],
        $currentFacilityCreatedCount,
        $currentMemberJoinedCount,
        $currentEquipmentCreatedCount,
        $currentNonConformityOpenedCount,
        $currentNonConformityResolvedCount,
        $currentPeriodHealth,
        $comparisonPeriod,
      ),
      trends: $trends,
      recentInterventions: $recentInterventions,
    );
    $this->writeCache($cacheKey, $result);

    return $result;
  }

  /**
   * Method assertDashboardPermissions.
   *
   * Check if the user has necessary permissions to access the dashboard.
   *
   * @since 1.0.0
   *
   * @param string $userId the ID of the user requesting the dashboard
   * @param string $organizationId the ID of the organization for which the dashboard is requested
   *
   * @throws OrganizationAccessDeniedException if the user lacks any required permission
   *
   * @return void No return value. Throws exception if access is denied.
   */
  private function assertDashboardPermissions(string $userId, string $organizationId): void
  {
    $this->authorization->assertGrantedPermissions(
      $userId,
      $organizationId,
      OrganizationPermissionCatalog::dashboardReadDependencies(),
    );
  }

  private function buildCacheKey(GetOrganizationDashboardQuery $query, bool $includeInterventions): string
  {
    try {
      $payload = json_encode([
        'organizationId' => $query->organizationId,
        'periodFrom' => $query->periodFrom,
        'periodTo' => $query->periodTo,
        'compareWithPreviousPeriod' => $query->compareWithPreviousPeriod,
        'timeZone' => $query->timeZone,
        'facilityType' => $query->facilityType,
        'equipmentType' => $query->equipmentType,
        'equipmentStatus' => $query->equipmentStatus,
        'inspectionStatus' => $query->inspectionStatus,
        'inspectionResult' => $query->inspectionResult,
        'inspectorType' => $query->inspectorType,
        'nonConformityStatus' => $query->nonConformityStatus,
        'nonConformitySeverity' => $query->nonConformitySeverity,
        'includeInterventions' => $includeInterventions,
      ], JSON_THROW_ON_ERROR);
    } catch (Throwable) {
      $payload = $query->organizationId;
    }

    return 'organization.dashboard.' . hash('sha256', $payload);
  }

  private function readCache(string $cacheKey): ?GetOrganizationDashboardResult
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    try {
      $cached = $this->cache->get($cacheKey);
    } catch (Throwable) {
      return null;
    }

    return $cached instanceof GetOrganizationDashboardResult ? $cached : null;
  }

  private function writeCache(string $cacheKey, GetOrganizationDashboardResult $result): void
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return;
    }

    try {
      $this->cache->set($cacheKey, $result, $this->cacheTtl);
    } catch (Throwable) {
      // Dashboard cache failures should not block fresh dashboard generation.
    }
  }

  /**
   * Method percentage.
   *
   * Calculate a percentage value with safe
   * division and rounding.
   *
   * @since 1.0.0
   *
   * @param int $numerator the numerator for the percentage calculation
   * @param int $denominator the denominator for the percentage calculation
   *
   * @return float the calculated percentage, rounded to 2 decimal places. Returns 0.0
   *               if denominator is zero or negative to avoid division errors.
   */
  private function percentage(int $numerator, int $denominator): float
  {
    return $denominator <= 0 ? 0.0 : round(($numerator / $denominator) * 100, 2);
  }

  /**
   * Method buildInspectionPeriodMetrics.
   *
   * Calculate inspection metrics for a given period and optional filters,
   * including total count, closed count, pass count, fail count, and partial count.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the ID of the organization for which to calculate metrics
   * @param DateTimeImmutable $periodStart the start datetime of the period for which to calculate metrics
   * @param DateTimeImmutable $periodEnd the end datetime of the period for which to calculate metrics
   * @param string|null $status an optional filter for inspection status to include in the metrics calculation
   * @param string|null $result an optional filter for inspection result to include in the metrics calculation
   * @param string|null $inspectorType an optional filter for inspector type to include in the metrics calculation
   *
   * @return array{total: int, closed: int, pass: int, fail: int, partial: int} an array containing the
   *                                                                            calculated inspection metrics for the specified period and filters
   */
  private function buildInspectionPeriodMetrics(
    string $organizationId,
    DateTimeImmutable $periodStart,
    DateTimeImmutable $periodEnd,
    ?string $status = null,
    ?string $result = null,
    ?string $inspectorType = null,
  ): array {
    return $this->inspectionStatistics->countInspectionPeriodMetrics(
      $organizationId,
      DashboardSeriesBuilder::formatIso8601($periodStart),
      DashboardSeriesBuilder::formatIso8601($periodEnd),
      $status,
      $result,
      $inspectorType,
    );
  }

  /**
   * @return array{opened: int, resolved: int, activeAtStart: int}
   */
  private function buildNonConformityPeriodMetrics(
    string $organizationId,
    DateTimeImmutable $periodStart,
    DateTimeImmutable $periodEnd,
    ?string $severity = null,
    ?string $status = null,
  ): array {
    $periodStartFormatted = DashboardSeriesBuilder::formatIso8601($periodStart);

    return $this->nonConformityStatistics->countNonConformityPeriodMetrics(
      $organizationId,
      $periodStartFormatted,
      DashboardSeriesBuilder::formatIso8601($periodEnd),
      $periodStartFormatted,
      $severity,
      $status,
    );
  }

  private function buildNonConformityPeriodResolutionRate(
    int $openedCount,
    int $resolvedCount,
    int $activeAtStartCount,
  ): float {
    return $this->percentage($resolvedCount, $activeAtStartCount + $openedCount);
  }

  /**
   * Method buildComparison.
   *
   * Construct a comparison dataset between the current period and a
   * previous period for key metrics and health indicators.
   *
   * @since 1.0.0
   *
   * @param GetOrganizationDashboardQuery $query the original dashboard query containing filters and parameters for the comparison
   * @param int $currentInspectionCount the number of inspections performed during the current period
   * @param int $currentFacilityCreatedCount the number of facilities created during the current period
   * @param int $currentMemberJoinedCount the number of members who joined during the current period
   * @param int $currentEquipmentCreatedCount the number of equipment records created during the current period
   * @param int $currentNonConformityOpenedCount the number of non-conformities opened during the current period
   * @param int $currentNonConformityResolvedCount the number of non-conformities resolved during the current period
   * @param array<string, float> $currentPeriodHealth the health metrics for the current period, indexed by metric name
   * @param ?array{from: DateTimeImmutable, to: DateTimeImmutable} $comparisonPeriod the period to compare against, if any
   *
   * @return array<string, mixed> an array containing the comparison results, including mode,
   *                              current and previous metrics, deltas, and health indicators. If no comparison period
   *                              is provided, the mode will be 'none' and metric values will be empty.
   */
  private function buildComparison(GetOrganizationDashboardQuery $query, int $currentInspectionCount, int $currentFacilityCreatedCount, int $currentMemberJoinedCount, int $currentEquipmentCreatedCount, int $currentNonConformityOpenedCount, int $currentNonConformityResolvedCount, array $currentPeriodHealth, ?array $comparisonPeriod): array
  {
    if (null === $comparisonPeriod) {
      return ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]];
    }
    $from = DashboardSeriesBuilder::formatIso8601($comparisonPeriod['from']);
    $to = DashboardSeriesBuilder::formatIso8601($comparisonPeriod['to']);
    $comparisonTimeZone = $comparisonPeriod['from']->getTimezone()->getName();
    $comparisonOrganizationId = OrganizationId::fromString($query->organizationId);
    $previousFacilityCreatedCount = $this->countPeriodFacilitiesCreated($query->organizationId, $from, $to, $comparisonTimeZone, $query->facilityType);
    $previousMemberJoinedCount = $this->countMembersJoinedBetween($comparisonOrganizationId, $comparisonPeriod['from'], $comparisonPeriod['to']);
    $previousEquipmentCreatedCount = $this->countPeriodEquipmentCreated($query->organizationId, $from, $to, $comparisonTimeZone, $query->equipmentType, $query->equipmentStatus);
    $previousNonConformityPeriodMetrics = $this->buildNonConformityPeriodMetrics($query->organizationId, $comparisonPeriod['from'], $comparisonPeriod['to'], $query->nonConformitySeverity, $query->nonConformityStatus);
    $previousNonConformityOpenedCount = $previousNonConformityPeriodMetrics['opened'];
    $previousNonConformityResolvedCount = $previousNonConformityPeriodMetrics['resolved'];
    $previousPeriodInspectionMetrics = $this->buildInspectionPeriodMetrics($query->organizationId, $comparisonPeriod['from'], $comparisonPeriod['to'], $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $previousPeriodHealth = ['inspectionCompletionRate' => $this->percentage($previousPeriodInspectionMetrics['closed'], $previousPeriodInspectionMetrics['total']), 'inspectionPassRate' => $this->percentage($previousPeriodInspectionMetrics['pass'], $previousPeriodInspectionMetrics['pass'] + $previousPeriodInspectionMetrics['fail'] + $previousPeriodInspectionMetrics['partial']), 'nonConformityResolutionRate' => $this->buildNonConformityPeriodResolutionRate($previousNonConformityOpenedCount, $previousNonConformityResolvedCount, $previousNonConformityPeriodMetrics['activeAtStart'])];
    $current = [
      'inspectionsPerformed' => $currentInspectionCount,
      'facilitiesCreated' => $currentFacilityCreatedCount,
      'membersJoined' => $currentMemberJoinedCount,
      'equipmentCreated' => $currentEquipmentCreatedCount,
      'nonConformitiesOpened' => $currentNonConformityOpenedCount,
      'nonConformitiesResolved' => $currentNonConformityResolvedCount,
    ];
    $previous = [
      'inspectionsPerformed' => $previousPeriodInspectionMetrics['total'],
      'facilitiesCreated' => $previousFacilityCreatedCount,
      'membersJoined' => $previousMemberJoinedCount,
      'equipmentCreated' => $previousEquipmentCreatedCount,
      'nonConformitiesOpened' => $previousNonConformityOpenedCount,
      'nonConformitiesResolved' => $previousNonConformityResolvedCount,
    ];

    return ['mode' => 'previous_period', 'from' => $from, 'to' => $to, 'current' => $current, 'previous' => $previous, 'deltas' => ['inspectionsPerformed' => DashboardSeriesBuilder::relativeDelta($current['inspectionsPerformed'], $previous['inspectionsPerformed']), 'facilitiesCreated' => DashboardSeriesBuilder::relativeDelta($current['facilitiesCreated'], $previous['facilitiesCreated']), 'membersJoined' => DashboardSeriesBuilder::relativeDelta($current['membersJoined'], $previous['membersJoined']), 'equipmentCreated' => DashboardSeriesBuilder::relativeDelta($current['equipmentCreated'], $previous['equipmentCreated']), 'nonConformitiesOpened' => DashboardSeriesBuilder::relativeDelta($current['nonConformitiesOpened'], $previous['nonConformitiesOpened']), 'nonConformitiesResolved' => DashboardSeriesBuilder::relativeDelta($current['nonConformitiesResolved'], $previous['nonConformitiesResolved'])], 'health' => ['current' => $currentPeriodHealth, 'previous' => $previousPeriodHealth, 'deltas' => ['inspectionCompletionRate' => $this->relativeDeltaFloat($currentPeriodHealth['inspectionCompletionRate'], $previousPeriodHealth['inspectionCompletionRate']), 'inspectionPassRate' => $this->relativeDeltaFloat($currentPeriodHealth['inspectionPassRate'], $previousPeriodHealth['inspectionPassRate']), 'nonConformityResolutionRate' => $this->relativeDeltaFloat($currentPeriodHealth['nonConformityResolutionRate'], $previousPeriodHealth['nonConformityResolutionRate'])]]];
  }

  /**
   * Method relativeDeltaFloat.
   *
   * Calculate the relative percentage change between two float values,
   * handling division by zero and rounding.
   *
   * @since 1.0.0
   *
   * @param float $current the current value for which to calculate the delta
   * @param float $previous the previous value to compare against for the delta calculation
   *
   * @return float the calculated relative delta as a percentage, rounded to 2 decimal
   *               places. If the previous value is zero, returns 100.0 if the current value is greater
   *               than zero, or 0.0 otherwise.
   */
  private function relativeDeltaFloat(float $current, float $previous): float
  {
    return 0.0 === $previous ? ($current > 0.0 ? 100.0 : 0.0) : round((($current - $previous) / $previous) * 100, 2);
  }

  /**
   * Method buildRunningTotalSeries.
   *
   * Builds a per-day running-total sparkline series from a by-day
   * creation/occurrence map, anchored on the CURRENT KPI total. Walks
   * the period backward from the last day to the first, subtracting
   * each day's count so that `value(bucket b) = anchorTotal -
   * sum(byDayMap[b+1..periodEnd])`, clamped at zero.
   *
   * This is exact when the period ends at (or near) "now" (the default
   * dashboard window), because the anchor IS the current total. For an
   * explicitly historical window (a `to` in the past), the anchor still
   * reflects the CURRENT total, so the series is an approximation of
   * what the historical totals actually were at each bucket.
   *
   * @since 1.0.0
   *
   * @param array<string, int> $byDayMap map of YYYY-MM-DD => count created/occurred that day
   * @param int $anchorTotal the current KPI total the series is anchored on
   * @param DateTimeImmutable $periodStart the inclusive period start
   * @param DateTimeImmutable $periodEnd the inclusive period end
   * @param DateTimeZone $timeZone the timezone used to enumerate day buckets
   *
   * @return list<array{bucket: string, value: int}> one point per day, in chronological order
   */
  private function buildRunningTotalSeries(array $byDayMap, int $anchorTotal, DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, DateTimeZone $timeZone): array
  {
    $days = [];
    for (
      $cursor = $periodStart->setTimezone($timeZone)->setTime(0, 0),
      $lastDay = $periodEnd->setTimezone($timeZone)->setTime(0, 0);
      $cursor <= $lastDay;
      $cursor = $cursor->add(new DateInterval('P1D'))
    ) {
      $days[] = $cursor->format('Y-m-d');
    }

    $cumulativeAfter = 0;
    $valueByDay = [];
    for ($index = count($days) - 1; $index >= 0; --$index) {
      $day = $days[$index];
      $valueByDay[$day] = max(0, $anchorTotal - $cumulativeAfter);
      $cumulativeAfter += $byDayMap[$day] ?? 0;
    }

    $series = [];
    foreach ($days as $day) {
      $series[] = ['bucket' => $day, 'value' => $valueByDay[$day]];
    }

    return $series;
  }

  /**
   * Method buildRecentInterventions.
   *
   * Resolves the 5 most recently updated organization interventions,
   * enriched with the assigned facility name and the responsible
   * member's resolved user identifier (the display name and avatar are
   * resolved by the Presentation provider, which alone talks to the
   * User module).
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param OrganizationId $organizationIdValueObject the organization identifier value object
   *
   * @return list<array{
   *   id: string,
   *   number: int,
   *   name: string,
   *   status: string,
   *   priority: string,
   *   siteId: ?string,
   *   siteName: ?string,
   *   responsibleId: ?string,
   *   responsibleUserId: ?string,
   *   dueAt: ?string,
   *   updatedAt: string,
   * }>
   */
  private function buildRecentInterventions(string $organizationId, OrganizationId $organizationIdValueObject): array
  {
    /** @var list<RecentInterventionSummary> $summaries */
    $summaries = $this->interventionStatistics->findRecentInterventions($organizationId, self::RECENT_INTERVENTIONS_LIMIT);
    if ([] === $summaries) {
      return [];
    }

    $siteIds = [];
    $memberIds = [];
    foreach ($summaries as $summary) {
      if (null !== $summary->siteId) {
        $siteIds[$summary->siteId] = true;
      }
      if (null !== $summary->responsibleMemberId) {
        $memberIds[$summary->responsibleMemberId] = true;
      }
    }

    $siteNames = [] !== $siteIds ? $this->facilityStatistics->getFacilityNamesByIds($organizationId, array_keys($siteIds)) : [];
    $userIdsByMemberId = [] !== $memberIds ? $this->memberRepository->findUserIdsByMemberIds($organizationIdValueObject, array_keys($memberIds)) : [];

    $rows = [];
    foreach ($summaries as $summary) {
      $rows[] = [
        'id' => $summary->id,
        'number' => $summary->number,
        'name' => $summary->name,
        'status' => $summary->status,
        'priority' => $summary->priority,
        'siteId' => $summary->siteId,
        'siteName' => null !== $summary->siteId ? ($siteNames[$summary->siteId] ?? null) : null,
        'responsibleId' => $summary->responsibleMemberId,
        'responsibleUserId' => null !== $summary->responsibleMemberId ? ($userIdsByMemberId[$summary->responsibleMemberId] ?? null) : null,
        'dueAt' => null !== $summary->dueAt ? DashboardSeriesBuilder::formatIso8601($summary->dueAt) : null,
        'updatedAt' => DashboardSeriesBuilder::formatIso8601($summary->updatedAt),
      ];
    }

    return $rows;
  }

  /**
   * Method normalizeBreakdown.
   *
   * Normalize a breakdown of counts by enum cases, ensuring that all cases are
   * represented with a count, even if zero. This is useful for ensuring consistent
   * output in dashboard metrics where certain categories
   * may have no occurrences.
   *
   * @since 1.0.0
   *
   * @param array<int|string, int> $counts an associative array of counts indexed by enum
   *                                       case values, which may be incomplete and missing some cases
   * @param list<BackedEnum> $cases a list of all possible enum cases that should be
   *                                included in the normalized breakdown, ensuring that any missing cases in the
   *                                counts are filled with a count of zero
   *
   * @return array<int|string, int> an associative array where keys are enum case values
   *                                and values are the corresponding counts, with all cases from the provided list
   *                                included and missing cases filled with a count of zero
   */
  private function normalizeBreakdown(array $counts, array $cases): array
  {
    /** @var array<int|string, int> $normalized */
    $normalized = [];
    foreach ($cases as $case) {
      /** @var BackedEnum $case */
      $normalized[$case->value] = (int) ($counts[$case->value] ?? 0);
    }

    return $normalized;
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

  private function countPeriodFacilitiesCreated(string $organizationId, string $createdAtFrom, string $createdAtTo, string $timeZone, ?string $type = null): int
  {
    return DashboardSeriesBuilder::sumSeries($this->countFacilitiesCreatedByDay(
      $organizationId,
      $createdAtFrom,
      $createdAtTo,
      $timeZone,
      $type,
    ));
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

  private function countPeriodEquipmentCreated(string $organizationId, string $createdAtFrom, string $createdAtTo, string $timeZone, ?string $type = null, ?string $status = null): int
  {
    return DashboardSeriesBuilder::sumSeries($this->countEquipmentCreatedByDay(
      $organizationId,
      $createdAtFrom,
      $createdAtTo,
      $timeZone,
      $type,
      $status,
    ));
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

  private function countMembersJoinedBetween(OrganizationId $organizationId, DateTimeImmutable $joinedAtFrom, DateTimeImmutable $joinedAtTo): int
  {
    return $this->memberRepository->countJoinedBetween(
      $organizationId,
      $this->normalizeMemberTimestampForStorage($joinedAtFrom),
      $this->normalizeMemberTimestampForStorage($joinedAtTo),
    );
  }

  private function normalizeMemberTimestampForStorage(DateTimeImmutable $value): DateTimeImmutable
  {
    return $value->setTimezone(new DateTimeZone(self::STORAGE_TIME_ZONE));
  }

  // #endregion
}
