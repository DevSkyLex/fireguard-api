<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend;

use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{InspectionStatisticsPort, NonConformityStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend\{GetOrganizationDashboardTrendHandler, GetOrganizationDashboardTrendQuery, GetOrganizationDashboardTrendResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function count;
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
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($this->createOrganization());

    $handler = new GetOrganizationDashboardTrendHandler(
      authorization: $authorization,
      organizationRepository: $organizationRepository,
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
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
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      organizationRepository: $organizationRepository,
      inspectionStatistics: $this->createMock(InspectionStatisticsPort::class),
      nonConformityStatistics: $this->createMock(NonConformityStatisticsPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

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

  private function createOrganization(): Organization
  {
    return Organization::create(
      id: OrganizationId::fromString(self::ORG_ID),
      name: new OrganizationName('Dashboard Org'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655440199',
    );
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
