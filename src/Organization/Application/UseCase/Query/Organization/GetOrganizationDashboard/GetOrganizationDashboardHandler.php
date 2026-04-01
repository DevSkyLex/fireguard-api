<?php

declare(strict_types=1);

namespace Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use BackedEnum;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Equipment\Domain\ValueObject\{EquipmentStatus, EquipmentType};
use Facility\Domain\ValueObject\FacilityType;
use Inspection\Domain\ValueObject\{InspectionResult, InspectionStatus, InspectorType, NonConformitySeverity, NonConformityStatus};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, NonConformityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Support\DashboardDateTimeParser;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationStatus};
use Shared\Application\Message\QueryHandler;

use function array_sum;
use function in_array;
use function max;
use function round;
use function timezone_identifiers_list;

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

    $generatedAt = new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_DASHBOARD_TIME_ZONE));
    $periodFrom = DashboardDateTimeParser::parseNullable($query->periodFrom, 'from');
    $periodTo = DashboardDateTimeParser::parseNullable($query->periodTo, 'to');
    $dashboardTimeZone = $this->resolveDashboardTimeZone($query->timeZone, $periodFrom, $periodTo, $generatedAt);
    $generatedAt = $generatedAt->setTimezone($dashboardTimeZone);
    [$periodStart, $periodEnd] = $this->resolvePeriod($periodFrom, $periodTo, $generatedAt, $dashboardTimeZone);
    $this->assertSupportedPeriod($periodStart, $periodEnd);
    $granularity = $this->resolveGranularity($query->granularity, $periodStart, $periodEnd);
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

    $facilityCount = $this->countFacilities($query->organizationId, $query->facilityType);
    $activeFacilityCount = $this->countActiveFacilities($query->organizationId, $query->facilityType);
    $facilityCountsByType = null === $query->facilityType
      ? $this->normalizeBreakdown(
        $this->facilityStatistics->countFacilitiesByType($query->organizationId),
        FacilityType::cases(),
      )
      : $this->buildFilteredBreakdown(
        FacilityType::cases(),
        $query->facilityType,
        fn (string $type): int => $this->countFacilities($query->organizationId, $type),
        $facilityCount,
      );

    $equipmentTotalCount = $this->countEquipment($query->organizationId, $query->equipmentType, $query->equipmentStatus);
    $equipmentCountsByStatus = null === $query->equipmentType && null === $query->equipmentStatus
      ? $this->normalizeBreakdown(
        $this->equipmentStatistics->countEquipmentByStatus($query->organizationId),
        EquipmentStatus::cases(),
      )
      : $this->buildFilteredBreakdown(
        EquipmentStatus::cases(),
        $query->equipmentStatus,
        fn (string $status): int => $this->countEquipment($query->organizationId, $query->equipmentType, $status),
        $equipmentTotalCount,
      );
    $equipmentCountsByType = null === $query->equipmentType && null === $query->equipmentStatus
      ? $this->normalizeBreakdown(
        $this->equipmentStatistics->countEquipmentByType($query->organizationId),
        EquipmentType::cases(),
      )
      : $this->buildFilteredBreakdown(
        EquipmentType::cases(),
        $query->equipmentType,
        fn (string $type): int => $this->countEquipment($query->organizationId, $type, $query->equipmentStatus),
        $equipmentTotalCount,
      );

    $inspectionTotalCount = $this->countInspections($query->organizationId, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $inspectionCountsByStatus = null === $query->inspectionStatus && null === $query->inspectionResult && null === $query->inspectorType
      ? $this->normalizeBreakdown(
        $this->inspectionStatistics->countInspectionsByStatus($query->organizationId),
        InspectionStatus::cases(),
      )
      : $this->buildFilteredBreakdown(
        InspectionStatus::cases(),
        $query->inspectionStatus,
        fn (string $status): int => $this->countInspections($query->organizationId, $status, $query->inspectionResult, $query->inspectorType),
        $inspectionTotalCount,
      );
    $inspectionCountsByResult = null === $query->inspectionStatus && null === $query->inspectionResult && null === $query->inspectorType
      ? $this->normalizeBreakdown(
        $this->inspectionStatistics->countInspectionsByResult($query->organizationId),
        InspectionResult::cases(),
      )
      : $this->buildFilteredBreakdown(
        InspectionResult::cases(),
        $query->inspectionResult,
        fn (string $result): int => $this->countInspections($query->organizationId, $query->inspectionStatus, $result, $query->inspectorType),
        $inspectionTotalCount,
      );
    $inspectionCountsByInspectorType = null === $query->inspectionStatus && null === $query->inspectionResult && null === $query->inspectorType
      ? $this->normalizeBreakdown(
        $this->inspectionStatistics->countInspectionsByInspectorType($query->organizationId),
        InspectorType::cases(),
      )
      : $this->buildFilteredBreakdown(
        InspectorType::cases(),
        $query->inspectorType,
        fn (string $inspectorType): int => $this->countInspections($query->organizationId, $query->inspectionStatus, $query->inspectionResult, $inspectorType),
        $inspectionTotalCount,
      );

    $nonConformityTotalCount = $this->countNonConformities($query->organizationId, $query->nonConformitySeverity, $query->nonConformityStatus);
    $nonConformityCountsByStatus = null === $query->nonConformitySeverity && null === $query->nonConformityStatus
      ? $this->normalizeBreakdown(
        $this->nonConformityStatistics->countNonConformitiesByStatus($query->organizationId),
        NonConformityStatus::cases(),
      )
      : $this->buildFilteredBreakdown(
        NonConformityStatus::cases(),
        $query->nonConformityStatus,
        fn (string $status): int => $this->countNonConformities($query->organizationId, $query->nonConformitySeverity, $status),
        $nonConformityTotalCount,
      );
    $nonConformityCountsBySeverity = null === $query->nonConformitySeverity && null === $query->nonConformityStatus
      ? $this->normalizeBreakdown(
        $this->nonConformityStatistics->countNonConformitiesBySeverity($query->organizationId),
        NonConformitySeverity::cases(),
      )
      : $this->buildFilteredBreakdown(
        NonConformitySeverity::cases(),
        $query->nonConformitySeverity,
        fn (string $severity): int => $this->countNonConformities($query->organizationId, $severity, $query->nonConformityStatus),
        $nonConformityTotalCount,
      );

    $generatedAtFormatted = $this->formatIso8601($generatedAt);
    $overdueNonConformityCount = $this->countOverdueNonConformities($query->organizationId, $generatedAtFormatted, $query->nonConformitySeverity, $query->nonConformityStatus);
    $openCriticalNonConformityCount = $this->countOpenCriticalNonConformities($query->organizationId, $query->nonConformitySeverity, $query->nonConformityStatus);

    $draftInspectionCount = $inspectionCountsByStatus['draft'] ?? 0;
    $submittedInspectionCount = $inspectionCountsByStatus['submitted'] ?? 0;
    $closedInspectionCount = $inspectionCountsByStatus['closed'] ?? 0;
    $passInspectionCount = $inspectionCountsByResult['pass'] ?? 0;
    $failInspectionCount = $inspectionCountsByResult['fail'] ?? 0;
    $partialInspectionCount = $inspectionCountsByResult['partial'] ?? 0;
    $inStockEquipmentCount = $equipmentCountsByStatus['in_stock'] ?? 0;
    $operationalEquipmentCount = $equipmentCountsByStatus['operational'] ?? 0;
    $underMaintenanceEquipmentCount = $equipmentCountsByStatus['under_maintenance'] ?? 0;
    $decommissionedEquipmentCount = $equipmentCountsByStatus['decommissioned'] ?? 0;
    $openNonConformityCount = $nonConformityCountsByStatus['open'] ?? 0;
    $inProgressNonConformityCount = $nonConformityCountsByStatus['in_progress'] ?? 0;
    $doneNonConformityCount = $nonConformityCountsByStatus['done'] ?? 0;
    $waivedNonConformityCount = $nonConformityCountsByStatus['waived'] ?? 0;

    $periodStartFormatted = $this->formatIso8601($periodStart);
    $periodEndFormatted = $this->formatIso8601($periodEnd);
    $currentInspectionTrendCounts = $this->countInspectionsPerformedByDay($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $currentNonConformityOpenedTrendCounts = $this->countNonConformitiesCreatedByDay($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->nonConformitySeverity, $query->nonConformityStatus);
    $currentNonConformityResolvedTrendCounts = $this->countNonConformitiesResolvedByDay($query->organizationId, $periodStartFormatted, $periodEndFormatted, $dashboardTimeZone->getName(), $query->nonConformitySeverity, $query->nonConformityStatus);
    $currentPeriodInspectionMetrics = $this->buildInspectionPeriodMetrics($query->organizationId, $periodStart, $periodEnd, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $currentPeriodHealth = [
      'inspectionCompletionRate' => $this->percentage($currentPeriodInspectionMetrics['closed'], $currentPeriodInspectionMetrics['total']),
      'inspectionPassRate' => $this->percentage($currentPeriodInspectionMetrics['pass'], $currentPeriodInspectionMetrics['pass'] + $currentPeriodInspectionMetrics['fail'] + $currentPeriodInspectionMetrics['partial']),
      'nonConformityResolutionRate' => $this->buildNonConformityPeriodResolutionRate($query->organizationId, $periodStart, $currentNonConformityOpenedTrendCounts, $currentNonConformityResolvedTrendCounts, $query->nonConformitySeverity, $query->nonConformityStatus),
    ];

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
        'byStatus' => $invitationCountsByStatus,
      ],
      'facilities' => [
        'total' => $facilityCount,
        'active' => $activeFacilityCount,
        'archived' => max(0, $facilityCount - $activeFacilityCount),
        'byType' => $facilityCountsByType,
      ],
      'equipment' => [
        'total' => $equipmentTotalCount,
        'inStock' => $inStockEquipmentCount,
        'operational' => $operationalEquipmentCount,
        'underMaintenance' => $underMaintenanceEquipmentCount,
        'decommissioned' => $decommissionedEquipmentCount,
        'byStatus' => $equipmentCountsByStatus,
        'byType' => $equipmentCountsByType,
      ],
      'inspections' => [
        'total' => $inspectionTotalCount,
        'draft' => $draftInspectionCount,
        'submitted' => $submittedInspectionCount,
        'closed' => $closedInspectionCount,
        'pass' => $passInspectionCount,
        'fail' => $failInspectionCount,
        'partial' => $partialInspectionCount,
        'byStatus' => $inspectionCountsByStatus,
        'byResult' => $inspectionCountsByResult,
        'byInspectorType' => $inspectionCountsByInspectorType,
      ],
      'nonConformities' => [
        'total' => $nonConformityTotalCount,
        'open' => $openNonConformityCount,
        'inProgress' => $inProgressNonConformityCount,
        'done' => $doneNonConformityCount,
        'waived' => $waivedNonConformityCount,
        'overdue' => $overdueNonConformityCount,
        'criticalOpen' => $openCriticalNonConformityCount,
        'byStatus' => $nonConformityCountsByStatus,
        'bySeverity' => $nonConformityCountsBySeverity,
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
    $trends = ['inspectionsPerformed' => $this->normalizeSeries($periodStart, $periodEnd, $granularity, $currentInspectionTrendCounts), 'nonConformitiesOpened' => $this->normalizeSeries($periodStart, $periodEnd, $granularity, $currentNonConformityOpenedTrendCounts), 'nonConformitiesResolved' => $this->normalizeSeries($periodStart, $periodEnd, $granularity, $currentNonConformityResolvedTrendCounts)];

    return new GetOrganizationDashboardResult(generatedAt: $generatedAtFormatted, period: ['from' => $periodStartFormatted, 'to' => $periodEndFormatted, 'granularity' => $granularity, 'comparison' => null !== $comparisonPeriod ? 'previous_period' : 'none', 'timezone' => $dashboardTimeZone->getName()], overview: $overview, health: $health, alerts: $alerts, trends: $trends, comparison: $this->buildComparison($query, $currentInspectionTrendCounts, $currentNonConformityOpenedTrendCounts, $currentNonConformityResolvedTrendCounts, $currentPeriodHealth, $comparisonPeriod));
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
   * Method resolveGranularity.
   *
   * Determine the appropriate granularity for trend data aggregation
   * based on the provided filter and period length.
   *
   * @since 1.0.0
   *
   * @param string $granularity the granularity filter provided in the query, which can be 'auto', 'day', 'week', or 'month'
   * @param DateTimeImmutable $periodStart the start datetime of the period for which to determine granularity
   * @param DateTimeImmutable $periodEnd the end datetime of the period for which to determine granularity
   *
   * @return string the resolved granularity to be used for trend data aggregation, which can
   *                be 'day', 'week', or 'month'. If 'auto' is provided, the method determines the granularity based
   *                on the period length: 'day' for periods up to 45 days, 'week' for periods up to 180 days,
   *                and 'month' for longer periods.
   */
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
    $from = $this->formatIso8601($periodStart);
    $to = $this->formatIso8601($periodEnd);

    $total = $this->countInspectionsBetween($organizationId, $from, $to, $status, $result, $inspectorType);

    return [
      'total' => $total,
      'closed' => 'closed' === $status ? $total : (null !== $status ? 0 : $this->countInspectionsBetween($organizationId, $from, $to, 'closed', $result, $inspectorType)),
      'pass' => 'pass' === $result ? $total : (null !== $result ? 0 : $this->countInspectionsBetween($organizationId, $from, $to, $status, 'pass', $inspectorType)),
      'fail' => 'fail' === $result ? $total : (null !== $result ? 0 : $this->countInspectionsBetween($organizationId, $from, $to, $status, 'fail', $inspectorType)),
      'partial' => 'partial' === $result ? $total : (null !== $result ? 0 : $this->countInspectionsBetween($organizationId, $from, $to, $status, 'partial', $inspectorType)),
    ];
  }

  /**
   * Method buildNonConformityPeriodResolutionRate.
   *
   * Calculate the non-conformity resolution rate for a given period based
   * on the counts of opened and resolved non-conformities, as well as
   * the active non-conformities at the start of the period.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the ID of the organization for which to calculate the resolution rate
   * @param DateTimeImmutable $periodStart the start datetime of the period for which to calculate the resolution rate
   * @param array<string, int> $openedTrendCounts the counts of non-conformities opened during the period, indexed by date
   * @param array<string, int> $resolvedTrendCounts the counts of non-conformities resolved during the period, indexed by date
   * @param string|null $severity an optional filter for non-conformity severity to include in the resolution rate calculation
   * @param string|null $status an optional filter for non-conformity status to include in the resolution rate calculation
   *
   * @return float the calculated non-conformity resolution rate for the specified period and filters
   */
  private function buildNonConformityPeriodResolutionRate(
    string $organizationId,
    DateTimeImmutable $periodStart,
    array $openedTrendCounts,
    array $resolvedTrendCounts,
    ?string $severity = null,
    ?string $status = null,
  ): float {
    return $this->percentage(
      $this->sumSeries($resolvedTrendCounts),
      $this->countActiveNonConformitiesAtDate(
        $organizationId,
        $this->formatIso8601($periodStart),
        $severity,
        $status,
      ) + $this->sumSeries($openedTrendCounts),
    );
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
   * @param array<string, int> $currentInspectionTrendCounts the counts of inspections performed
   *                                                         during the current period, indexed by date
   * @param array<string, int> $currentNonConformityOpenedTrendCounts the counts of non-conformities opened during the current period, indexed by date
   * @param array<string, int> $currentNonConformityResolvedTrendCounts the counts of non-conformities resolved during the current period, indexed by date
   * @param array<string, float> $currentPeriodHealth the health metrics for the current period, indexed by metric name
   * @param ?array{from: DateTimeImmutable, to: DateTimeImmutable} $comparisonPeriod the period to compare against, if any
   *
   * @return array<string, mixed> an array containing the comparison results, including mode,
   *                              current and previous metrics, deltas, and health indicators. If no comparison period
   *                              is provided, the mode will be 'none' and metric values will be empty.
   */
  private function buildComparison(GetOrganizationDashboardQuery $query, array $currentInspectionTrendCounts, array $currentNonConformityOpenedTrendCounts, array $currentNonConformityResolvedTrendCounts, array $currentPeriodHealth, ?array $comparisonPeriod): array
  {
    if (null === $comparisonPeriod) {
      return ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]];
    }
    $from = $this->formatIso8601($comparisonPeriod['from']);
    $to = $this->formatIso8601($comparisonPeriod['to']);
    $comparisonTimeZone = $comparisonPeriod['from']->getTimezone()->getName();
    $previousInspectionTrendCounts = $this->countInspectionsPerformedByDay($query->organizationId, $from, $to, $comparisonTimeZone, $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $previousNonConformityOpenedTrendCounts = $this->countNonConformitiesCreatedByDay($query->organizationId, $from, $to, $comparisonTimeZone, $query->nonConformitySeverity, $query->nonConformityStatus);
    $previousNonConformityResolvedTrendCounts = $this->countNonConformitiesResolvedByDay($query->organizationId, $from, $to, $comparisonTimeZone, $query->nonConformitySeverity, $query->nonConformityStatus);
    $previousPeriodInspectionMetrics = $this->buildInspectionPeriodMetrics($query->organizationId, $comparisonPeriod['from'], $comparisonPeriod['to'], $query->inspectionStatus, $query->inspectionResult, $query->inspectorType);
    $previousPeriodHealth = ['inspectionCompletionRate' => $this->percentage($previousPeriodInspectionMetrics['closed'], $previousPeriodInspectionMetrics['total']), 'inspectionPassRate' => $this->percentage($previousPeriodInspectionMetrics['pass'], $previousPeriodInspectionMetrics['pass'] + $previousPeriodInspectionMetrics['fail'] + $previousPeriodInspectionMetrics['partial']), 'nonConformityResolutionRate' => $this->buildNonConformityPeriodResolutionRate($query->organizationId, $comparisonPeriod['from'], $previousNonConformityOpenedTrendCounts, $previousNonConformityResolvedTrendCounts, $query->nonConformitySeverity, $query->nonConformityStatus)];
    $current = ['inspectionsPerformed' => $this->sumSeries($currentInspectionTrendCounts), 'nonConformitiesOpened' => $this->sumSeries($currentNonConformityOpenedTrendCounts), 'nonConformitiesResolved' => $this->sumSeries($currentNonConformityResolvedTrendCounts)];
    $previous = ['inspectionsPerformed' => $this->sumSeries($previousInspectionTrendCounts), 'nonConformitiesOpened' => $this->sumSeries($previousNonConformityOpenedTrendCounts), 'nonConformitiesResolved' => $this->sumSeries($previousNonConformityResolvedTrendCounts)];

    return ['mode' => 'previous_period', 'from' => $from, 'to' => $to, 'current' => $current, 'previous' => $previous, 'deltas' => ['inspectionsPerformed' => $this->relativeDelta($current['inspectionsPerformed'], $previous['inspectionsPerformed']), 'nonConformitiesOpened' => $this->relativeDelta($current['nonConformitiesOpened'], $previous['nonConformitiesOpened']), 'nonConformitiesResolved' => $this->relativeDelta($current['nonConformitiesResolved'], $previous['nonConformitiesResolved'])], 'health' => ['current' => $currentPeriodHealth, 'previous' => $previousPeriodHealth, 'deltas' => ['inspectionCompletionRate' => $this->relativeDeltaFloat($currentPeriodHealth['inspectionCompletionRate'], $previousPeriodHealth['inspectionCompletionRate']), 'inspectionPassRate' => $this->relativeDeltaFloat($currentPeriodHealth['inspectionPassRate'], $previousPeriodHealth['inspectionPassRate']), 'nonConformityResolutionRate' => $this->relativeDeltaFloat($currentPeriodHealth['nonConformityResolutionRate'], $previousPeriodHealth['nonConformityResolutionRate'])]]];
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
   * Method normalizeSeries.
   *
   * Normalize a series of counts into a list of buckets with values,
   * ensuring that all buckets within the specified period and granularity
   * are represented, even if their count is zero.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the start datetime of the period for which to normalize the series
   * @param DateTimeImmutable $periodEnd the end datetime of the period for which to normalize the series
   * @param string $granularity the granularity for bucketing the series, which can be 'day', 'week',
   *                            or 'month'. This determines how the buckets are identified and aggregated (e.g., daily buckets
   *                            will be identified by 'Y-m-d', weekly buckets by 'o-\\WW', and monthly buckets by 'Y-m').
   * @param array<string, int> $counts the original counts indexed by date strings in 'Y-m-d' format
   *
   * @return list<array{bucket: string, value: int}> the normalized series as a list of
   *                                                 buckets with their corresponding values, where each bucket is a string
   *                                                 identifier based on the specified granularity (e.g., '2024-01-01' for
   *                                                 daily, '2024-W01' for weekly, '2024-01' for monthly) and value is
   *                                                 the count for that bucket
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
   * Method initializeSeriesBuckets.
   *
   * Initialize a series of buckets for a given period and granularity,
   * setting initial counts to zero.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the start datetime of the period for which to initialize buckets
   * @param DateTimeImmutable $periodEnd the end datetime of the period for which to initialize buckets
   * @param string $granularity the granularity for bucketing, which can be 'day', 'week', or 'month'. This determines how the buckets are identified
   *                            (e.g., daily buckets will be identified by 'Y-m-d', weekly buckets by 'o-\\WW', and monthly buckets by 'Y-m').
   *
   * @return array<string, int> an associative array of bucket identifiers
   *                            initialized to zero, where keys are bucket identifiers based on the
   *                            specified granularity (e.g., '2024-01-01' for daily, '2024-W01' for weekly,
   *                            '2024-01' for monthly) and values are initialized counts set to zero
   */
  private function initializeSeriesBuckets(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd, string $granularity): array
  {
    $series = [];
    for ($cursor = $this->bucketStartForDate($periodStart, $granularity), $lastBucket = $this->bucketStartForDate($periodEnd, $granularity); $cursor <= $lastBucket; $cursor = $this->advanceBucket($cursor, $granularity)) {
      $series[$this->bucketKeyForDate($cursor, $granularity)] = 0;
    }

    return $series;
  }

  /**
   * Method bucketStartForDate.
   *
   * Calculate the start datetime of the bucket for
   * a given date and granularity.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $date the date for which to calculate the bucket start
   * @param string $granularity the granularity for bucketing, which can be 'day', 'week',
   *                            or 'month'. This determines how the bucket start is calculated (e.g., for 'week', the bucket
   *                            starts on Monday of the week; for 'month', the bucket starts on the first day of the month).
   *
   * @return DateTimeImmutable the calculated start datetime of the bucket for the given date and granularity, with time set to 00:00:00
   */
  private function bucketStartForDate(DateTimeImmutable $date, string $granularity): DateTimeImmutable
  {
    return match ($granularity) {
      'week' => $date->setISODate((int) $date->format('o'), (int) $date->format('W'))->setTime(0, 0), 'month' => $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 1)->setTime(0, 0), default => $date->setTime(0, 0)
    };
  }

  /**
   * Method advanceBucket.
   *
   * Advance a bucket start datetime to the next bucket based
   * on the specified granularity.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $bucketStart the start datetime of the current bucket
   * @param string $granularity the granularity for bucketing, which can be 'day', 'week', or 'month'. This determines how the bucket is advanced (e.g., for 'week', it advances by 7 days; for 'month', it advances to the first day of the next month).
   *
   * @return DateTimeImmutable the calculated start datetime of the next bucket based on the specified granularity
   */
  private function advanceBucket(DateTimeImmutable $bucketStart, string $granularity): DateTimeImmutable
  {
    return match ($granularity) {
      'week' => $bucketStart->add(new DateInterval('P7D')), 'month' => $bucketStart->add(new DateInterval('P1M')), default => $bucketStart->add(new DateInterval('P1D'))
    };
  }

  /**
   * Method bucketKeyForDate.
   *
   * Generate a bucket key string for a given date based
   * on the specified granularity.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $date the date for which to generate the bucket key
   * @param string $granularity the granularity for bucketing, which can be 'day', 'week', or 'month'.
   *                            This determines the format of the bucket key (e.g., for 'week', the key is formatted as 'o-\\WW'; for
   *                            'month', it is formatted as 'Y-m'; for 'day', it is formatted as 'Y-m-d').
   *
   * @return string the generated bucket key string for the given date and granularity,
   *                formatted according to the specified granularity
   */
  private function bucketKeyForDate(DateTimeImmutable $date, string $granularity): string
  {
    return match ($granularity) {
      'week' => $date->format('o-\\WW'), 'month' => $date->format('Y-m'), default => $date->format('Y-m-d')
    };
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
   * Method buildFilteredBreakdown.
   *
   * Build a breakdown of counts by enum cases, applying a filter to select a
   * specific case if provided. If a selected value is given, only that case will have
   * its count resolved using the provided resolver, while all other cases will be set to zero.
   * If no selected value is provided, all cases will have their counts resolved using the resolver.
   *
   * @since 1.0.0
   *
   * @param list<BackedEnum> $cases a list of enum cases to include in the breakdown,
   *                                where each case is a backed enum instance
   * @param string|null $selectedValue an optional value to filter the breakdown by,
   *                                   which corresponds to one of the enum case values
   * @param callable(string):int $resolver a callable function that takes a resolved case value
   *                                       as input and returns the count for that case
   * @param int|null $selectedCount an optional pre-resolved count for the
   *                                selected case, which can be provided to avoid calling the resolver if the count is already known
   *
   * @return array<int|string, int> an associative array where keys are enum case values and values are
   *                                the corresponding counts, with only the selected case having its count resolved
   *                                by the resolver (or using the provided selectedCount) and all other cases set to
   *                                zero if a selectedValue is given; if no selectedValue is provided, all cases will
   *                                have their counts resolved by the resolver
   */
  private function buildFilteredBreakdown(array $cases, ?string $selectedValue, callable $resolver, ?int $selectedCount = null): array
  {
    /** @var array<int|string, int> $breakdown */
    $breakdown = [];
    foreach ($cases as $case) {
      /** @var BackedEnum $case */
      $value = $case->value;
      $resolvedValue = (string) $value;

      if (null !== $selectedValue) {
        if ($selectedValue === $value) {
          $breakdown[$value] = $selectedCount ?? $resolver($resolvedValue);
        } else {
          $breakdown[$value] = 0;
        }

        continue;
      }

      $breakdown[$value] = $resolver($resolvedValue);
    }

    return $breakdown;
  }

  /**
   * Method countFacilities.
   *
   * Counts facilities for the organization, optionally
   * restricted to a single facility type.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string|null $type the optional facility type filter
   *
   * @return int the total matching facilities
   */
  private function countFacilities(string $organizationId, ?string $type = null): int
  {
    return null === $type ? $this->facilityStatistics->countFacilities($organizationId) : $this->facilityStatistics->countFacilities($organizationId, $type);
  }

  /**
   * Method countActiveFacilities.
   *
   * Counts active facilities for the organization, optionally
   * restricted to a single facility type.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string|null $type the optional facility type filter
   *
   * @return int the total matching active facilities
   */
  private function countActiveFacilities(string $organizationId, ?string $type = null): int
  {
    return null === $type ? $this->facilityStatistics->countActiveFacilities($organizationId) : $this->facilityStatistics->countActiveFacilities($organizationId, $type);
  }

  /**
   * Method countEquipment.
   *
   * Counts organization equipment with optional type
   * and status filters.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string|null $type the optional equipment type filter
   * @param string|null $status the optional equipment status filter
   *
   * @return int the total matching equipment count
   */
  private function countEquipment(string $organizationId, ?string $type = null, ?string $status = null): int
  {
    $arguments = [$organizationId];
    if (null !== $type) {
      $arguments['type'] = $type;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }

    return $this->equipmentStatistics->countEquipment(...$arguments);
  }

  /**
   * Method countInspections.
   *
   * Counts inspections for the organization with optional
   * status, result, and inspector filters.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string|null $status the optional inspection status filter
   * @param string|null $result the optional inspection result filter
   * @param string|null $inspectorType the optional inspector type filter
   *
   * @return int the total matching inspections
   */
  private function countInspections(string $organizationId, ?string $status = null, ?string $result = null, ?string $inspectorType = null): int
  {
    $arguments = [$organizationId];
    if (null !== $status) {
      $arguments['status'] = $status;
    }
    if (null !== $result) {
      $arguments['result'] = $result;
    }
    if (null !== $inspectorType) {
      $arguments['inspectorType'] = $inspectorType;
    }

    return $this->inspectionStatistics->countInspections(...$arguments);
  }

  /**
   * Method countInspectionsBetween.
   *
   * Counts inspections performed inside the requested period
   * with optional inspection filters.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $performedAtFrom the inclusive lower performed-at bound
   * @param string $performedAtTo the inclusive upper performed-at bound
   * @param string|null $status the optional inspection status filter
   * @param string|null $result the optional inspection result filter
   * @param string|null $inspectorType the optional inspector type filter
   *
   * @return int the total matching inspections in the period
   */
  private function countInspectionsBetween(string $organizationId, string $performedAtFrom, string $performedAtTo, ?string $status = null, ?string $result = null, ?string $inspectorType = null): int
  {
    $arguments = [$organizationId, $performedAtFrom, $performedAtTo];
    if (null !== $status) {
      $arguments['status'] = $status;
    }
    if (null !== $result) {
      $arguments['result'] = $result;
    }
    if (null !== $inspectorType) {
      $arguments['inspectorType'] = $inspectorType;
    }

    return $this->inspectionStatistics->countInspectionsBetween(...$arguments);
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
   * Method countNonConformities.
   *
   * Counts non-conformities for the organization with optional
   * severity and status filters.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string|null $severity the optional severity filter
   * @param string|null $status the optional status filter
   *
   * @return int the total matching non-conformities
   */
  private function countNonConformities(string $organizationId, ?string $severity = null, ?string $status = null): int
  {
    $arguments = [$organizationId];
    if (null !== $severity) {
      $arguments['severity'] = $severity;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }

    return $this->nonConformityStatistics->countNonConformities(...$arguments);
  }

  /**
   * Method countOverdueNonConformities.
   *
   * Counts overdue non-conformities while forcing the status
   * scope to unresolved values only.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $dueAtBefore the inclusive due-date upper bound
   * @param string|null $severity the optional severity filter
   * @param string|null $status the optional requested status filter
   *
   * @return int the total matching overdue unresolved non-conformities
   */
  private function countOverdueNonConformities(string $organizationId, string $dueAtBefore, ?string $severity = null, ?string $status = null): int
  {
    $unresolvedStatus = $this->resolveUnresolvedNonConformityStatus($status);
    if (false === $unresolvedStatus) {
      return 0;
    }

    $arguments = [$organizationId, $dueAtBefore];
    if (null !== $severity) {
      $arguments['severity'] = $severity;
    }
    if (null !== $unresolvedStatus) {
      $arguments['status'] = $unresolvedStatus;
    }

    return $this->nonConformityStatistics->countOverdueNonConformities(...$arguments);
  }

  /**
   * Method countActiveNonConformitiesAtDate.
   *
   * Counts active non-conformities at a specific point in time
   * with optional severity and status filters.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $at the reference timestamp
   * @param string|null $severity the optional severity filter
   * @param string|null $status the optional status filter
   *
   * @return int the total matching active non-conformities
   */
  private function countActiveNonConformitiesAtDate(string $organizationId, string $at, ?string $severity = null, ?string $status = null): int
  {
    $arguments = [$organizationId, $at];
    if (null !== $severity) {
      $arguments['severity'] = $severity;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }

    return $this->nonConformityStatistics->countActiveNonConformitiesAtDate(...$arguments);
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

  /**
   * Method countOpenCriticalNonConformities.
   *
   * Counts open critical non-conformities while discarding
   * incompatible severity or resolved-status filters.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string|null $severity the optional severity filter
   * @param string|null $status the optional requested status filter
   *
   * @return int the total matching open critical non-conformities
   */
  private function countOpenCriticalNonConformities(string $organizationId, ?string $severity = null, ?string $status = null): int
  {
    if (null !== $severity && NonConformitySeverity::CRITICAL->value !== $severity) {
      return 0;
    }

    $unresolvedStatus = $this->resolveUnresolvedNonConformityStatus($status);
    if (false === $unresolvedStatus) {
      return 0;
    }

    return null === $unresolvedStatus
      ? $this->nonConformityStatistics->countOpenCriticalNonConformities($organizationId)
      : $this->nonConformityStatistics->countOpenCriticalNonConformities($organizationId, $unresolvedStatus);
  }

  /**
   * Method resolveUnresolvedNonConformityStatus.
   *
   * Narrows an optional non-conformity status filter to the unresolved
   * values accepted by overdue and critical-open indicators.
   *
   * @since 1.0.0
   *
   * @param string|null $status the optional requested non-conformity status
   *
   * @return string|false|null the unresolved status to forward, null when unfiltered,
   *                           or false when the requested status is resolved and should yield zero
   */
  private function resolveUnresolvedNonConformityStatus(?string $status): string|false|null
  {
    if (null === $status) {
      return null;
    }

    return match ($status) {
      NonConformityStatus::OPEN->value,
      NonConformityStatus::IN_PROGRESS->value => $status,
      default => false,
    };
  }
}
