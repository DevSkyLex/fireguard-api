<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, NonConformityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard\{GetOrganizationDashboardHandler, GetOrganizationDashboardQuery, GetOrganizationDashboardResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use function array_column;
use function in_array;
use function str_ends_with;

#[CoversClass(GetOrganizationDashboardHandler::class)]
final class GetOrganizationDashboardHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440102';

  #[Test]
  public function testInvokeReturnsDashboardOverviewHealthAlertsAndTrends(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (OrganizationId $id): bool => self::ORG_ID === (string) $id))
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Dashboard Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
      ));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('countByOrganizationId')->willReturn(12);
    $memberRepository->expects(self::once())->method('countActiveByOrganizationId')->willReturn(9);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('countByOrganizationId')->willReturn(5);
    $roleRepository->expects(self::once())->method('countSystemByOrganizationId')->willReturn(2);

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('countByOrganizationId')->willReturn(8);
    $invitationRepository->expects(self::once())->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 2,
      'accepted' => 3,
      'revoked' => 1,
      'expired' => 2,
    ]);

    /** @var FacilityStatisticsPort&MockObject $facilityStatistics */
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::once())->method('countFacilities')->with(self::ORG_ID)->willReturn(7);
    $facilityStatistics->expects(self::once())->method('countActiveFacilities')->with(self::ORG_ID)->willReturn(6);
    $facilityStatistics->expects(self::once())->method('countFacilitiesByType')->with(self::ORG_ID)->willReturn([
      'site' => 5,
      'building' => 2,
    ]);

    /** @var EquipmentStatisticsPort&MockObject $equipmentStatistics */
    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::once())->method('countEquipment')->with(self::ORG_ID)->willReturn(20);
    $equipmentStatistics->expects(self::once())->method('countEquipmentByStatus')->with(self::ORG_ID)->willReturn([
      'in_stock' => 2,
      'operational' => 14,
      'under_maintenance' => 3,
      'decommissioned' => 1,
    ]);
    $equipmentStatistics->expects(self::once())->method('countEquipmentByType')->with(self::ORG_ID)->willReturn([
      'fire_extinguisher' => 12,
      'smoke_detector' => 8,
    ]);

    /** @var InspectionStatisticsPort&MockObject $inspectionStatistics */
    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::once())->method('countInspections')->with(self::ORG_ID)->willReturn(10);
    $inspectionStatistics->expects(self::once())->method('countInspectionsByStatus')->with(self::ORG_ID)->willReturn([
      'draft' => 1,
      'submitted' => 2,
      'closed' => 7,
    ]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsByResult')->with(self::ORG_ID)->willReturn([
      'pass' => 5,
      'fail' => 1,
      'partial' => 1,
    ]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsByInspectorType')->with(self::ORG_ID)->willReturn([
      'user' => 8,
      'external' => 2,
    ]);
    $inspectionStatistics->expects(self::exactly(2))->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([
      '2026-03-01' => 2,
      '2026-03-02' => 1,
    ]);
    $inspectionStatistics->expects(self::exactly(10))->method('countInspectionsBetween')
      ->willReturnCallback(static function (
        string $organizationId,
        string $performedAtFrom,
        string $performedAtTo,
        ?string $status = null,
        ?string $result = null,
      ): int {
        self::assertSame(self::ORG_ID, $organizationId);
        self::assertNotSame('', $performedAtFrom);
        self::assertNotSame('', $performedAtTo);

        return match (true) {
          'closed' === $status => 2,
          'pass' === $result => 2,
          'fail' === $result => 1,
          'partial' === $result => 0,
          default => 3,
        };
      });

    /** @var NonConformityStatisticsPort&MockObject $nonConformityStatistics */
    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())->method('countNonConformities')->with(self::ORG_ID)->willReturn(9);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesByStatus')->with(self::ORG_ID)->willReturn([
      'open' => 3,
      'in_progress' => 2,
      'done' => 3,
      'waived' => 1,
    ]);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesBySeverity')->with(self::ORG_ID)->willReturn([
      'low' => 1,
      'medium' => 3,
      'high' => 2,
      'critical' => 3,
    ]);
    $nonConformityStatistics->expects(self::once())->method('countOverdueNonConformities')->with(
      self::ORG_ID,
      self::isString(),
    )->willReturn(2);
    $nonConformityStatistics->expects(self::once())->method('countOpenCriticalNonConformities')->with(self::ORG_ID)->willReturn(1);
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([
      '2026-03-01' => 1,
    ]);
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([
      '2026-03-02' => 1,
    ]);
    $nonConformityStatistics->expects(self::exactly(2))->method('countActiveNonConformitiesAtDate')->with(
      self::ORG_ID,
      self::isString(),
    )->willReturn(1);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
    self::assertSame('day', $result->period['granularity']);
    self::assertArrayHasKey('timezone', $result->period);
    self::assertSame('previous_period', $result->period['comparison']);
    self::assertCount(4, $result->alerts);

    /** @var array{
     *   members: array{total: int},
     *   invitations: array{byStatus: array{expired: int}},
     *   facilities: array{byType: array{site: int}},
     *   nonConformities: array{open: int, byStatus: array{open: int}, bySeverity: array{critical: int}},
     *   equipment: array{operational: int, byStatus: array{operational: int}, byType: array{fire_extinguisher: int}},
     *   inspections: array{byStatus: array{closed: int}, byResult: array{pass: int}, byInspectorType: array{user: int}}
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
    /** @var list<array{code: string, severity: string, count: int}> $alerts */
    $alerts = $result->alerts;
    /** @var array{inspectionsPerformed: list<array{bucket: string, value: int}>} $trends */
    $trends = $result->trends;
    /** @var array{
     *   mode: string,
     *   current: array{inspectionsPerformed: int},
     *   deltas: array{inspectionsPerformed: float},
     *   health: array{
     *     current: array{
     *       inspectionCompletionRate: float,
     *       nonConformityResolutionRate: float
     *     },
     *     deltas: array{
     *       inspectionCompletionRate: float,
     *       nonConformityResolutionRate: float
     *     }
     *   }
     * } $comparison
     */
    $comparison = $result->comparison;

    self::assertSame(12, $overview['members']['total']);
    self::assertSame(2, $overview['invitations']['byStatus']['expired']);
    self::assertSame(5, $overview['facilities']['byType']['site']);
    self::assertSame(3, $overview['nonConformities']['open']);
    self::assertSame(3, $overview['nonConformities']['byStatus']['open']);
    self::assertSame(3, $overview['nonConformities']['bySeverity']['critical']);
    self::assertSame(14, $overview['equipment']['operational']);
    self::assertSame(14, $overview['equipment']['byStatus']['operational']);
    self::assertSame(12, $overview['equipment']['byType']['fire_extinguisher']);
    self::assertSame(7, $overview['inspections']['byStatus']['closed']);
    self::assertSame(5, $overview['inspections']['byResult']['pass']);
    self::assertSame(8, $overview['inspections']['byInspectorType']['user']);
    self::assertSame(75.0, $health['memberActivationRate']);
    self::assertSame(70.0, $health['inspectionCompletionRate']);
    self::assertSame(71.43, $health['inspectionPassRate']);
    self::assertSame(66.67, $health['periodInspectionCompletionRate']);
    self::assertSame(66.67, $health['periodInspectionPassRate']);
    self::assertSame(50.0, $health['periodNonConformityResolutionRate']);
    self::assertSame('critical_non_conformities_open', $alerts[0]['code']);
    self::assertCount(30, $trends['inspectionsPerformed']);
    self::assertSame('previous_period', $comparison['mode']);
    self::assertSame(3, $comparison['current']['inspectionsPerformed']);
    self::assertArrayHasKey('inspectionsPerformed', $comparison['deltas']);
    self::assertSame(66.67, $comparison['health']['current']['inspectionCompletionRate']);
    self::assertSame(0.0, $comparison['health']['deltas']['inspectionCompletionRate']);
    self::assertSame(50.0, $comparison['health']['current']['nonConformityResolutionRate']);
    self::assertSame(0.0, $comparison['health']['deltas']['nonConformityResolutionRate']);
  }

  #[Test]
  public function testInvokeReturnsDashboardWithoutComparisonWhenDisabled(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(1);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(1);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(1);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(1);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      '2026-03-01T00:00:00.500000+00:00',
      '2026-03-03T23:59:59.250000+00:00',
      self::isString(),
    )->willReturn([]);
    $inspectionStatistics->expects(self::exactly(5))->method('countInspectionsBetween')
      ->willReturnCallback(static function (
        string $organizationId,
        string $performedAtFrom,
        string $performedAtTo,
        ?string $status = null,
        ?string $result = null,
      ): int {
        self::assertSame(self::ORG_ID, $organizationId);
        self::assertSame('2026-03-01T00:00:00.500000+00:00', $performedAtFrom);
        self::assertSame('2026-03-03T23:59:59.250000+00:00', $performedAtTo);
        self::assertContains($status, [null, 'closed']);
        self::assertContains($result, [null, 'pass', 'fail', 'partial']);

        return 0;
      });

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      '2026-03-01T00:00:00.500000+00:00',
      '2026-03-03T23:59:59.250000+00:00',
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      '2026-03-01T00:00:00.500000+00:00',
      '2026-03-03T23:59:59.250000+00:00',
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countActiveNonConformitiesAtDate')
      ->with(self::ORG_ID, '2026-03-01T00:00:00.500000+00:00')
      ->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      periodFrom: '2026-03-01T00:00:00.500000+00:00',
      periodTo: '2026-03-03T23:59:59.250000+00:00',
      compareWithPreviousPeriod: false,
    ));

    self::assertSame('day', $result->period['granularity']);
    self::assertSame('none', $result->period['comparison']);
    self::assertSame('UTC', $result->period['timezone']);
    self::assertSame('2026-03-01T00:00:00.500000+00:00', $result->period['from']);
    self::assertSame('2026-03-03T23:59:59.250000+00:00', $result->period['to']);
    /** @var array{mode: string, health: array{current: array<mixed>}} $comparison */
    $comparison = $result->comparison;
    self::assertSame('none', $comparison['mode']);
    self::assertSame(0.0, $result->health['periodInspectionCompletionRate']);
    self::assertSame([], $comparison['health']['current']);
    self::assertCount(3, $result->trends['inspectionsPerformed']);
  }

  #[Test]
  public function testInvokeKeepsComparisonBoundsInRequestedTimezone(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(0);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(0);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->expects(self::exactly(2))->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([]);
    $inspectionStatistics->expects(self::exactly(10))->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::exactly(2))->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
    ));

    self::assertSame('2026-02-26T00:00:00+00:00', $result->comparison['from']);
    self::assertSame('2026-02-28T23:59:59+00:00', $result->comparison['to']);
  }

  #[Test]
  public function testInvokeAlignsPreviousPeriodAcrossDstFallback(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(0);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(0);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->method('countInspectionsPerformedByDay')->willReturn([]);
    $inspectionStatistics->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesCreatedByDay')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesResolvedByDay')->willReturn([]);
    $nonConformityStatistics->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      periodFrom: '2026-10-25T00:00:00+02:00',
      periodTo: '2026-10-26T23:59:59+01:00',
      compareWithPreviousPeriod: true,
      timeZone: 'Europe/Paris',
    ));

    self::assertSame('2026-10-23T00:00:00+02:00', $result->comparison['from']);
    self::assertSame('2026-10-24T23:59:59+02:00', $result->comparison['to']);
  }

  #[Test]
  public function testInvokeAggregatesDailyBucketsAcrossDstWhenTimezoneIsProvided(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(0);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(0);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      '2026-03-29T00:00:00+01:00',
      '2026-03-30T23:59:59+02:00',
      'Europe/Paris',
    )->willReturn([
      '2026-03-29' => 1,
      '2026-03-30' => 2,
    ]);
    $inspectionStatistics->expects(self::exactly(5))->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      '2026-03-29T00:00:00+01:00',
      '2026-03-30T23:59:59+02:00',
      'Europe/Paris',
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      '2026-03-29T00:00:00+01:00',
      '2026-03-30T23:59:59+02:00',
      'Europe/Paris',
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      periodFrom: '2026-03-29T00:00:00+01:00',
      periodTo: '2026-03-30T23:59:59+02:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'Europe/Paris',
    ));

    self::assertSame('Europe/Paris', $result->period['timezone']);
    self::assertSame([
      ['bucket' => '2026-03-29', 'value' => 1],
      ['bucket' => '2026-03-30', 'value' => 2],
    ], $result->trends['inspectionsPerformed']);
  }

  #[Test]
  public function testInvokeAcceptsSingleExplicitBoundWithoutTimezone(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(0);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(0);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      self::callback(static fn (string $from): bool => '2026-03-01T00:00:00+00:00' === $from),
      self::isString(),
      'UTC',
    )->willReturn([]);
    $inspectionStatistics->expects(self::exactly(5))->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      '2026-03-01T00:00:00+00:00',
      self::isString(),
      'UTC',
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      '2026-03-01T00:00:00+00:00',
      self::isString(),
      'UTC',
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      periodTo: null,
      compareWithPreviousPeriod: false,
    ));

    self::assertSame('UTC', $result->period['timezone']);
    self::assertSame('2026-03-01T00:00:00+00:00', $result->period['from']);
  }

  #[Test]
  public function testInvokeRejectsSingleExplicitNonUtcOffsetWithoutTimezone(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Dashboard Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
      ));

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $this->createMock(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createMock(OrganizationRoleRepositoryPort::class),
      invitationRepository: $this->createMock(OrganizationInvitationRepositoryPort::class),
      facilityStatistics: $this->createMock(FacilityStatisticsPort::class),
      equipmentStatistics: $this->createMock(EquipmentStatisticsPort::class),
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-01T00:00:00+01:00',
      compareWithPreviousPeriod: false,
    ));
  }

  #[Test]
  public function testInvokeUsesMidnightForDefaultPeriodWhenTimezoneIsProvided(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(0);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(0);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(0);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(0);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      self::callback(static fn (string $from): bool => str_ends_with($from, 'T00:00:00+00:00')),
      self::isString(),
      'UTC',
    )->willReturn([]);
    $inspectionStatistics->expects(self::exactly(5))->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      self::callback(static fn (string $from): bool => str_ends_with($from, 'T00:00:00+00:00')),
      self::isString(),
      'UTC',
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      self::callback(static fn (string $from): bool => str_ends_with($from, 'T00:00:00+00:00')),
      self::isString(),
      'UTC',
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
    ));

    self::assertSame('UTC', $result->period['timezone']);
    self::assertMatchesRegularExpression('/T00:00:00\+00:00$/', $result->period['from']);
  }

  #[Test]
  public function testInvokeAggregatesTrendsByRequestedWeekGranularity(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(1);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(1);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(1);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(1);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([
      '2026-03-01' => 2,
      '2026-03-02' => 1,
      '2026-03-10' => 4,
    ]);
    $inspectionStatistics->expects(self::exactly(5))->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      periodTo: '2026-03-15T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'week',
    ));

    self::assertSame('week', $result->period['granularity']);
    self::assertSame([
      ['bucket' => '2026-W09', 'value' => 2],
      ['bucket' => '2026-W10', 'value' => 1],
      ['bucket' => '2026-W11', 'value' => 4],
    ], $result->trends['inspectionsPerformed']);
  }

  #[Test]
  public function testInvokeResolvesAutoGranularityToMonthForLongPeriods(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(1);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(1);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(1);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(1);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([
      'pending' => 0,
      'accepted' => 0,
      'revoked' => 0,
      'expired' => 0,
    ]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsPerformedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([
      '2026-01-10' => 2,
      '2026-03-05' => 4,
    ]);
    $inspectionStatistics->expects(self::exactly(5))->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countNonConformitiesByStatus')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesBySeverity')->willReturn([]);
    $nonConformityStatistics->method('countOverdueNonConformities')->willReturn(0);
    $nonConformityStatistics->method('countOpenCriticalNonConformities')->willReturn(0);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesCreatedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countNonConformitiesResolvedByDay')->with(
      self::ORG_ID,
      self::isString(),
      self::isString(),
      self::isString(),
    )->willReturn([]);
    $nonConformityStatistics->expects(self::once())->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      periodFrom: '2026-01-01T00:00:00+00:00',
      periodTo: '2026-12-31T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'auto',
    ));

    self::assertSame('month', $result->period['granularity']);
    self::assertSame([
      ['bucket' => '2026-01', 'value' => 2],
      ['bucket' => '2026-02', 'value' => 0],
      ['bucket' => '2026-03', 'value' => 4],
      ['bucket' => '2026-04', 'value' => 0],
      ['bucket' => '2026-05', 'value' => 0],
      ['bucket' => '2026-06', 'value' => 0],
      ['bucket' => '2026-07', 'value' => 0],
      ['bucket' => '2026-08', 'value' => 0],
      ['bucket' => '2026-09', 'value' => 0],
      ['bucket' => '2026-10', 'value' => 0],
      ['bucket' => '2026-11', 'value' => 0],
      ['bucket' => '2026-12', 'value' => 0],
    ], $result->trends['inspectionsPerformed']);
  }

  #[Test]
  public function testInvokeThrowsWhenRequestedPeriodIsChronologicallyInvalidAcrossTimezones(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Dashboard Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
      ));

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $this->createMock(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createMock(OrganizationRoleRepositoryPort::class),
      invitationRepository: $this->createMock(OrganizationInvitationRepositoryPort::class),
      facilityStatistics: $this->createMock(FacilityStatisticsPort::class),
      equipmentStatistics: $this->createMock(EquipmentStatisticsPort::class),
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-01T09:30:00+01:00',
      periodTo: '2026-03-01T10:00:00+02:00',
      compareWithPreviousPeriod: false,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMixedOffsetsAreProvidedWithoutTimezone(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Dashboard Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
      ));

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $this->createMock(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createMock(OrganizationRoleRepositoryPort::class),
      invitationRepository: $this->createMock(OrganizationInvitationRepositoryPort::class),
      facilityStatistics: $this->createMock(FacilityStatisticsPort::class),
      equipmentStatistics: $this->createMock(EquipmentStatisticsPort::class),
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      periodFrom: '2026-03-29T00:00:00+01:00',
      periodTo: '2026-03-30T23:59:59+02:00',
      compareWithPreviousPeriod: false,
    ));
  }

  #[Test]
  public function testInvokeRejectsFixedOffsetTimezoneFilter(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Dashboard Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
      ));

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $this->createMock(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createMock(OrganizationRoleRepositoryPort::class),
      invitationRepository: $this->createMock(OrganizationInvitationRepositoryPort::class),
      facilityStatistics: $this->createMock(FacilityStatisticsPort::class),
      equipmentStatistics: $this->createMock(EquipmentStatisticsPort::class),
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new GetOrganizationDashboardQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      compareWithPreviousPeriod: false,
      timeZone: '+01:00',
    ));
  }

  #[Test]
  public function testInvokeAppliesAnalyticsFiltersToDashboardMetrics(): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(2);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(1);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(1);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(1);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::once())->method('countFacilities')->willReturnCallback(static function (string $organizationId, ?string $type = null): int {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('site', $type);

      return 4;
    });
    $facilityStatistics->expects(self::once())->method('countActiveFacilities')->with(self::ORG_ID, 'site')->willReturn(3);
    $facilityStatistics->expects(self::never())->method('countFacilitiesByType');

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::once())->method('countEquipment')->willReturnCallback(static function (string $organizationId, ?string $type = null, ?string $status = null): int {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('fire_extinguisher', $type);
      self::assertSame('operational', $status);

      return 5;
    });
    $equipmentStatistics->expects(self::never())->method('countEquipmentByStatus');
    $equipmentStatistics->expects(self::never())->method('countEquipmentByType');

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::once())->method('countInspections')->willReturnCallback(static function (string $organizationId, ?string $status = null, ?string $result = null, ?string $inspectorType = null): int {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('closed', $status);
      self::assertSame('pass', $result);
      self::assertSame('user', $inspectorType);

      return 6;
    });
    $inspectionStatistics->expects(self::never())->method('countInspectionsByStatus');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByResult');
    $inspectionStatistics->expects(self::never())->method('countInspectionsByInspectorType');
    $inspectionStatistics->expects(self::exactly(2))->method('countInspectionsPerformedByDay')->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone = null, ?string $status = null, ?string $result = null, ?string $inspectorType = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('UTC', $timeZone);
      self::assertSame('closed', $status);
      self::assertSame('pass', $result);
      self::assertSame('user', $inspectorType);

      return match ($from) {
        '2026-03-01T00:00:00+00:00' => ['2026-03-01' => 2],
        '2026-02-26T00:00:00+00:00' => ['2026-02-27' => 1],
        default => [],
      };
    });
    $inspectionStatistics->expects(self::exactly(2))->method('countInspectionsBetween')->willReturnCallback(static function (string $organizationId, string $performedAtFrom, string $performedAtTo, ?string $status = null, ?string $result = null, ?string $inspectorType = null): int {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('closed', $status);
      self::assertSame('pass', $result);
      self::assertSame('user', $inspectorType);

      return 2;
    });

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())->method('countNonConformities')->willReturnCallback(static function (string $organizationId, ?string $severity = null, ?string $status = null): int {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('critical', $severity);
      self::assertSame('open', $status);

      return 4;
    });
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesByStatus');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesBySeverity');
    $nonConformityStatistics->expects(self::once())->method('countOverdueNonConformities')->with(self::ORG_ID, self::isString(), 'critical', 'open')->willReturn(1);
    $nonConformityStatistics->expects(self::once())->method('countOpenCriticalNonConformities')->with(self::ORG_ID, 'open')->willReturn(2);
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformitiesCreatedByDay')->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone = null, ?string $severity = null, ?string $status = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('UTC', $timeZone);
      self::assertSame('critical', $severity);
      self::assertSame('open', $status);

      return match ($from) {
        '2026-03-01T00:00:00+00:00' => ['2026-03-01' => 1],
        '2026-02-26T00:00:00+00:00' => [],
        default => [],
      };
    });
    $nonConformityStatistics->expects(self::exactly(2))->method('countNonConformitiesResolvedByDay')->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone = null, ?string $severity = null, ?string $status = null): array {
      self::assertSame(self::ORG_ID, $organizationId);
      self::assertSame('UTC', $timeZone);
      self::assertSame('critical', $severity);
      self::assertSame('open', $status);

      return match ($from) {
        '2026-03-01T00:00:00+00:00' => ['2026-03-02' => 1],
        '2026-02-26T00:00:00+00:00' => ['2026-02-28' => 1],
        default => [],
      };
    });
    $nonConformityStatistics->expects(self::exactly(2))->method('countActiveNonConformitiesAtDate')->with(self::ORG_ID, self::isString(), 'critical', 'open')->willReturn(1);

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      granularity: 'day',
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
     *   facilities: array{total: int, byType: array{site: int, building: int}},
     *   equipment: array{total: int, byStatus: array{operational: int, decommissioned: int}},
     *   inspections: array{total: int, byStatus: array{closed: int}, byResult: array{pass: int}, byInspectorType: array{user: int}},
     *   nonConformities: array{total: int, byStatus: array{open: int}, bySeverity: array{critical: int}}
     * } $overview */
    $overview = $result->overview;

    self::assertSame(4, $overview['facilities']['total']);
    self::assertSame(4, $overview['facilities']['byType']['site']);
    self::assertSame(0, $overview['facilities']['byType']['building']);
    self::assertSame(5, $overview['equipment']['total']);
    self::assertSame(5, $overview['equipment']['byStatus']['operational']);
    self::assertSame(0, $overview['equipment']['byStatus']['decommissioned']);
    self::assertSame(6, $overview['inspections']['total']);
    self::assertSame(6, $overview['inspections']['byStatus']['closed']);
    self::assertSame(6, $overview['inspections']['byResult']['pass']);
    self::assertSame(6, $overview['inspections']['byInspectorType']['user']);
    self::assertSame(4, $overview['nonConformities']['total']);
    self::assertSame(4, $overview['nonConformities']['byStatus']['open']);
    self::assertSame(4, $overview['nonConformities']['bySeverity']['critical']);

    /** @var array{inspectionCompletionRate: float, inspectionPassRate: float, periodNonConformityResolutionRate: float} $health */
    $health = $result->health;
    self::assertSame(100.0, $health['inspectionCompletionRate']);
    self::assertSame(100.0, $health['inspectionPassRate']);
    self::assertSame(50.0, $health['periodNonConformityResolutionRate']);

    /** @var array{mode: string, current: array{inspectionsPerformed: int}, previous: array{inspectionsPerformed: int}} $comparison */
    $comparison = $result->comparison;
    self::assertSame('previous_period', $comparison['mode']);
    self::assertSame(2, $comparison['current']['inspectionsPerformed']);
    self::assertSame(1, $comparison['previous']['inspectionsPerformed']);
  }

  #[Test]
  #[DataProvider('resolvedNonConformityStatusProvider')]
  public function testInvokeResolvedStatusFilterDoesNotCorruptUnresolvedNonConformityMetrics(string $status): void
  {
    $authorization = $this->createDashboardAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn(Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    ));

    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->method('countByOrganizationId')->willReturn(1);
    $memberRepository->method('countActiveByOrganizationId')->willReturn(1);

    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->method('countByOrganizationId')->willReturn(1);
    $roleRepository->method('countSystemByOrganizationId')->willReturn(1);

    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->method('countByOrganizationId')->willReturn(0);
    $invitationRepository->method('countByStatusForOrganizationId')->willReturn([]);

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->method('countFacilities')->willReturn(0);
    $facilityStatistics->method('countActiveFacilities')->willReturn(0);
    $facilityStatistics->method('countFacilitiesByType')->willReturn([]);

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->method('countEquipment')->willReturn(0);
    $equipmentStatistics->method('countEquipmentByStatus')->willReturn([]);
    $equipmentStatistics->method('countEquipmentByType')->willReturn([]);

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspections')->willReturn(0);
    $inspectionStatistics->method('countInspectionsByStatus')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByResult')->willReturn([]);
    $inspectionStatistics->method('countInspectionsByInspectorType')->willReturn([]);
    $inspectionStatistics->method('countInspectionsPerformedByDay')->willReturn([]);
    $inspectionStatistics->method('countInspectionsBetween')->willReturn(0);

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->method('countNonConformities')->willReturn(3);
    $nonConformityStatistics->method('countNonConformitiesCreatedByDay')->willReturn([]);
    $nonConformityStatistics->method('countNonConformitiesResolvedByDay')->willReturn([]);
    $nonConformityStatistics->method('countActiveNonConformitiesAtDate')->willReturn(0);

    $nonConformityStatistics->expects(self::never())->method('countOverdueNonConformities');
    $nonConformityStatistics->expects(self::never())->method('countOpenCriticalNonConformities');

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
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
      compareWithPreviousPeriod: true,
      nonConformityStatus: $status,
    ));

    /** @var array{nonConformities: array{overdue: int, criticalOpen: int}} $overview */
    $overview = $result->overview;
    $alertCodes = array_column($result->alerts, 'code');
    self::assertNotContains('critical_non_conformities_open', $alertCodes);
    self::assertNotContains('non_conformities_overdue', $alertCodes);
    self::assertSame(0, $overview['nonConformities']['overdue']);
    self::assertSame(0, $overview['nonConformities']['criticalOpen']);

    /** @var array{inspectionCompletionRate: float, inspectionPassRate: float, periodNonConformityResolutionRate: float} $health */
    $health = $result->health;
    self::assertSame(0.0, $health['periodNonConformityResolutionRate']);

    /** @var array{mode: string, health: array{current: array{nonConformityResolutionRate: float}, previous: array{nonConformityResolutionRate: float}, deltas: array{nonConformityResolutionRate: float}}} $comparison */
    $comparison = $result->comparison;
    self::assertSame('previous_period', $comparison['mode']);
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

        return null;
      });

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $this->createMock(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createMock(OrganizationRoleRepositoryPort::class),
      invitationRepository: $this->createMock(OrganizationInvitationRepositoryPort::class),
      facilityStatistics: $this->createMock(FacilityStatisticsPort::class),
      equipmentStatistics: $this->createMock(EquipmentStatisticsPort::class),
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenDashboardDependencyPermissionIsMissing(): void
  {
    $authorization = $this->createDashboardAuthorizationMock(['organization.roles.read']);

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationDashboardHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      memberRepository: $this->createMock(OrganizationMemberRepositoryPort::class),
      roleRepository: $this->createMock(OrganizationRoleRepositoryPort::class),
      invitationRepository: $this->createMock(OrganizationInvitationRepositoryPort::class),
      facilityStatistics: $this->createMock(FacilityStatisticsPort::class),
      equipmentStatistics: $this->createMock(EquipmentStatisticsPort::class),
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
    );

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.roles.read permission.');

    $handler->__invoke(new GetOrganizationDashboardQuery(self::ORG_ID, self::USER_ID));
  }

  /**
   * @param list<string> $deniedPermissions
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
  private function createDashboardAuthorizationMock(array $deniedPermissions = []): OrganizationAuthorizationPort
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

    return $authorization;
  }
}
