<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend\{GetOrganizationDashboardTrendHandler, GetOrganizationDashboardTrendQuery, GetOrganizationDashboardTrendResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationDashboardTrendOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationDashboardTrendProvider;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function count;
use function in_array;

#[CoversClass(GetOrganizationDashboardTrendProvider::class)]
final class GetOrganizationDashboardTrendProviderTest extends TestCase
{
  #[Test]
  public function testProvideMapsInspectionTrendPayloadAndExtractsFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardTrendQuery $query): bool {
        return GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED === $query->metric
          && '2026-03-01T00:00:00.500000+00:00' === $query->periodFrom
          && '2026-03-15T23:59:59.250000+00:00' === $query->periodTo
          && false === $query->compareWithPreviousPeriod
          && 'week' === $query->granularity;
      }))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00.125000+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
        period: [
          'from' => '2026-03-01T00:00:00.500000+00:00',
          'to' => '2026-03-15T23:59:59.250000+00:00',
          'granularity' => 'week',
          'comparison' => 'none',
          'timezone' => 'UTC',
        ],
        summary: ['total' => 3],
        series: [['bucket' => '2026-W09', 'value' => 2]],
        comparison: [
          'mode' => 'none',
          'from' => null,
          'to' => null,
          'summary' => [],
          'series' => [],
        ],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2026-03-01T00:00:00.500000+00:00',
        'to' => '2026-03-15T23:59:59.250000+00:00',
        'compare' => 'false',
        'granularity' => 'week',
      ]],
    );

    self::assertInstanceOf(OrganizationDashboardTrendOutput::class, $output);
    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED, $output->metric);
    self::assertSame(3, $output->summary['total']);
    self::assertSame('count', $output->summary['unit']);
    self::assertSame('2026-W09', $output->series[0]['bucket']);
    self::assertArrayHasKey('mode', $output->comparison);
    self::assertSame('none', $output->comparison['mode']);
  }

  #[Test]
  public function testProvideNormalizesTrendComparisonPayload(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-03T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'previous_period', 'timezone' => 'UTC'],
        summary: ['total' => 3],
        series: [['bucket' => '2026-03-01', 'value' => 2]],
        comparison: [
          'mode' => 'previous_period',
          'from' => '2026-02-27T00:00:00+00:00',
          'to' => '2026-02-29T23:59:59+00:00',
          'summary' => ['total' => 1, 'delta' => 200.0],
          'series' => [['bucket' => '2026-02-27', 'value' => 1]],
          'unexpected' => 'ignored',
        ],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
    );

    self::assertInstanceOf(OrganizationDashboardTrendOutput::class, $output);
    self::assertArrayHasKey('mode', $output->comparison);
    self::assertSame('previous_period', $output->comparison['mode']);
    self::assertArrayHasKey('from', $output->comparison);
    self::assertSame('2026-02-27T00:00:00+00:00', $output->comparison['from']);
    self::assertArrayHasKey('summary', $output->comparison);
    /** @var array{total: int, delta: float} $summary */
    $summary = $output->comparison['summary'];
    self::assertArrayHasKey('total', $summary);
    self::assertSame(1, $summary['total']);
    self::assertArrayHasKey('delta', $summary);
    self::assertSame(200.0, $summary['delta']);
    self::assertArrayHasKey('series', $output->comparison);
    self::assertSame('2026-02-27', $output->comparison['series'][0]['bucket']);
    self::assertArrayNotHasKey('unexpected', $output->comparison);
    self::assertArrayNotHasKey('delta', $output->comparison);
  }

  #[Test]
  public function testProvideThrowsWhenMetricPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock(['organization.inspection.read']);

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.inspection.read permission.');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
    );
  }

  #[Test]
  public function testProvideDoesNotRequireDashboardReadForTrendMetrics(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock(
      deniedPermissions: ['organization.dashboard.read'],
      requiredPermissions: OrganizationPermissionCatalog::dashboardTrendReadDependencies(
        GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      ),
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
        period: [
          'from' => '2026-03-01T00:00:00+00:00',
          'to' => '2026-03-29T23:59:59+00:00',
          'granularity' => 'day',
          'comparison' => 'none',
          'timezone' => 'UTC',
        ],
        summary: ['total' => 0],
        series: [],
        comparison: [
          'mode' => 'none',
          'from' => null,
          'to' => null,
          'summary' => [],
          'series' => [],
        ],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
    );

    self::assertInstanceOf(OrganizationDashboardTrendOutput::class, $output);
  }

  #[Test]
  public function testProvideExtractsAnalyticsFiltersForTrendMetrics(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardTrendQuery $query): bool {
        return GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED === $query->metric
          && 'closed' === $query->inspectionStatus
          && 'pass' === $query->inspectionResult
          && 'user' === $query->inspectorType
          && null === $query->nonConformityStatus
          && null === $query->nonConformitySeverity;
      }))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'none', 'timezone' => 'UTC'],
        summary: ['total' => 0],
        series: [],
        comparison: ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $output = $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['inspectionStatus' => 'closed', 'inspectionResult' => 'pass', 'inspectorType' => 'user']],
    );

    self::assertInstanceOf(OrganizationDashboardTrendOutput::class, $output);
  }

  #[Test]
  public function testProvideThrowsWhenTrendAnalyticsEnumFilterIsInvalid(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(\Symfony\Component\HttpKernel\Exception\BadRequestHttpException::class);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['inspectionStatus' => 'broken']],
    );
  }

  private function createSecurityUser(string $id): SecurityUser
  {
    return new SecurityUser(
      id: $id,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
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
        self::assertSame('550e8400-e29b-41d4-a716-446655441600', $userId);
        self::assertSame('550e8400-e29b-41d4-a716-446655441610', $organizationId);

        return !in_array($permission, $deniedPermissions, true);
      });

    return $authorization;
  }
}
