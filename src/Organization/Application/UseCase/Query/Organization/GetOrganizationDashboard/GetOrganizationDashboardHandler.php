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

final readonly class GetOrganizationDashboardHandler implements QueryHandler
{
  private const string DEFAULT_DASHBOARD_TIME_ZONE = 'UTC';

  private const int DEFAULT_TREND_PERIOD_DAYS = 30;

  private const int MAX_TREND_PERIOD_DAYS = 366;

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

  public function __invoke(GetOrganizationDashboardQuery $query): GetOrganizationDashboardResult
  {
    $organization = $this->organizationRepository->findById(OrganizationId::fromString($query->organizationId));
    if (null === $organization) {
      throw OrganizationNotFoundException::withId($query->organizationId);
    }

    $this->assertDashboardPermissions($query->userId, $query->organizationId);

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

  private function assertDashboardPermissions(string $userId, string $organizationId): void
  {
    foreach (OrganizationPermissionCatalog::dashboardReadDependencies() as $permission) {
      if (!$this->authorization->hasPermission($userId, $organizationId, $permission)) {
        throw OrganizationAccessDeniedException::missingPermission($permission);
      }
    }
  }

  private function percentage(int $numerator, int $denominator): float
  {
    return $denominator <= 0 ? 0.0 : round(($numerator / $denominator) * 100, 2);
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
   * @return array{total: int, closed: int, pass: int, fail: int, partial: int}
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
   * @param array<string, int> $openedTrendCounts
   * @param array<string, int> $resolvedTrendCounts
   */
  private function buildNonConformityPeriodResolutionRate(
    string $organizationId,
    DateTimeImmutable $periodStart,
    array $openedTrendCounts,
    array $resolvedTrendCounts,
    ?string $severity = null,
    ?string $status = null,
  ): float {
    return $this->percentage($this->sumSeries($resolvedTrendCounts), $this->countActiveNonConformitiesAtDate($organizationId, $this->formatIso8601($periodStart), $severity, $status) + $this->sumSeries($openedTrendCounts));
  }

  /**
   * @param array<string, int> $currentInspectionTrendCounts
   * @param array<string, int> $currentNonConformityOpenedTrendCounts
   * @param array<string, int> $currentNonConformityResolvedTrendCounts
   * @param array<string, float> $currentPeriodHealth
   * @param ?array{from: DateTimeImmutable, to: DateTimeImmutable} $comparisonPeriod
   *
   * @return array<string, mixed>
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

  private function relativeDelta(int $current, int $previous): float
  {
    return 0 === $previous ? ($current > 0 ? 100.0 : 0.0) : round((($current - $previous) / $previous) * 100, 2);
  }

  private function relativeDeltaFloat(float $current, float $previous): float
  {
    return 0.0 === $previous ? ($current > 0.0 ? 100.0 : 0.0) : round((($current - $previous) / $previous) * 100, 2);
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
      'week' => $date->setISODate((int) $date->format('o'), (int) $date->format('W'))->setTime(0, 0), 'month' => $date->setDate((int) $date->format('Y'), (int) $date->format('m'), 1)->setTime(0, 0), default => $date->setTime(0, 0)
    };
  }

  private function advanceBucket(DateTimeImmutable $bucketStart, string $granularity): DateTimeImmutable
  {
    return match ($granularity) {
      'week' => $bucketStart->add(new DateInterval('P7D')), 'month' => $bucketStart->add(new DateInterval('P1M')), default => $bucketStart->add(new DateInterval('P1D'))
    };
  }

  private function bucketKeyForDate(DateTimeImmutable $date, string $granularity): string
  {
    return match ($granularity) {
      'week' => $date->format('o-\\WW'), 'month' => $date->format('Y-m'), default => $date->format('Y-m-d')
    };
  }

  private function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u') ? $value->format('Y-m-d\\TH:i:sP') : $value->format('Y-m-d\\TH:i:s.uP');
  }

  /**
   * @param array<int|string, int> $counts
   * @param list<BackedEnum> $cases
   *
   * @return array<int|string, int>
   */
  private function normalizeBreakdown(array $counts, array $cases): array
  {
    $normalized = [];
    foreach ($cases as $case) {
      $normalized[$case->value] = (int) ($counts[$case->value] ?? 0);
    }

    return $normalized;
  }

  /**
   * @param list<BackedEnum> $cases
   * @param callable(string): int $resolver
   * @param ?int $selectedCount precomputed count for the selected bucket when the query already pins the grouped dimension
   *
   * @return array<int|string, int>
   */
  private function buildFilteredBreakdown(array $cases, ?string $selectedValue, callable $resolver, ?int $selectedCount = null): array
  {
    $breakdown = [];
    foreach ($cases as $case) {
      $value = $case->value;
      $resolvedValue = (string) $value;

      if (null !== $selectedValue) {
        $breakdown[$value] = $selectedValue === $value ? ($selectedCount ?? (int) $resolver($resolvedValue)) : 0;

        continue;
      }

      $breakdown[$value] = (int) $resolver($resolvedValue);
    }

    return $breakdown;
  }

  private function countFacilities(string $organizationId, ?string $type = null): int
  {
    return null === $type ? $this->facilityStatistics->countFacilities($organizationId) : $this->facilityStatistics->countFacilities($organizationId, $type);
  }

  private function countActiveFacilities(string $organizationId, ?string $type = null): int
  {
    return null === $type ? $this->facilityStatistics->countActiveFacilities($organizationId) : $this->facilityStatistics->countActiveFacilities($organizationId, $type);
  }

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

  private function countOverdueNonConformities(string $organizationId, string $dueAtBefore, ?string $severity = null, ?string $status = null): int
  {
    $arguments = [$organizationId, $dueAtBefore];
    if (null !== $severity) {
      $arguments['severity'] = $severity;
    }
    if (null !== $status) {
      $arguments['status'] = $status;
    }

    return $this->nonConformityStatistics->countOverdueNonConformities(...$arguments);
  }

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

  private function countOpenCriticalNonConformities(string $organizationId, ?string $severity = null, ?string $status = null): int
  {
    if (null !== $severity && NonConformitySeverity::CRITICAL->value !== $severity) {
      return 0;
    }

    return null === $status ? $this->nonConformityStatistics->countOpenCriticalNonConformities($organizationId) : $this->nonConformityStatistics->countOpenCriticalNonConformities($organizationId, $status);
  }
}
