<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, NonConformityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Support\DashboardDateTimeParser;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationStatus};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

use function array_sum;
use function hash;
use function in_array;
use function json_encode;
use function max;
use function round;
use function timezone_identifiers_list;

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
   * Constant MAX_TREND_PERIOD_DAYS.
   *
   * Maximum number of days allowed for dashboard trend periods
   * to prevent performance issues.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DEFAULT_TREND_PERIOD_DAYS = 30;

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

  /**
   * Constant MAX_TREND_PERIOD_DAYS.
   *
   * Maximum number of days allowed for dashboard trend
   * periods to prevent performance issues.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_TREND_PERIOD_DAYS = 366;

  private const int DEFAULT_CACHE_TTL_SECONDS = 30;
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

    $cacheKey = $this->buildCacheKey($query);
    $cached = $this->readCache($cacheKey);
    if ($cached instanceof GetOrganizationDashboardResult) {
      return $cached;
    }

    $generatedAt = new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_DASHBOARD_TIME_ZONE));
    $periodFrom = DashboardDateTimeParser::parseNullable($query->periodFrom, 'from');
    $periodTo = DashboardDateTimeParser::parseNullable($query->periodTo, 'to');
    $dashboardTimeZone = $this->resolveDashboardTimeZone($query->timeZone, $periodFrom, $periodTo, $generatedAt);
    $generatedAt = $generatedAt->setTimezone($dashboardTimeZone);
    [$periodStart, $periodEnd] = $this->resolvePeriod($periodFrom, $periodTo, $generatedAt, $dashboardTimeZone);
    $this->assertSupportedPeriod($periodStart, $periodEnd);
    $comparisonPeriod = $query->compareWithPreviousPeriod ? $this->resolvePreviousPeriod($periodStart, $periodEnd) : null;

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

    $generatedAtFormatted = $this->formatIso8601($generatedAt);
    $facilityOverview = $this->facilityStatistics->countFacilityOverview($query->organizationId, $query->facilityType);
    $equipmentOverview = $this->equipmentStatistics->countEquipmentOverview($query->organizationId, $query->equipmentType, $query->equipmentStatus);
    $inspectionOverview = $this->inspectionStatistics->countInspectionOverview($query->organizationId, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $nonConformityOverview = $this->nonConformityStatistics->countNonConformityOverview($query->organizationId, $generatedAtFormatted, $query->nonConformitySeverity, $query->nonConformityStatus);

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

    $periodStartFormatted = $this->formatIso8601($periodStart);
    $periodEndFormatted = $this->formatIso8601($periodEnd);
    $currentPeriodInspectionMetrics = $this->buildInspectionPeriodMetrics($query->organizationId, $periodStart, $periodEnd, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $currentNonConformityPeriodMetrics = $this->buildNonConformityPeriodMetrics($query->organizationId, $periodStart, $periodEnd, $query->nonConformitySeverity, $query->nonConformityStatus);
    $currentNonConformityOpenedCount = $currentNonConformityPeriodMetrics['opened'];
    $currentNonConformityResolvedCount = $currentNonConformityPeriodMetrics['resolved'];
    $currentPeriodHealth = [
      'inspectionCompletionRate' => $this->percentage($currentPeriodInspectionMetrics['closed'], $currentPeriodInspectionMetrics['total']),
      'inspectionPassRate' => $this->percentage($currentPeriodInspectionMetrics['pass'], $currentPeriodInspectionMetrics['pass'] + $currentPeriodInspectionMetrics['fail'] + $currentPeriodInspectionMetrics['partial']),
      'nonConformityResolutionRate' => $this->buildNonConformityPeriodResolutionRate($currentNonConformityOpenedCount, $currentNonConformityResolvedCount, $currentNonConformityPeriodMetrics['activeAtStart']),
    ];
    $currentFacilityCreatedCount = null !== $comparisonPeriod
      ? $this->countPeriodFacilitiesCreated($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->facilityType)
      : 0;
    $currentMemberJoinedCount = null !== $comparisonPeriod
      ? $this->countMembersJoinedBetween($organizationId, $periodStart, $periodEnd)
      : 0;
    $currentEquipmentCreatedCount = null !== $comparisonPeriod
      ? $this->countPeriodEquipmentCreated($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->equipmentType, $query->equipmentStatus)
      : 0;

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

  private function buildCacheKey(GetOrganizationDashboardQuery $query): string
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
   * Method resolveDashboardTimeZone.
   *
   * Determine the appropriate time zone for dashboard data
   * aggregation based on provided filters and defaults.
   *
   * @since 1.0.0
   *
   * @param string|null $timeZone the optional time zone filter provided in the query
   * @param DateTimeImmutable|null $periodFrom the optional "from" datetime filter
   * @param DateTimeImmutable|null $periodTo the optional "to" datetime filter
   * @param DateTimeImmutable $fallbackNow the current datetime to use as a
   *                                       fallback reference for time zone resolution
   *
   * @throws InvalidArgumentException if the provided "timezone" filter is invalid or
   *                                  if there are mixed time zones without an explicit "timezone" filter
   *
   * @return DateTimeZone the resolved time zone to be used for dashboard data aggregation
   */
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

  /**
   * Method normalizeImplicitDashboardTimeZone.
   *
   * Normalize a time zone for dashboard use when no explicit "timezone"
   * filter is provided, ensuring it is either a valid IANA timezone
   * or defaults to UTC if it has a zero offset.
   *
   * @since 1.0.0
   *
   * @param DateTimeZone $timeZone the time zone to normalize
   * @param DateTimeImmutable $reference the reference datetime to determine the offset of the time
   *
   * @throws InvalidArgumentException if the time zone is not a valid
   *                                  IANA timezone and does not have a zero offset (UTC)
   *
   * @return DateTimeZone the normalized time zone to be used for dashboard data aggregation
   */
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
   * Method resolvePeriod.
   *
   * Determine the start and end datetimes for the dashboard
   * period based on provided filters and defaults.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable|null $periodFrom the optional "from" datetime filter
   * @param DateTimeImmutable|null $periodTo the optional "to" datetime filter
   * @param DateTimeImmutable $now the current datetime to use as a reference
   *                               for default period calculation
   * @param DateTimeZone $dashboardTimeZone the time zone to use for period calculation
   *
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} an array containing the resolved period
   *                                                           start and end datetimes, both set to the dashboard time zone
   */
  private function resolvePeriod(?DateTimeImmutable $periodFrom, ?DateTimeImmutable $periodTo, DateTimeImmutable $now, DateTimeZone $dashboardTimeZone): array
  {
    $periodEnd = ($periodTo ?? $now)->setTimezone($dashboardTimeZone);
    $periodStart = null !== $periodFrom ? $periodFrom->setTimezone($dashboardTimeZone) : $periodEnd->sub(new DateInterval('P' . (self::DEFAULT_TREND_PERIOD_DAYS - 1) . 'D'))->setTime(0, 0);

    return [$periodStart, $periodEnd];
  }

  /**
   * Method assertSupportedPeriod.
   *
   * Validate that the provided period start and end datetimes
   * are in a supported range and order for dashboard generation.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the start datetime of the period to validate
   * @param DateTimeImmutable $periodEnd the end datetime of the period to validate
   *
   * @throws InvalidArgumentException if the "from" datetime is after the "to" datetime
   *                                  or if the period exceeds the maximum allowed range
   *
   * @return void No return value. Throws exception if the period is invalid.
   */
  private function assertSupportedPeriod(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): void
  {
    if ($periodStart->getTimestamp() > $periodEnd->getTimestamp()) {
      throw new InvalidArgumentException('The "from" datetime filter must be before or equal to "to".');
    }
    if ((int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1 > self::MAX_TREND_PERIOD_DAYS) {
      throw new InvalidArgumentException('Dashboard period cannot exceed 366 days.');
    }
  }

  /**
   * Method resolvePreviousPeriod.
   *
   * Calculate the previous period's start and end datetimes based on the current
   * period, ensuring the same duration and time zone.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the start datetime of the current period
   * @param DateTimeImmutable $periodEnd the end datetime of the current period
   *
   * @return array{from: DateTimeImmutable, to: DateTimeImmutable} an array containing
   *                                                               the 'from' and 'to' datetimes for the previous period, both
   *                                                               set to the dashboard time zone
   */
  private function resolvePreviousPeriod(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): array
  {
    $shift = new DateInterval('P' . max(1, (int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1) . 'D');

    return ['from' => $periodStart->sub($shift), 'to' => $periodEnd->sub($shift)];
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
      $this->formatIso8601($periodStart),
      $this->formatIso8601($periodEnd),
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
    $periodStartFormatted = $this->formatIso8601($periodStart);

    return $this->nonConformityStatistics->countNonConformityPeriodMetrics(
      $organizationId,
      $periodStartFormatted,
      $this->formatIso8601($periodEnd),
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
    $from = $this->formatIso8601($comparisonPeriod['from']);
    $to = $this->formatIso8601($comparisonPeriod['to']);
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

    return ['mode' => 'previous_period', 'from' => $from, 'to' => $to, 'current' => $current, 'previous' => $previous, 'deltas' => ['inspectionsPerformed' => $this->relativeDelta($current['inspectionsPerformed'], $previous['inspectionsPerformed']), 'facilitiesCreated' => $this->relativeDelta($current['facilitiesCreated'], $previous['facilitiesCreated']), 'membersJoined' => $this->relativeDelta($current['membersJoined'], $previous['membersJoined']), 'equipmentCreated' => $this->relativeDelta($current['equipmentCreated'], $previous['equipmentCreated']), 'nonConformitiesOpened' => $this->relativeDelta($current['nonConformitiesOpened'], $previous['nonConformitiesOpened']), 'nonConformitiesResolved' => $this->relativeDelta($current['nonConformitiesResolved'], $previous['nonConformitiesResolved'])], 'health' => ['current' => $currentPeriodHealth, 'previous' => $previousPeriodHealth, 'deltas' => ['inspectionCompletionRate' => $this->relativeDeltaFloat($currentPeriodHealth['inspectionCompletionRate'], $previousPeriodHealth['inspectionCompletionRate']), 'inspectionPassRate' => $this->relativeDeltaFloat($currentPeriodHealth['inspectionPassRate'], $previousPeriodHealth['inspectionPassRate']), 'nonConformityResolutionRate' => $this->relativeDeltaFloat($currentPeriodHealth['nonConformityResolutionRate'], $previousPeriodHealth['nonConformityResolutionRate'])]]];
  }

  /**
   * Method relativeDelta.
   *
   * Calculate the relative percentage change between two integer
   * values, handling division by zero and rounding.
   *
   * @since 1.0.0
   *
   * @param int $current the current value for which to calculate the delta
   * @param int $previous the previous value to compare against for the delta calculation
   *
   * @return float the calculated relative delta as a percentage, rounded to 2 decimal
   *               places. If the previous value is zero, returns 100.0 if the current value is greater than zero, or 0.0 otherwise.
   */
  private function relativeDelta(int $current, int $previous): float
  {
    return 0 === $previous ? ($current > 0 ? 100.0 : 0.0) : round((($current - $previous) / $previous) * 100, 2);
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
   * Method sumSeries.
   *
   * Calculate the sum of values in a series represented
   * as an associative array, where keys are bucket identifiers
   * and values are counts.
   *
   * @since 1.0.0
   *
   * @param array<string, int> $series the series of counts to sum,
   *                                   indexed by bucket identifiers
   *
   * @return int the total sum of the counts in the series
   */
  private function sumSeries(array $series): int
  {
    return (int) array_sum($series);
  }

  /**
   * Method formatIso8601.
   *
   * Format a DateTimeImmutable value into an ISO 8601
   * string with microsecond precision only if necessary.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $value the datetime value to format
   *
   * @return string the formatted ISO 8601 string representation of
   *                the datetime value, including microseconds if they are not zero
   */
  private function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u') ? $value->format('Y-m-d\\TH:i:sP') : $value->format('Y-m-d\\TH:i:s.uP');
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
    return $this->sumSeries($this->countFacilitiesCreatedByDay(
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
    return $this->sumSeries($this->countEquipmentCreatedByDay(
      $organizationId,
      $createdAtFrom,
      $createdAtTo,
      $timeZone,
      $type,
      $status,
    ));
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
