<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use DateInterval;
use DateTimeImmutable;
use Organization\Application\Contract\Intervention\RecentInterventionSummary;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, InterventionStatisticsPort, NonConformityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard\{GetOrganizationDashboardHandler, GetOrganizationDashboardQuery, GetOrganizationDashboardResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\{MockObject, Stub};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\CachePort;
use Shared\Domain\Exception\InvalidValueException;

use function array_column;
use function array_keys;
use function get_object_vars;
use function hash;
use function in_array;

#[CoversClass(GetOrganizationDashboardHandler::class)]
final class GetOrganizationDashboardHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440102';

  #[Test]
  public function testInvokeReturnsLightDashboardOverviewHealthAlertsAndComparison(): void
  {
    $organizationRepository = $this->createOrganizationRepository();

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(12);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(9);
    $memberRepository->expects(self::exactly(2))->method('countJoinedBetween')->willReturnOnConsecutiveCalls(2, 1);

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(5);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(2);

    $invitationRepository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(8);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 2,
      'accepted' => 3,
      'revoked' => 1,
      'expired' => 2,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilityOverview')->willReturn(['total' => 7, 'active' => 6]);
    $facilityStatistics->expects(self::never())->method('countFacilities');
    $facilityStatistics->expects(self::never())->method('countActiveFacilities');
    $facilityStatistics->expects(self::never())->method('countFacilitiesByType');
    $facilityStatistics->expects(self::exactly(2))->method('countFacilitiesCreatedByDay')->willReturnOnConsecutiveCalls(
      ['2026-03-01' => 3],
      ['2026-02-01' => 2],
    );

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipmentOverview')->willReturn([
      'total' => 20,
      'in_stock' => 2,
      'operational' => 14,
      'under_maintenance' => 3,
      'decommissioned' => 1,
    ]);
    $equipmentStatistics->expects(self::never())->method('countEquipment');
    $equipmentStatistics->expects(self::never())->method('countEquipmentByStatus');
    $equipmentStatistics->expects(self::never())->method('countEquipmentByType');
    $equipmentStatistics->expects(self::exactly(2))->method('countEquipmentCreatedByDay')->willReturnOnConsecutiveCalls(
      ['2026-03-01' => 4],
      ['2026-02-01' => 1],
    );

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspectionOverview')->willReturn([
      'total' => 10,
      'draft' => 1,
      'submitted' => 2,
      'closed' => 7,
      'pass' => 5,
      'fail' => 1,
      'partial' => 1,
    ]);
    $inspectionStatistics->expects(self::never())->method('countInspections');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByStatus');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByResult');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByInspectorType');
    $inspectionStatistics->expects(self::exactly(2))->method('countInspectionPeriodMetrics')
      ->willReturnCallback(static function (
        string $organizationId,
        string $performedAtFrom,
        string $performedAtTo,
        ?string $status = null,
        ?string $result = null,
        ?string $inspectorType = null,
      ): array {
        self::assertSame(self::ORG_ID, $organizationId);
        self::assertNotSame('', $performedAtFrom);
        self::assertNotSame('', $performedAtTo);
        self::assertNull($status);
        self::assertNull($result);
        self::assertNull($inspectorType);

        return ['total' => 3, 'closed' => 2, 'pass' => 2, 'fail' => 1, 'partial' => 0];
      });

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformityOverview')->willReturn([
      'total' => 9,
      'open' => 3,
      'in_progress' => 2,
      'done' => 3,
      'waived' => 1,
      'overdue' => 2,
      'critical_open' => 1,
    ]);
    $nonConformityStatistics->expects(self::never())->method('countNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesByStatus');
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesBySeverity')->with(self::ORG_ID)->willReturn([
      'low' => 2,
      'medium' => 3,
      'high' => 3,
      'critical' => 1,
    ]);
    $nonConformityStatistics->expects(self::never())->method('countOverdueNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countOpenCriticalNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');
    $nonConformityStatistics->expects(self::never())->method('countActiveNonConformitiesAtDate');
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformityPeriodMetrics')->willReturn([
      'opened' => 1,
      'resolved' => 1,
      'activeAtStart' => 1,
    ]);

    $handler = $this->createHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
      facilityStatistics: $facilityStatistics,
      equipmentStatistics: $equipmentStatistics,
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetOrganizationDashboardResult::class, $result);
    self::assertSame('previous_period', $result->period['comparison']);
    self::assertSame('UTC', $result->period['timezone']);
    self::assertArrayNotHasKey('granularity', $result->period);
    self::assertCount(4, $result->alerts);
    /** @var array{
     *   members: array{total: int},
     *   invitations: array{expired: int},
     *   facilities: array{total: int},
     *   equipment: array{operational: int},
     *   inspections: array{closed: int},
     *   nonConformities: array{open: int, severityLow: int, severityMedium: int, severityHigh: int, severityCritical: int}
     * } $overview
     */
    $overview = $result->overview;
    /** @var array{
     *   memberActivationRate: float,
     *   inspectionCompletionRate: float,
     *   inspectionPassRate: float,
     *   periodInspectionCompletionRate: float,
     *   periodInspectionPassRate: float,
     *   periodNonConformityResolutionRate: float
     * } $health
     */
    $health = $result->health;
    /** @var array{
     *   mode: string,
     *   current: array{
     *     inspectionsPerformed: int,
     *     facilitiesCreated: int,
     *     membersJoined: int,
     *     equipmentCreated: int,
     *     nonConformitiesOpened: int,
     *     nonConformitiesResolved: int
     *   },
     *   previous: array{
     *     inspectionsPerformed: int,
     *     facilitiesCreated: int,
     *     membersJoined: int,
     *     equipmentCreated: int
     *   },
     *   health: array{
     *     current: array{nonConformityResolutionRate: float},
     *     deltas: array{nonConformityResolutionRate: float}
     *   }
     * } $comparison
     */
    $comparison = $result->comparison;

    self::assertSame(12, $overview['members']['total']);
    self::assertSame(2, $overview['invitations']['expired']);
    self::assertSame(7, $overview['facilities']['total']);
    self::assertSame(14, $overview['equipment']['operational']);
    self::assertSame(7, $overview['inspections']['closed']);
    self::assertSame(3, $overview['nonConformities']['open']);
    self::assertArrayNotHasKey('byType', $overview['facilities']);
    self::assertArrayNotHasKey('byStatus', $overview['equipment']);
    self::assertArrayNotHasKey('byResult', $overview['inspections']);
    self::assertArrayNotHasKey('bySeverity', $overview['nonConformities']);
    self::assertSame(2, $overview['nonConformities']['severityLow']);
    self::assertSame(3, $overview['nonConformities']['severityMedium']);
    self::assertSame(3, $overview['nonConformities']['severityHigh']);
    self::assertSame(1, $overview['nonConformities']['severityCritical']);
    self::assertSame(75.0, $health['memberActivationRate']);
    self::assertSame(70.0, $health['inspectionCompletionRate']);
    self::assertSame(71.43, $health['inspectionPassRate']);
    self::assertSame(66.67, $health['periodInspectionCompletionRate']);
    self::assertSame(66.67, $health['periodInspectionPassRate']);
    self::assertSame(50.0, $health['periodNonConformityResolutionRate']);
    self::assertSame('critical_non_conformities_open', $result->alerts[0]['code']);
    self::assertSame('previous_period', $comparison['mode']);
    self::assertSame(3, $comparison['current']['inspectionsPerformed']);
    self::assertSame(3, $comparison['previous']['inspectionsPerformed']);
    self::assertSame(3, $comparison['current']['facilitiesCreated']);
    self::assertSame(2, $comparison['previous']['facilitiesCreated']);
    self::assertSame(2, $comparison['current']['membersJoined']);
    self::assertSame(1, $comparison['previous']['membersJoined']);
    self::assertSame(4, $comparison['current']['equipmentCreated']);
    self::assertSame(1, $comparison['previous']['equipmentCreated']);
    self::assertSame(1, $comparison['current']['nonConformitiesOpened']);
    self::assertSame(1, $comparison['current']['nonConformitiesResolved']);
    self::assertSame(50.0, $comparison['health']['current']['nonConformityResolutionRate']);
    self::assertSame(0.0, $comparison['health']['deltas']['nonConformityResolutionRate']);
    self::assertArrayHasKey('trends', get_object_vars($result));
    self::assertArrayHasKey('facilities', $result->trends);
    self::assertArrayHasKey('members', $result->trends);
    self::assertArrayHasKey('equipment', $result->trends);
    self::assertArrayHasKey('inspections', $result->trends);
    self::assertSame([], $result->recentInterventions);
    // Absent, not zeroed: a member without the permission must not read "no
    // interventions" when the truth is "not your business".
    self::assertArrayNotHasKey('interventions', $result->overview);
  }

  #[Test]
  public function testInvokeReturnsDashboardWithoutComparisonWhenDisabled(): void
  {
    $organizationRepository = $this->createOrganizationRepository();

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspectionOverview')->willReturn(['total' => 0, 'draft' => 0, 'submitted' => 0, 'closed' => 0, 'pass' => 0, 'fail' => 0, 'partial' => 0]);
    $inspectionStatistics->expects(self::never())->method('countInspections');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByStatus');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByResult');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByInspectorType');
    $inspectionStatistics->expects(self::once())->method('countInspectionPeriodMetrics')->willReturn(['total' => 0, 'closed' => 0, 'pass' => 0, 'fail' => 0, 'partial' => 0]);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformityOverview')->willReturn(['total' => 0, 'open' => 0, 'in_progress' => 0, 'done' => 0, 'waived' => 0, 'overdue' => 0, 'critical_open' => 0]);
    $nonConformityStatistics->expects(self::never())->method('countNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesByStatus');
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesBySeverity')->with(self::ORG_ID)->willReturn(['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0]);
    $nonConformityStatistics->expects(self::never())->method('countOverdueNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countOpenCriticalNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');
    $nonConformityStatistics->expects(self::never())->method('countActiveNonConformitiesAtDate');
    $nonConformityStatistics->expects(self::once())->method('countNonConformityPeriodMetrics')->with(
      self::ORG_ID,
      '2026-03-01T00:00:00.500000+00:00',
      '2026-03-03T23:59:59.250000+00:00',
      '2026-03-01T00:00:00.500000+00:00',
    )->willReturn(['opened' => 0, 'resolved' => 0, 'activeAtStart' => 0]);

    $handler = $this->createHandler(
      organizationRepository: $organizationRepository,
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-01T00:00:00.500000+00:00',
      periodTo: '2026-03-03T23:59:59.250000+00:00',
      compareWithPreviousPeriod: false,
    ));

    self::assertSame('none', $result->period['comparison']);
    self::assertSame('UTC', $result->period['timezone']);
    self::assertSame('2026-03-01T00:00:00.500000+00:00', $result->period['from']);
    self::assertSame('2026-03-03T23:59:59.250000+00:00', $result->period['to']);
    self::assertArrayNotHasKey('granularity', $result->period);
    /** @var array{mode: string, health: array{current: array<mixed>}} $comparison */
    $comparison = $result->comparison;

    self::assertSame('none', $comparison['mode']);
    self::assertSame([], $comparison['health']['current']);
    self::assertSame(0.0, $result->health['periodInspectionCompletionRate']);
    self::assertArrayHasKey('trends', get_object_vars($result));
    self::assertSame([], $result->recentInterventions);
  }

  #[Test]
  public function testInvokeKeepsComparisonBoundsInRequestedTimezone(): void
  {
    $handler = $this->createHandler(
      organizationRepository: $this->createOrganizationRepository(),
      inspectionStatistics: $this->createZeroInspectionStatistics(2),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(2),
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-10-25T00:00:00+02:00',
      periodTo: '2026-10-26T23:59:59+01:00',
      compareWithPreviousPeriod: true,
      timeZone: 'Europe/Paris',
    ));

    self::assertSame('2026-10-23T00:00:00+02:00', $result->comparison['from']);
    self::assertSame('2026-10-24T23:59:59+02:00', $result->comparison['to']);
  }

  #[Test]
  public function testInvokeNormalizesMemberComparisonBoundsToUtcStorageTimezone(): void
  {
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);

    $memberJoinedBetweenCalls = 0;
    $memberRepository->expects(self::exactly(2))
      ->method('countJoinedBetween')
      ->willReturnCallback(static function (OrganizationId $organizationId, DateTimeImmutable $from, DateTimeImmutable $to) use (&$memberJoinedBetweenCalls): int {
        self::assertSame(self::ORG_ID, (string) $organizationId);

        if (0 === $memberJoinedBetweenCalls) {
          self::assertSame('2026-03-31T22:00:00+00:00', $from->format('c'));
          self::assertSame('2026-04-01T21:59:59+00:00', $to->format('c'));
        } else {
          self::assertSame('2026-03-30T22:00:00+00:00', $from->format('c'));
          self::assertSame('2026-03-31T21:59:59+00:00', $to->format('c'));
        }

        ++$memberJoinedBetweenCalls;

        return 0 === $memberJoinedBetweenCalls % 2 ? 2 : 1;
      });

    $handler = $this->createHandler(
      organizationRepository: $this->createOrganizationRepository(),
      memberRepository: $memberRepository,
      inspectionStatistics: $this->createZeroInspectionStatistics(2),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(2),
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-04-01T00:00:00+02:00',
      periodTo: '2026-04-01T23:59:59+02:00',
      compareWithPreviousPeriod: true,
      timeZone: 'Europe/Paris',
    ));

    /** @var array{current: array{membersJoined: int}, previous: array{membersJoined: int}} $comparison */
    $comparison = $result->comparison;

    self::assertSame(1, $comparison['current']['membersJoined']);
    self::assertSame(2, $comparison['previous']['membersJoined']);
  }

  #[Test]
  public function testInvokeAcceptsSingleExplicitBoundWithoutTimezone(): void
  {
    $handler = $this->createHandler(
      organizationRepository: $this->createOrganizationRepository(),
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-01T00:00:00+00:00',
      compareWithPreviousPeriod: false,
    ));

    self::assertSame('UTC', $result->period['timezone']);
    self::assertSame('2026-03-01T00:00:00+00:00', $result->period['from']);
  }

  #[Test]
  public function testInvokeRejectsSingleExplicitNonUtcOffsetWithoutTimezone(): void
  {
    $handler = $this->createHandler(organizationRepository: $this->createOrganizationRepository());

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-01T00:00:00+01:00',
      compareWithPreviousPeriod: false,
    ));
  }

  #[Test]
  public function testInvokeAppliesAnalyticsFiltersToDashboardMetrics(): void
  {
    $organizationRepository = $this->createOrganizationRepository();

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(2);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(1);
    $memberRepository->expects(self::exactly(2))->method('countJoinedBetween')->willReturnCallback(static function (OrganizationId $organizationId, DateTimeImmutable $from, DateTimeImmutable $to): int {
      self::assertSame(self::ORG_ID, (string) $organizationId);

      return '2026-03-01T00:00:00+00:00' === $from->format('c') && '2026-03-03T23:59:59+00:00' === $to->format('c') ? 2 : 1;
    });

    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(1);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(1);

    $invitationRepository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::once())->method('countFacilityOverview')->willReturnCallback(static function (string $organizationId, ?string $type = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('site', $type);

      return ['total' => 4, 'active' => 3];
    });
    $facilityStatistics->expects(self::never())->method('countFacilities');
    $facilityStatistics->expects(self::never())->method('countActiveFacilities');
    $facilityStatistics->expects(self::never())->method('countFacilitiesByType');
    $facilityStatistics->expects(self::exactly(2))->method('countFacilitiesCreatedByDay')->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone = null, ?string $type = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('UTC', $timeZone);
      self::assertSame('site', $type);

      return '2026-03-01T00:00:00+00:00' === $from && '2026-03-03T23:59:59+00:00' === $to
        ? ['2026-03-02' => 2]
        : ['2026-02-27' => 1];
    });

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::once())->method('countEquipmentOverview')->willReturnCallback(static function (string $organizationId, ?string $type = null, ?string $status = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('fire_extinguisher', $type);
      self::assertSame('operational', $status);

      return ['total' => 5, 'in_stock' => 0, 'operational' => 5, 'under_maintenance' => 0, 'decommissioned' => 0];
    });
    $equipmentStatistics->expects(self::never())->method('countEquipment');
    $equipmentStatistics->expects(self::never())->method('countEquipmentByStatus');
    $equipmentStatistics->expects(self::never())->method('countEquipmentByType');
    $equipmentStatistics->expects(self::exactly(2))->method('countEquipmentCreatedByDay')->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone = null, ?string $type = null, ?string $status = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('UTC', $timeZone);
      self::assertSame('fire_extinguisher', $type);
      self::assertSame('operational', $status);

      return '2026-03-01T00:00:00+00:00' === $from && '2026-03-03T23:59:59+00:00' === $to
        ? ['2026-03-03' => 3]
        : ['2026-02-28' => 1];
    });

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::once())->method('countInspectionOverview')->willReturnCallback(static function (string $organizationId, ?string $status = null, ?string $result = null, ?string $inspectorType = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('closed', $status);
      self::assertSame('pass', $result);
      self::assertSame('user', $inspectorType);

      return ['total' => 6, 'draft' => 0, 'submitted' => 0, 'closed' => 6, 'pass' => 6, 'fail' => 0, 'partial' => 0];
    });
    $inspectionStatistics->expects(self::never())->method('countInspections');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByStatus');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByResult');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByInspectorType');
    $inspectionStatistics->expects(self::exactly(2))->method('countInspectionPeriodMetrics')->willReturnCallback(static function (string $organizationId, string $performedAtFrom, string $performedAtTo, ?string $status = null, ?string $result = null, ?string $inspectorType = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('closed', $status);
      self::assertSame('pass', $result);
      self::assertSame('user', $inspectorType);

      $total = '2026-03-01T00:00:00+00:00' === $performedAtFrom ? 2 : 1;

      return ['total' => $total, 'closed' => $total, 'pass' => $total, 'fail' => 0, 'partial' => 0];
    });

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())->method('countNonConformityOverview')->willReturnCallback(static function (string $organizationId, string $dueAtBefore, ?string $severity = null, ?string $status = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertNotSame('', $dueAtBefore);
      self::assertSame('critical', $severity);
      self::assertSame('open', $status);

      return ['total' => 4, 'open' => 4, 'in_progress' => 0, 'done' => 0, 'waived' => 0, 'overdue' => 1, 'critical_open' => 2];
    });
    $nonConformityStatistics->expects(self::never())->method('countNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesByStatus');
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesBySeverity')->with(self::ORG_ID)->willReturn(['low' => 1, 'medium' => 1, 'high' => 1, 'critical' => 1]);
    $nonConformityStatistics->expects(self::never())->method('countOverdueNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countOpenCriticalNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');
    $nonConformityStatistics->expects(self::never())->method('countActiveNonConformitiesAtDate');
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformityPeriodMetrics')->willReturnCallback(static function (string $organizationId, string $from, string $to, string $activeAt, ?string $severity = null, ?string $status = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame($from, $activeAt);
      self::assertNotSame('', $to);
      self::assertSame('critical', $severity);
      self::assertSame('open', $status);

      $isCurrent = '2026-03-01T00:00:00+00:00' === $from;

      return ['opened' => $isCurrent ? 1 : 0, 'resolved' => 1, 'activeAtStart' => 1];
    });

    $handler = $this->createHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
      facilityStatistics: $facilityStatistics,
      equipmentStatistics: $equipmentStatistics,
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-03T23:59:59+00:00',
      compareWithPreviousPeriod: true,
      timeZone: 'UTC',
      facilityType: 'site',
      equipmentType: 'fire_extinguisher',
      equipmentStatus: 'operational',
      inspectionStatus: 'closed',
      inspectionResult: 'pass',
      inspectorType: 'user',
      nonConformityStatus: 'open',
      nonConformitySeverity: 'critical',
    ));

    /** @var array{
     *   facilities: array{total: int, active: int},
     *   equipment: array{total: int, operational: int},
     *   inspections: array{total: int, closed: int, pass: int},
     *   nonConformities: array{total: int, open: int, severityLow: int, severityMedium: int, severityHigh: int, severityCritical: int}
     * } $overview
     */
    $overview = $result->overview;
    /** @var array{
     *   inspectionCompletionRate: float,
     *   inspectionPassRate: float,
     *   periodNonConformityResolutionRate: float
     * } $health
     */
    $health = $result->health;
    /** @var array{
     *   mode: string,
     *   current: array{inspectionsPerformed: int, facilitiesCreated: int, membersJoined: int, equipmentCreated: int},
     *   previous: array{inspectionsPerformed: int, facilitiesCreated: int, membersJoined: int, equipmentCreated: int},
     *   deltas: array{facilitiesCreated: float, membersJoined: float, equipmentCreated: float}
     * } $comparison
     */
    $comparison = $result->comparison;

    self::assertSame(4, $overview['facilities']['total']);
    self::assertSame(3, $overview['facilities']['active']);
    self::assertSame(5, $overview['equipment']['total']);
    self::assertSame(5, $overview['equipment']['operational']);
    self::assertSame(6, $overview['inspections']['total']);
    self::assertSame(6, $overview['inspections']['closed']);
    self::assertSame(6, $overview['inspections']['pass']);
    self::assertSame(4, $overview['nonConformities']['total']);
    self::assertSame(4, $overview['nonConformities']['open']);
    self::assertSame(1, $overview['nonConformities']['severityLow']);
    self::assertSame(1, $overview['nonConformities']['severityMedium']);
    self::assertSame(1, $overview['nonConformities']['severityHigh']);
    self::assertSame(1, $overview['nonConformities']['severityCritical']);
    self::assertSame(100.0, $health['inspectionCompletionRate']);
    self::assertSame(100.0, $health['inspectionPassRate']);
    self::assertSame(50.0, $health['periodNonConformityResolutionRate']);
    self::assertSame('previous_period', $comparison['mode']);
    self::assertSame(2, $comparison['current']['inspectionsPerformed']);
    self::assertSame(1, $comparison['previous']['inspectionsPerformed']);
    self::assertSame(2, $comparison['current']['facilitiesCreated']);
    self::assertSame(1, $comparison['previous']['facilitiesCreated']);
    self::assertSame(2, $comparison['current']['membersJoined']);
    self::assertSame(1, $comparison['previous']['membersJoined']);
    self::assertSame(3, $comparison['current']['equipmentCreated']);
    self::assertSame(1, $comparison['previous']['equipmentCreated']);
    self::assertSame(100.0, $comparison['deltas']['facilitiesCreated']);
    self::assertSame(100.0, $comparison['deltas']['membersJoined']);
    self::assertSame(200.0, $comparison['deltas']['equipmentCreated']);
  }

  #[Test]
  #[DataProvider('resolvedNonConformityStatusProvider')]
  public function testInvokeResolvedStatusFilterDoesNotEmitUnresolvedAlerts(string $status): void
  {
    $organizationRepository = $this->createOrganizationRepository();

    $inspectionStatistics = $this->createZeroInspectionStatistics(2);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformityOverview')->willReturn(['total' => 3, 'open' => 0, 'in_progress' => 0, 'done' => 'done' === $status ? 3 : 0, 'waived' => 'waived' === $status ? 3 : 0, 'overdue' => 0, 'critical_open' => 0]);
    $nonConformityStatistics->expects(self::never())->method('countNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesByStatus');
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesBySeverity')->with(self::ORG_ID)->willReturn(['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0]);
    $nonConformityStatistics->expects(self::never())->method('countOverdueNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countOpenCriticalNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');
    $nonConformityStatistics->expects(self::never())->method('countActiveNonConformitiesAtDate');
    $nonConformityStatistics->method('countNonConformityPeriodMetrics')->willReturn(['opened' => 0, 'resolved' => 0, 'activeAtStart' => 0]);

    $handler = $this->createHandler(
      organizationRepository: $organizationRepository,
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      compareWithPreviousPeriod: true,
      nonConformityStatus: $status,
    ));

    $alertCodes = array_column($result->alerts, 'code');
    /** @var array{nonConformities: array{overdue: int, criticalOpen: int, severityLow: int, severityMedium: int, severityHigh: int, severityCritical: int}} $overview */
    $overview = $result->overview;
    /** @var array{periodNonConformityResolutionRate: float} $health */
    $health = $result->health;
    /** @var array{health: array{current: array{nonConformityResolutionRate: float}, previous: array{nonConformityResolutionRate: float}, deltas: array{nonConformityResolutionRate: float}}} $comparison */
    $comparison = $result->comparison;

    self::assertNotContains('critical_non_conformities_open', $alertCodes);
    self::assertNotContains('non_conformities_overdue', $alertCodes);
    self::assertSame(0, $overview['nonConformities']['overdue']);
    self::assertSame(0, $overview['nonConformities']['criticalOpen']);
    self::assertSame(0, $overview['nonConformities']['severityLow']);
    self::assertSame(0, $overview['nonConformities']['severityMedium']);
    self::assertSame(0, $overview['nonConformities']['severityHigh']);
    self::assertSame(0, $overview['nonConformities']['severityCritical']);
    self::assertSame(0.0, $health['periodNonConformityResolutionRate']);
    self::assertSame(0.0, $comparison['health']['current']['nonConformityResolutionRate']);
    self::assertSame(0.0, $comparison['health']['previous']['nonConformityResolutionRate']);
    self::assertSame(0.0, $comparison['health']['deltas']['nonConformityResolutionRate']);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $authorizationChecked = false;

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->willReturnCallback(function (string $userId, string $organizationId, array $permissions) use (&$authorizationChecked): void {
        self::assertSame(self::USER_ID, $userId);
        self::assertSame(self::ORG_ID, $organizationId);
        self::assertSame(OrganizationPermissionCatalog::dashboardReadDependencies(), $permissions);
        $authorizationChecked = true;
      });

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturnCallback(function () use (&$authorizationChecked) {
        self::assertTrue($authorizationChecked);


      });

    $handler = $this->createHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenDashboardDependencyPermissionIsMissing(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $handler = $this->createHandler(
      authorization: $this->createDashboardAuthorizationMock(['organization.roles.read']),
      organizationRepository: $organizationRepository,
    );

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.roles.read permission.');

    $handler->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeBuildsRunningTotalTrendSeriesAnchoredOnCurrentTotals(): void
  {
    $organizationRepository = $this->createOrganizationRepository();

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(10);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(10);
    $memberRepository->expects(self::never())->method('countJoinedBetween');
    $memberRepository->expects(self::once())
      ->method('countJoinedByDay')
      ->with(
        self::callback(static fn (OrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        self::callback(static fn (DateTimeImmutable $from): bool => '2026-03-01' === $from->format('Y-m-d')),
        self::callback(static fn (DateTimeImmutable $to): bool => '2026-03-03' === $to->format('Y-m-d')),
        'UTC',
      )
      ->willReturn(['2026-03-02' => 1, '2026-03-03' => 1]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilityOverview')->willReturn(['total' => 7, 'active' => 7]);
    $facilityStatistics->expects(self::once())->method('countFacilitiesCreatedByDay')->willReturn(['2026-03-03' => 3]);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipmentOverview')->willReturn(['total' => 20, 'in_stock' => 0, 'operational' => 20, 'under_maintenance' => 0, 'decommissioned' => 0]);
    $equipmentStatistics->expects(self::once())->method('countEquipmentCreatedByDay')->willReturn(['2026-03-03' => 4]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspectionOverview')->willReturn(['total' => 10, 'draft' => 0, 'submitted' => 0, 'closed' => 10, 'pass' => 10, 'fail' => 0, 'partial' => 0]);
    $inspectionStatistics->method('countInspectionPeriodMetrics')->willReturn(['total' => 0, 'closed' => 0, 'pass' => 0, 'fail' => 0, 'partial' => 0]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsPerformedByDay')->willReturn(['2026-03-01' => 1, '2026-03-02' => 1]);

    $nonConformityStatistics = $this->createZeroNonConformityStatistics(1);

    $handler = $this->createHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      facilityStatistics: $facilityStatistics,
      equipmentStatistics: $equipmentStatistics,
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-03T23:59:59+00:00',
      compareWithPreviousPeriod: false,
    ));

    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 4],
      ['bucket' => '2026-03-02', 'value' => 4],
      ['bucket' => '2026-03-03', 'value' => 7],
    ], $result->trends['facilities']);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 8],
      ['bucket' => '2026-03-02', 'value' => 9],
      ['bucket' => '2026-03-03', 'value' => 10],
    ], $result->trends['members']);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 16],
      ['bucket' => '2026-03-02', 'value' => 16],
      ['bucket' => '2026-03-03', 'value' => 20],
    ], $result->trends['equipment']);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 9],
      ['bucket' => '2026-03-02', 'value' => 10],
      ['bucket' => '2026-03-03', 'value' => 10],
    ], $result->trends['inspections']);
  }

  #[Test]
  public function testInvokeIncludesEnrichedRecentInterventionsWhenPermissionGranted(): void
  {
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);
    $memberRepository->method('countJoinedByDay')->willReturn([]);
    $memberRepository->expects(self::once())
      ->method('findUserIdsByMemberIds')
      ->with(
        self::callback(static fn (OrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        ['member-1'],
      )
      ->willReturn(['member-1' => 'user-1']);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilityOverview')->willReturn(['total' => 0, 'active' => 0]);
    $facilityStatistics->method('countFacilitiesCreatedByDay')->willReturn([]);
    $facilityStatistics->expects(self::once())
      ->method('getFacilityNamesByIds')
      ->with(self::ORG_ID, ['site-1'])
      ->willReturn(['site-1' => 'Site Paris']);

    $dueAt = new DateTimeImmutable('2026-03-10T12:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-03-09T08:30:00+00:00');

    $interventionStatistics = $this->createMock(InterventionStatisticsPort::class);
    $interventionStatistics->expects(self::once())
      ->method('findRecentInterventions')
      ->with(self::ORG_ID, 5)
      ->willReturn([
        new RecentInterventionSummary(
          id: 'intervention-1',
          number: 42,
          name: 'Contrôle annuel',
          status: 'in_progress',
          priority: 'high',
          siteId: 'site-1',
          responsibleMemberId: 'member-1',
          dueAt: $dueAt,
          updatedAt: $updatedAt,
        ),
        new RecentInterventionSummary(
          id: 'intervention-2',
          number: 41,
          name: 'Inventaire',
          status: 'draft',
          priority: 'low',
          siteId: null,
          responsibleMemberId: null,
          dueAt: null,
          updatedAt: $updatedAt,
        ),
      ]);

    $handler = $this->createHandler(
      authorization: $this->createDashboardAuthorizationMock(hasInterventionsPermission: true),
      memberRepository: $memberRepository,
      facilityStatistics: $facilityStatistics,
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      interventionStatistics: $interventionStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      compareWithPreviousPeriod: false,
    ));

    self::assertCount(2, $result->recentInterventions);
    self::assertSame([
      'id' => 'intervention-1',
      'number' => 42,
      'name' => 'Contrôle annuel',
      'status' => 'in_progress',
      'priority' => 'high',
      'siteId' => 'site-1',
      'siteName' => 'Site Paris',
      'responsibleId' => 'member-1',
      'responsibleUserId' => 'user-1',
      'dueAt' => '2026-03-10T12:00:00+00:00',
      'updatedAt' => '2026-03-09T08:30:00+00:00',
    ], $result->recentInterventions[0]);
    self::assertSame([
      'id' => 'intervention-2',
      'number' => 41,
      'name' => 'Inventaire',
      'status' => 'draft',
      'priority' => 'low',
      'siteId' => null,
      'siteName' => null,
      'responsibleId' => null,
      'responsibleUserId' => null,
      'dueAt' => null,
      'updatedAt' => '2026-03-09T08:30:00+00:00',
    ], $result->recentInterventions[1]);
  }

  #[Test]
  public function testInvokeReturnsEmptyRecentInterventionsWhenPermissionDenied(): void
  {
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilityOverview')->willReturn(['total' => 0, 'active' => 0]);
    $facilityStatistics->method('countFacilitiesCreatedByDay')->willReturn([]);
    $facilityStatistics->expects(self::never())->method('getFacilityNamesByIds');

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);
    $memberRepository->method('countJoinedByDay')->willReturn([]);
    $memberRepository->expects(self::never())->method('findUserIdsByMemberIds');

    $interventionStatistics = $this->createMock(InterventionStatisticsPort::class);
    $interventionStatistics->expects(self::never())->method('findRecentInterventions');
    $interventionStatistics->expects(self::never())->method('countOverview');

    $handler = $this->createHandler(
      authorization: $this->createDashboardAuthorizationMock(hasInterventionsPermission: false),
      facilityStatistics: $facilityStatistics,
      memberRepository: $memberRepository,
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      interventionStatistics: $interventionStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      compareWithPreviousPeriod: false,
    ));

    self::assertSame([], $result->recentInterventions);
    self::assertArrayNotHasKey('interventions', $result->overview);
  }

  #[Test]
  public function testInvokeExposesInterventionOverviewCountsWhenPermitted(): void
  {
    $interventionStatistics = $this->createMock(InterventionStatisticsPort::class);
    $interventionStatistics->method('findRecentInterventions')->willReturn([]);
    $interventionStatistics->expects(self::once())
      ->method('countOverview')
      ->willReturn(['total' => 31, 'open' => 12, 'overdue' => 4]);

    $handler = $this->createHandler(
      authorization: $this->createDashboardAuthorizationMock(hasInterventionsPermission: true),
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      interventionStatistics: $interventionStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      compareWithPreviousPeriod: false,
    ));

    self::assertSame(
      ['total' => 31, 'open' => 12, 'overdue' => 4],
      $result->overview['interventions'],
    );
  }

  #[Test]
  public function testInvokeCacheKeyDiscriminatesByInterventionsPermissionFlag(): void
  {
    $cache = new class () implements CachePort {
      /**
       * @var array<string, mixed>
       */
      public array $store = [];

      public function get(string $key, mixed $default = null): mixed
      {
        return $this->store[$key] ?? $default;
      }

      public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): void
      {
        $this->store[$key] = $value;
      }

      public function delete(string $key): void
      {
        unset($this->store[$key]);
      }

      public function clear(): void
      {
        $this->store = [];
      }
    };

    $handlerWithoutInterventions = $this->createHandler(
      authorization: $this->createDashboardAuthorizationMock(hasInterventionsPermission: false),
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      cache: $cache,
    );
    $handlerWithInterventions = $this->createHandler(
      authorization: $this->createDashboardAuthorizationMock(hasInterventionsPermission: true),
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      interventionStatistics: $this->createZeroInterventionStatistics(),
      cache: $cache,
    );

    $handlerWithoutInterventions->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID, compareWithPreviousPeriod: false));
    $handlerWithInterventions->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID, compareWithPreviousPeriod: false));

    self::assertCount(2, $cache->store);
  }

  #[Test]
  public function testInvokeServesTheCachedDashboardOnASubsequentIdenticalQuery(): void
  {
    $cache = new class () implements CachePort {
      /**
       * @var array<string, mixed>
       */
      public array $store = [];

      public function get(string $key, mixed $default = null): mixed
      {
        return $this->store[$key] ?? $default;
      }

      public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): void
      {
        $this->store[$key] = $value;
      }

      public function delete(string $key): void
      {
        unset($this->store[$key]);
      }

      public function clear(): void
      {
        $this->store = [];
      }
    };

    $warmHandler = $this->createHandler(
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      cache: $cache,
    );
    $cachedHandler = $this->createHandler(
      inspectionStatistics: $this->createZeroInspectionStatistics(0),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(0),
      cache: $cache,
    );

    $query = new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID, compareWithPreviousPeriod: false);

    $fresh = $warmHandler->__invoke($query);
    $cached = $cachedHandler->__invoke($query);

    self::assertCount(1, $cache->store);
    self::assertSame($fresh, $cached);
  }

  #[Test]
  public function testInvokeRecomputesWhenCacheReadThrowsAndSurvivesCacheWriteFailure(): void
  {
    $cache = new class () implements CachePort {
      public function get(string $key, mixed $default = null): mixed
      {
        throw new RuntimeException('Cache backend unreachable.');
      }

      public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): void
      {
        throw new RuntimeException('Cache backend unreachable.');
      }

      public function delete(string $key): void
      {
      }

      public function clear(): void
      {
      }
    };

    $handler = $this->createHandler(
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      cache: $cache,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID, compareWithPreviousPeriod: false));

    self::assertInstanceOf(GetOrganizationDashboardResult::class, $result);
    self::assertSame(['total' => 0, 'active' => 0, 'inactive' => 0], $result->overview['members']);
  }

  #[Test]
  public function testInvokeFallsBackToAPlainCacheKeyWhenTheQueryIsNotJsonEncodable(): void
  {
    $cache = new class () implements CachePort {
      /**
       * @var array<string, mixed>
       */
      public array $store = [];

      public function get(string $key, mixed $default = null): mixed
      {
        return $this->store[$key] ?? $default;
      }

      public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): void
      {
        $this->store[$key] = $value;
      }

      public function delete(string $key): void
      {
        unset($this->store[$key]);
      }

      public function clear(): void
      {
        $this->store = [];
      }
    };

    $handler = $this->createHandler(
      inspectionStatistics: $this->createZeroInspectionStatistics(1),
      nonConformityStatistics: $this->createZeroNonConformityStatistics(1),
      cache: $cache,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardQuery(
      self::ORG_ID,
      self::USER_ID,
      compareWithPreviousPeriod: false,
      // Invalid UTF-8 makes json_encode() throw, forcing the degraded cache key.
      facilityType: "\xB1\x31",
    ));

    self::assertInstanceOf(GetOrganizationDashboardResult::class, $result);
    self::assertSame(
      ['organization.dashboard.' . hash('sha256', self::ORG_ID)],
      array_keys($cache->store),
    );
  }

  /**
   * @return array<string, array{0: string}>
   */
  public static function resolvedNonConformityStatusProvider(): array
  {
    return [
      'done' => ['done'],
      'waived' => ['waived'],
    ];
  }

  /**
   * @param list<string> $deniedPermissions
   */
  private function createDashboardAuthorizationMock(array $deniedPermissions = [], bool $hasInterventionsPermission = false): OrganizationAuthorizationPort
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->willReturnCallback(static function (string $userId, string $organizationId, array $permissions) use ($deniedPermissions): void {
        self::assertSame(self::USER_ID, $userId);
        self::assertSame(self::ORG_ID, $organizationId);
        self::assertSame(OrganizationPermissionCatalog::dashboardReadDependencies(), $permissions);

        foreach ($permissions as $permission) {
          if (in_array($permission, $deniedPermissions, true)) {
            throw OrganizationAccessDeniedException::missingPermission($permission);
          }
        }
      });
    $authorization->method('hasPermission')->willReturnCallback(
      static fn (string $userId, string $organizationId, string $permission): bool => $hasInterventionsPermission && 'organization.interventions.read' === $permission,
    );

    return $authorization;
  }

  /**
   * @return OrganizationRepositoryPort&MockObject
   */
  private function createOrganizationRepository(): OrganizationRepositoryPort
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (OrganizationId $id): bool => self::ORG_ID === (string) $id))
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Dashboard Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
      ));

    return $organizationRepository;
  }

  /**
   * @return OrganizationMemberRepositoryPort&Stub
   */
  private function createZeroMemberRepository(): OrganizationMemberRepositoryPort
  {
    $memberRepository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);
    $memberRepository->method('countJoinedBetween')->willReturn(0);
    $memberRepository->method('countJoinedByDay')->willReturn([]);

    return $memberRepository;
  }

  /**
   * @return OrganizationRoleRepositoryPort&Stub
   */
  private function createZeroRoleRepository(): OrganizationRoleRepositoryPort
  {
    $roleRepository = $this->createStub(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(0);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(0);

    return $roleRepository;
  }

  /**
   * @return OrganizationInvitationRepositoryPort&Stub
   */
  private function createZeroInvitationRepository(): OrganizationInvitationRepositoryPort
  {
    $invitationRepository = $this->createStub(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    return $invitationRepository;
  }

  /**
   * @return FacilityStatisticsPort&Stub
   */
  private function createZeroFacilityStatistics(): FacilityStatisticsPort
  {
    $facilityStatistics = $this->createStub(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilityOverview')->willReturn(['total' => 0, 'active' => 0]);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);
    $facilityStatistics->method('countFacilitiesCreatedByDay')->willReturn([]);
    $facilityStatistics->method('getFacilityNamesByIds')->willReturn([]);

    return $facilityStatistics;
  }

  /**
   * @return EquipmentStatisticsPort&Stub
   */
  private function createZeroEquipmentStatistics(): EquipmentStatisticsPort
  {
    $equipmentStatistics = $this->createStub(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipmentOverview')->willReturn(['total' => 0, 'in_stock' => 0, 'operational' => 0, 'under_maintenance' => 0, 'decommissioned' => 0]);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);
    $equipmentStatistics->method('countEquipmentCreatedByDay')->willReturn([]);

    return $equipmentStatistics;
  }

  /**
   * @return InspectionStatisticsPort&MockObject
   */
  private function createZeroInspectionStatistics(int $periodMetricCallCount): InspectionStatisticsPort
  {
    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspectionOverview')->willReturn(['total' => 0, 'draft' => 0, 'submitted' => 0, 'closed' => 0, 'pass' => 0, 'fail' => 0, 'partial' => 0]);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->expects(self::never())->method('countInspectionsByInspectorType');
    $inspectionStatistics->expects(self::exactly($periodMetricCallCount))->method('countInspectionPeriodMetrics')->willReturn(['total' => 0, 'closed' => 0, 'pass' => 0, 'fail' => 0, 'partial' => 0]);
    $inspectionStatistics->method('countInspectionsPerformedByDay')->willReturn([]);

    return $inspectionStatistics;
  }

  /**
   * @return NonConformityStatisticsPort&MockObject
   */
  private function createZeroNonConformityStatistics(int $periodMetricCallCount): NonConformityStatisticsPort
  {
    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformityOverview')->willReturn(['total' => 0, 'open' => 0, 'in_progress' => 0, 'done' => 0, 'waived' => 0, 'overdue' => 0, 'critical_open' => 0]);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn(['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');
    $nonConformityStatistics->expects(self::never())->method('countActiveNonConformitiesAtDate');
    $nonConformityStatistics->expects(self::exactly($periodMetricCallCount))->method('countNonConformityPeriodMetrics')->willReturn(['opened' => 0, 'resolved' => 0, 'activeAtStart' => 0]);

    return $nonConformityStatistics;
  }

  /**
   * @return InterventionStatisticsPort&Stub
   */
  private function createZeroInterventionStatistics(): InterventionStatisticsPort
  {
    $interventionStatistics = $this->createStub(InterventionStatisticsPort::class);
    $interventionStatistics->method('findRecentInterventions')->willReturn([]);

    return $interventionStatistics;
  }

  private function createHandler(
    ?OrganizationAuthorizationPort $authorization = null,
    ?OrganizationRepositoryPort $organizationRepository = null,
    ?OrganizationMemberRepositoryPort $memberRepository = null,
    ?OrganizationRoleRepositoryPort $roleRepository = null,
    ?OrganizationInvitationRepositoryPort $invitationRepository = null,
    ?FacilityStatisticsPort $facilityStatistics = null,
    ?EquipmentStatisticsPort $equipmentStatistics = null,
    ?InspectionStatisticsPort $inspectionStatistics = null,
    ?NonConformityStatisticsPort $nonConformityStatistics = null,
    ?InterventionStatisticsPort $interventionStatistics = null,
    ?CachePort $cache = null,
  ): GetOrganizationDashboardHandler {
    return new GetOrganizationDashboardHandler(
      authorization: $authorization ?? $this->createDashboardAuthorizationMock(),
      organizationRepository: $organizationRepository ?? $this->createOrganizationRepository(),
      memberRepository: $memberRepository ?? $this->createZeroMemberRepository(),
      roleRepository: $roleRepository ?? $this->createZeroRoleRepository(),
      invitationRepository: $invitationRepository ?? $this->createZeroInvitationRepository(),
      facilityStatistics: $facilityStatistics ?? $this->createZeroFacilityStatistics(),
      equipmentStatistics: $equipmentStatistics ?? $this->createZeroEquipmentStatistics(),
      inspectionStatistics: $inspectionStatistics ?? $this->createZeroInspectionStatistics(0),
      nonConformityStatistics: $nonConformityStatistics ?? $this->createZeroNonConformityStatistics(0),
      interventionStatistics: $interventionStatistics ?? $this->createZeroInterventionStatistics(),
      cache: $cache,
    );
  }
}
