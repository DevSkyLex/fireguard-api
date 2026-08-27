<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend;

use DateInterval;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{EquipmentStatisticsPort, FacilityStatisticsPort, InspectionStatisticsPort, NonConformityStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend\{GetOrganizationDashboardTrendHandler, GetOrganizationDashboardTrendQuery, GetOrganizationDashboardTrendResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\CachePort;
use Shared\Domain\Exception\InvalidValueException;

use function array_keys;
use function count;
use function hash;
use function in_array;

#[CoversClass(GetOrganizationDashboardTrendHandler::class)]
final class GetOrganizationDashboardTrendHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440102';

  #[Test]
  public function testInvokeReturnsInspectionTrendWithComparison(): void
  {
    $authorization = $this->createMetricAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::exactly(2))
      ->method('countInspectionsPerformedByDay')
      ->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone): array {
        self::assertSame(self::ORG_ID, $organizationId);
        self::assertSame('UTC', $timeZone);

        return match ($from) {
          '2026-03-01T00:00:00.500000+00:00' => [
            '2026-03-01' => 2,
            '2026-03-03' => 1,
          ],
          '2026-02-26T00:00:00.500000+00:00' => [
            '2026-02-27' => 1,
          ],
          default => self::fail('Unexpected trend range: ' . $from . ' -> ' . $to),
        };
      });

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      periodFrom: '2026-03-01T00:00:00.500000+00:00',
      periodTo: '2026-03-03T23:59:59.250000+00:00',
      compareWithPreviousPeriod: true,
      granularity: 'day',
      timeZone: 'UTC',
    ));

    self::assertInstanceOf(GetOrganizationDashboardTrendResult::class, $result);
    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED, $result->metric);
    self::assertSame(3, $result->summary['total']);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 2],
      ['bucket' => '2026-03-02', 'value' => 0],
      ['bucket' => '2026-03-03', 'value' => 1],
    ], $result->series);
    /** @var array{mode: string, summary: array{total: int, delta: float}} $comparison */
    $comparison = $result->comparison;
    self::assertSame('previous_period', $comparison['mode']);
    self::assertSame(1, $comparison['summary']['total']);
    self::assertSame(200.0, $comparison['summary']['delta']);
  }

  #[Test]
  public function testInvokeReturnsEquipmentCreatedTrendWithComparisonAndFilters(): void
  {
    $authorization = $this->createMetricAuthorizationMock(
      requiredPermissions: OrganizationPermissionCatalog::dashboardTrendReadDependencies(
        GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED,
      ),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::exactly(2))
      ->method('countEquipmentCreatedByDay')
      ->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone = null, ?string $type = null, ?string $status = null): array {
        self::assertSame(self::ORG_ID, $organizationId);
        self::assertSame('UTC', $timeZone);
        self::assertSame('fire_extinguisher', $type);
        self::assertSame('operational', $status);

        return match ($from) {
          '2026-03-01T00:00:00+00:00' => ['2026-03-01' => 2, '2026-03-02' => 1],
          '2026-02-27T00:00:00+00:00' => ['2026-02-27' => 1],
          default => [],
        };
      });

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $equipmentStatistics,
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createInspectionStatisticsMock(),
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-02T23:59:59+00:00',
      compareWithPreviousPeriod: true,
      granularity: 'day',
      timeZone: 'UTC',
      equipmentType: 'fire_extinguisher',
      equipmentStatus: 'operational',
    ));

    /** @var array{mode: string, summary: array{total: int, delta: float}} $comparison */
    $comparison = $result->comparison;

    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED, $result->metric);
    self::assertSame(3, $result->summary['total']);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 2],
      ['bucket' => '2026-03-02', 'value' => 1],
    ], $result->series);
    self::assertSame('previous_period', $comparison['mode']);
    self::assertSame(1, $comparison['summary']['total']);
    self::assertSame(200.0, $comparison['summary']['delta']);
  }

  #[Test]
  public function testInvokeReturnsFacilitiesCreatedTrendWithoutComparison(): void
  {
    $authorization = $this->createMetricAuthorizationMock(
      requiredPermissions: OrganizationPermissionCatalog::dashboardTrendReadDependencies(
        GetOrganizationDashboardTrendHandler::METRIC_FACILITIES_CREATED,
      ),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::once())
      ->method('countFacilitiesCreatedByDay')
      ->with(
        self::ORG_ID,
        '2026-03-01T00:00:00+00:00',
        '2026-03-03T23:59:59+00:00',
        'UTC',
        'site',
      )
      ->willReturn([
        '2026-03-01' => 1,
        '2026-03-03' => 1,
      ]);

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $facilityStatistics,
      inspectionStatistics: $this->createInspectionStatisticsMock(),
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_FACILITIES_CREATED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-03T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
      facilityType: 'site',
    ));

    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_FACILITIES_CREATED, $result->metric);
    self::assertSame(2, $result->summary['total']);
    self::assertSame('none', $result->comparison['mode']);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 1],
      ['bucket' => '2026-03-02', 'value' => 0],
      ['bucket' => '2026-03-03', 'value' => 1],
    ], $result->series);
  }

  #[Test]
  public function testInvokeReturnsOpenedNonConformityTrendWithoutComparison(): void
  {
    $authorization = $this->createMetricAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::never())->method('countInspectionsPerformedByDay');

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())
      ->method('countNonConformitiesCreatedByDay')
      ->with(
        self::ORG_ID,
        '2026-03-01T00:00:00+00:00',
        '2026-03-03T23:59:59+00:00',
        'UTC',
      )
      ->willReturn([
        '2026-03-01' => 1,
        '2026-03-02' => 2,
      ]);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-03T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
    ));

    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED, $result->metric);
    self::assertSame(3, $result->summary['total']);
    self::assertSame('none', $result->comparison['mode']);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 1],
      ['bucket' => '2026-03-02', 'value' => 2],
      ['bucket' => '2026-03-03', 'value' => 0],
    ], $result->series);
  }

  #[Test]
  public function testInvokeThrowsWhenMetricPermissionIsMissing(): void
  {
    $authorization = $this->createMetricAuthorizationMock(['organization.inspection.read']);

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createStub(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.inspection.read permission.');

    $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFoundAfterPermissionCheck(): void
  {
    $authorizationChecked = false;

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
      ->willReturnCallback(static function () use (&$authorizationChecked): bool {
        $authorizationChecked = true;

        return true;
      });

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturnCallback(static function () use (&$authorizationChecked) {
        self::assertTrue($authorizationChecked);


      });

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createStub(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
    ));
  }

  #[Test]
  public function testInvokeDoesNotRequireDashboardReadForTrendMetrics(): void
  {
    $authorization = $this->createMetricAuthorizationMock(
      deniedPermissions: ['organization.dashboard.read'],
      requiredPermissions: OrganizationPermissionCatalog::dashboardTrendReadDependencies(
        GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED,
      ),
    );

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::never())->method('countInspectionsPerformedByDay');

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())
      ->method('countNonConformitiesResolvedByDay')
      ->willReturn([]);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED,
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
    ));

    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED, $result->metric);
    self::assertSame(0, $result->summary['total']);
  }

  #[Test]
  public function testInvokeThrowsWhenMetricIsUnsupported(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createStub(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: 'unsupported_metric',
    ));
  }

  #[Test]
  public function testInvokeAppliesAnalyticsFiltersToInspectionTrendMetric(): void
  {
    $authorization = $this->createMetricAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::exactly(2))
      ->method('countInspectionsPerformedByDay')
      ->willReturnCallback(static function (string $organizationId, string $from, string $to, ?string $timeZone = null, ?string $status = null, ?string $result = null, ?string $inspectorType = null): array {
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

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-03T23:59:59+00:00',
      compareWithPreviousPeriod: true,
      granularity: 'day',
      timeZone: 'UTC',
      inspectionStatus: 'closed',
      inspectionResult: 'pass',
      inspectorType: 'user',
    ));

    /** @var array{total: int} $summary */
    $summary = $result->summary;
    /** @var array{mode: string, summary: array{total: int, delta: float}} $comparison */
    $comparison = $result->comparison;

    self::assertSame(2, $summary['total']);
    self::assertSame(1, $comparison['summary']['total']);
    self::assertSame(100.0, $comparison['summary']['delta']);
  }

  #[Test]
  public function testInvokeBuildsSeriesByMetricWhenAdditionalMetricsRequested(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
      ->willReturn(true);

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())
      ->method('countNonConformitiesCreatedByDay')
      ->with(self::ORG_ID, '2026-03-01T00:00:00+00:00', '2026-03-02T23:59:59+00:00', 'UTC')
      ->willReturn(['2026-03-01' => 2]);
    $nonConformityStatistics->expects(self::once())
      ->method('countNonConformitiesResolvedByDay')
      ->with(self::ORG_ID, '2026-03-01T00:00:00+00:00', '2026-03-02T23:59:59+00:00', 'UTC')
      ->willReturn(['2026-03-02' => 1]);

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createInspectionStatisticsMock(),
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-02T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
      additionalMetrics: [GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED],
    ));

    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED, $result->metric);
    self::assertSame([
      ['bucket' => '2026-03-01', 'value' => 2],
      ['bucket' => '2026-03-02', 'value' => 0],
    ], $result->series);
    self::assertSame([
      GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED => [
        ['bucket' => '2026-03-01', 'value' => 2],
        ['bucket' => '2026-03-02', 'value' => 0],
      ],
      GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED => [
        ['bucket' => '2026-03-01', 'value' => 0],
        ['bucket' => '2026-03-02', 'value' => 1],
      ],
    ], $result->seriesByMetric);
  }

  #[Test]
  public function testInvokeOmitsSeriesByMetricWhenNoAdditionalMetricsRequested(): void
  {
    $authorization = $this->createMetricAuthorizationMock();

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createStub(InspectionStatisticsPort::class);
    $inspectionStatistics->method('countInspectionsPerformedByDay')->willReturn([]);

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      compareWithPreviousPeriod: false,
    ));

    self::assertSame([], $result->seriesByMetric);
  }

  #[Test]
  public function testInvokeThrowsWhenAdditionalMetricIsUnsupported(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createStub(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
      additionalMetrics: ['unsupported_metric'],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTooManyMetricsRequested(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createStub(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createStub(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('At most 4 dashboard trend metrics may be requested at once.');

    $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      additionalMetrics: [
        GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED,
        GetOrganizationDashboardTrendHandler::METRIC_FACILITIES_CREATED,
        GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
        GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED,
      ],
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAdditionalMetricPermissionIsMissing(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))
      ->method('hasPermission')
      ->willReturnCallback(static function (string $userId, string $organizationId, string $permission): bool {
        self::assertSame(self::USER_ID, $userId);
        self::assertSame(self::ORG_ID, $organizationId);

        return 'organization.inspection.read' === $permission;
      });

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createInspectionStatisticsMock(),
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
    );

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.equipment.read permission.');

    $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      additionalMetrics: [GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED],
    ));
  }

  #[Test]
  public function testInvokeAppliesSeverityAndStatusFiltersToOpenedNonConformityTrend(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($this->createOrganization());

    $capturedArguments = [];

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::once())
      ->method('countNonConformitiesCreatedByDay')
      ->willReturnCallback(static function (
        string $organizationId,
        string $from,
        string $to,
        ?string $timeZone = null,
        ?string $severity = null,
        ?string $status = null,
      ) use (&$capturedArguments): array {
        $capturedArguments = [$organizationId, $from, $to, $timeZone, $severity, $status];

        return ['2026-03-01' => 4];
      });
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createMetricAuthorizationMock(),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createInspectionStatisticsMock(),
      nonConformityStatistics: $nonConformityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-02T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
      nonConformityStatus: 'open',
      nonConformitySeverity: 'critical',
    ));

    self::assertSame(4, $result->summary['total']);
    self::assertSame(
      [self::ORG_ID, '2026-03-01T00:00:00+00:00', '2026-03-02T23:59:59+00:00', 'UTC', 'critical', 'open'],
      $capturedArguments,
    );
  }

  #[Test]
  public function testInvokeAppliesSeverityAndStatusFiltersToResolvedNonConformityTrend(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($this->createOrganization());

    $capturedArguments = [];

    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::once())
      ->method('countNonConformitiesResolvedByDay')
      ->willReturnCallback(static function (
        string $organizationId,
        string $from,
        string $to,
        ?string $timeZone = null,
        ?string $severity = null,
        ?string $status = null,
      ) use (&$capturedArguments): array {
        $capturedArguments = [$organizationId, $from, $to, $timeZone, $severity, $status];

        return ['2026-03-01' => 3];
      });

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createMetricAuthorizationMock(),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createInspectionStatisticsMock(),
      nonConformityStatistics: $nonConformityStatistics,
    );

    $trend = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-02T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
      nonConformityStatus: 'resolved',
      nonConformitySeverity: 'major',
    ));

    self::assertSame(3, $trend->summary['total']);
    self::assertSame(
      [self::ORG_ID, '2026-03-01T00:00:00+00:00', '2026-03-02T23:59:59+00:00', 'UTC', 'major', 'resolved'],
      $capturedArguments,
    );
  }

  #[Test]
  public function testInvokeServesTheCachedTrendOnASubsequentIdenticalQuery(): void
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

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::exactly(2))->method('findById')->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::once())
      ->method('countInspectionsPerformedByDay')
      ->willReturn(['2026-03-01' => 5]);

    $warmHandler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createMetricAuthorizationMock(),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
      cache: $cache,
    );
    $cachedHandler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createMetricAuthorizationMock(),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $this->createInspectionStatisticsMock(),
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
      cache: $cache,
    );

    $query = new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-02T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
    );

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

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::once())
      ->method('countInspectionsPerformedByDay')
      ->willReturn(['2026-03-01' => 7]);

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createMetricAuthorizationMock(),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
      cache: $cache,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-02T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
    ));

    self::assertSame(7, $result->summary['total']);
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

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($this->createOrganization());

    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::once())
      ->method('countInspectionsPerformedByDay')
      ->willReturn(['2026-03-01' => 3]);

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $this->createMetricAuthorizationMock(),
      organizationRepository: $organizationRepository,
      equipmentStatistics: $this->createEquipmentStatisticsMock(),
      facilityStatistics: $this->createFacilityStatisticsMock(),
      inspectionStatistics: $inspectionStatistics,
      nonConformityStatistics: $this->createNonConformityStatisticsMock(),
      cache: $cache,
    );

    $result = $handler->__invoke(new GetOrganizationDashboardTrendQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
      metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      periodFrom: '2026-03-01T00:00:00+00:00',
      periodTo: '2026-03-02T23:59:59+00:00',
      compareWithPreviousPeriod: false,
      granularity: 'day',
      timeZone: 'UTC',
      // Invalid UTF-8 makes json_encode() throw, forcing the degraded cache key.
      facilityType: "\xB1\x31",
    ));

    self::assertSame(3, $result->summary['total']);
    self::assertCount(1, $cache->store);
    self::assertSame(
      ['organization.dashboard_trend.' . hash('sha256', self::ORG_ID . '|' . GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED)],
      array_keys($cache->store),
    );
  }

  private function createOrganization(): Organization
  {
    return Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    );
  }

  private function createInspectionStatisticsMock(): InspectionStatisticsPort
  {
    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::never())->method('countInspectionsPerformedByDay');

    return $inspectionStatistics;
  }

  private function createNonConformityStatisticsMock(): NonConformityStatisticsPort
  {
    $nonConformityStatistics = $this->createMock(NonConformityStatisticsPort::class);
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesCreatedByDay');
    $nonConformityStatistics->expects(self::never())->method('countNonConformitiesResolvedByDay');

    return $nonConformityStatistics;
  }

  private function createEquipmentStatisticsMock(): EquipmentStatisticsPort
  {
    $equipmentStatistics = $this->createMock(EquipmentStatisticsPort::class);
    $equipmentStatistics->expects(self::never())->method('countEquipmentCreatedByDay');

    return $equipmentStatistics;
  }

  private function createFacilityStatisticsMock(): FacilityStatisticsPort
  {
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::never())->method('countFacilitiesCreatedByDay');

    return $facilityStatistics;
  }

  /**
   * @param list<string> $deniedPermissions
   * @param list<string>|null $requiredPermissions
   */
  private function createMetricAuthorizationMock(
    array $deniedPermissions = [],
    ?array $requiredPermissions = null,
  ): OrganizationAuthorizationPort {
    $requiredPermissions ??= OrganizationPermissionCatalog::dashboardTrendReadDependencies(
      GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
    );
    $expectedCalls = count($requiredPermissions);
    foreach ($requiredPermissions as $index => $permission) {
      if (in_array($permission, $deniedPermissions, true)) {
        $expectedCalls = $index + 1;

        break;
      }
    }

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly($expectedCalls))
      ->method('hasPermission')
      ->willReturnCallback(static function (string $userId, string $organizationId, string $permission) use ($deniedPermissions): bool {
        self::assertSame(self::USER_ID, $userId);
        self::assertSame(self::ORG_ID, $organizationId);

        return !in_array($permission, $deniedPermissions, true);
      });

    return $authorization;
  }
}
