<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend\{GetOrganizationDashboardTrendHandler, GetOrganizationDashboardTrendQuery, GetOrganizationDashboardTrendResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationDashboardTrendOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationDashboardTrendProvider;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

use function count;
use function in_array;
use function sprintf;

#[CoversClass(GetOrganizationDashboardTrendProvider::class)]
final class GetOrganizationDashboardTrendProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
    );
  }

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
  public function testProvideMapsEquipmentCreatedTrendPayloadAndExtractsFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock(
      requiredPermissions: OrganizationPermissionCatalog::dashboardTrendReadDependencies(
        GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED,
      ),
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardTrendQuery $query): bool {
        return GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED === $query->metric
          && '2026-03-01T00:00:00+00:00' === $query->periodFrom
          && '2026-03-15T23:59:59+00:00' === $query->periodTo
          && 'week' === $query->granularity
          && 'fire_extinguisher' === $query->equipmentType
          && 'operational' === $query->equipmentStatus
          && null === $query->facilityType
          && null === $query->inspectionStatus
          && null === $query->nonConformityStatus;
      }))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED,
        period: [
          'from' => '2026-03-01T00:00:00+00:00',
          'to' => '2026-03-15T23:59:59+00:00',
          'granularity' => 'week',
          'comparison' => 'none',
          'timezone' => 'UTC',
        ],
        summary: ['total' => 4],
        series: [['bucket' => '2026-W09', 'value' => 4]],
        comparison: ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2026-03-01T00:00:00+00:00',
        'to' => '2026-03-15T23:59:59+00:00',
        'granularity' => 'week',
        'equipmentType' => 'fire_extinguisher',
        'equipmentStatus' => 'operational',
      ]],
    );

    self::assertInstanceOf(OrganizationDashboardTrendOutput::class, $output);
    self::assertSame(GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED, $output->metric);
    self::assertSame(4, $output->summary['total']);
    self::assertSame('2026-W09', $output->series[0]['bucket']);
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
      queryBus: $this->createStub(QueryBusPort::class),
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
  public function testProvideMapsWrappedTrendPermissionDenialToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationDashboardTrendQuery(
        organizationId: '550e8400-e29b-41d4-a716-446655441610',
        userId: '550e8400-e29b-41d4-a716-446655441600',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      )),
      [OrganizationAccessDeniedException::missingPermission('organization.inspection.read')],
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationDashboardTrendQuery::class))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
    );
  }

  #[Test]
  public function testProvideMapsWrappedTrendOrganizationNotFoundToHttp404(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationDashboardTrendQuery(
        organizationId: '550e8400-e29b-41d4-a716-446655441610',
        userId: '550e8400-e29b-41d4-a716-446655441600',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      )),
      [OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441610')],
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationDashboardTrendQuery::class))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
    );
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

  /**
   * @param array<string, string> $filters
   */
  #[Test]
  #[DataProvider('metricScopedUnsupportedFilterProvider')]
  public function testProvideThrowsWhenTrendReceivesUnsupportedMetricScopedFilters(
    string $operationName,
    array $filters,
    string $expectedMessage,
  ): void {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $metric = match ($operationName) {
      OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND => GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      OrganizationOperations::GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND => GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED,
      OrganizationOperations::GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND => GetOrganizationDashboardTrendHandler::METRIC_FACILITIES_CREATED,
      OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND => GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
      OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND => GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED,
      default => throw new InvalidArgumentException(sprintf('Unsupported dashboard trend operation "%s".', $operationName)),
    };

    $authorization = $this->createMetricAuthorizationMock(
      requiredPermissions: OrganizationPermissionCatalog::dashboardTrendReadDependencies($metric),
    );
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage($expectedMessage);

    $provider->provide(
      new Get(name: $operationName),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => $filters],
    );
  }

  #[Test]
  public function testProvideMapsWrappedTrendInvalidArgumentToHttp400(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationDashboardTrendQuery(
        organizationId: '550e8400-e29b-41d4-a716-446655441610',
        userId: '550e8400-e29b-41d4-a716-446655441600',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
      )),
      [new InvalidArgumentException('Mixed timezone offsets require the "timezone" filter.')],
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardTrendQuery $query): bool {
        return GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED === $query->metric
          && '2026-03-29T00:00:00+01:00' === $query->periodFrom
          && '2026-03-30T23:59:59+02:00' === $query->periodTo
          && null === $query->timeZone;
      }))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationDashboardTrendProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Mixed timezone offsets require the "timezone" filter.');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2026-03-29T00:00:00+01:00',
        'to' => '2026-03-30T23:59:59+02:00',
      ]],
    );
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

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['inspectionStatus' => 'broken']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenTrendCompareFilterIsInvalidBoolean(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "compare" filter.');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => 'maybe']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenTrendCompareFilterIsEmptyString(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "compare" filter.');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => '']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenTrendCompareFilterIsNonScalar(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "compare" filter.');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => ['maybe']]],
    );
  }

  #[Test]
  public function testProvideAcceptsNativeBooleanCompareFilterForTrend(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationDashboardTrendQuery $query): bool => true === $query->compareWithPreviousPeriod))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'previous_period', 'timezone' => 'UTC'],
        summary: ['total' => 0],
        series: [],
        comparison: ['mode' => 'previous_period', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => true]],
    );
  }

  #[Test]
  public function testProvideAcceptsCompareAliasOffForTrend(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationDashboardTrendQuery $query): bool => false === $query->compareWithPreviousPeriod))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'none', 'timezone' => 'UTC'],
        summary: ['total' => 0],
        series: [],
        comparison: ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => 'off']],
    );
  }

  #[Test]
  #[DataProvider('documentedStringCompareFilterProvider')]
  public function testProvideAcceptsDocumentedStringCompareAliasesForTrend(string $compare, bool $expected): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationDashboardTrendQuery $query): bool => $expected === $query->compareWithPreviousPeriod))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => $expected ? 'previous_period' : 'none', 'timezone' => 'UTC'],
        summary: ['total' => 0],
        series: [],
        comparison: ['mode' => $expected ? 'previous_period' : 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => $compare]],
    );
  }

  /**
   * @return array<string, array{0: string, 1: bool}>
   */
  public static function documentedStringCompareFilterProvider(): array
  {
    return [
      'true string' => ['true', true],
      'false string' => ['false', false],
      'one alias' => ['1', true],
      'zero alias' => ['0', false],
      'yes alias' => ['yes', true],
      'no alias' => ['no', false],
      'on alias' => ['on', true],
      'trimmed off alias' => [' off ', false],
    ];
  }

  #[Test]
  public function testProvideCombinesNonConformityMetricsIntoQueryAndPermissionChecksBoth(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441600', '550e8400-e29b-41d4-a716-446655441610', 'organization.inspection.read')
      ->willReturn(true);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardTrendQuery $query): bool {
        return GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED === $query->metric
          && [GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED] === $query->additionalMetrics;
      }))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'none', 'timezone' => 'UTC'],
        summary: ['total' => 2],
        series: [['bucket' => '2026-03-01', 'value' => 2]],
        comparison: ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
        seriesByMetric: [
          GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED => [['bucket' => '2026-03-01', 'value' => 2]],
          GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED => [['bucket' => '2026-03-01', 'value' => 1]],
        ],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $output = $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['metrics' => 'non_conformities_resolved']],
    );

    self::assertInstanceOf(OrganizationDashboardTrendOutput::class, $output);
    self::assertSame([
      GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED => [['bucket' => '2026-03-01', 'value' => 2]],
      GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED => [['bucket' => '2026-03-01', 'value' => 1]],
    ], $output->seriesByMetric);
  }

  #[Test]
  public function testProvideIgnoresPrimaryMetricAndDuplicatesWithinMetricsFilter(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardTrendQuery $query): bool {
        return [GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED] === $query->additionalMetrics;
      }))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'none', 'timezone' => 'UTC'],
        summary: ['total' => 0],
        series: [],
        comparison: ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      // primary metric repeated + resolved metric duplicated + blank entries: must dedupe to a single additional metric.
      ['filters' => ['metrics' => 'non_conformities_opened, non_conformities_resolved,,non_conformities_resolved']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenMetricsFilterUsedOnNonCombinableTrend(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The "metrics" filter is not supported for this trend.');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['metrics' => 'equipment_created']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenMetricsFilterValueIsUnknown(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createMetricAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "metrics" filter value "equipment_created".');

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['metrics' => 'equipment_created']],
    );
  }

  #[Test]
  public function testProvideCombinesMetricsFilterWithNonConformityScopedFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    // Both combinable non-conformity metrics share the same permission
    // ("organization.inspection.read"), so this asserts the provider still runs a SEPARATE
    // assertMetricsPermissions() pass for the additional metric (2 total hasPermission calls)
    // rather than skipping it because the primary metric already passed, and that the
    // `nonConformityStatus`/`nonConformitySeverity` filters still reach the query alongside
    // `metrics` — the combined chart is filtered the same way as either single-metric call.
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))
      ->method('hasPermission')
      ->with('550e8400-e29b-41d4-a716-446655441600', '550e8400-e29b-41d4-a716-446655441610', 'organization.inspection.read')
      ->willReturn(true);

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardTrendQuery $query): bool {
        return [GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED] === $query->additionalMetrics
          && 'critical' === $query->nonConformitySeverity
          && 'open' === $query->nonConformityStatus;
      }))
      ->willReturn(new GetOrganizationDashboardTrendResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        metric: GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'none', 'timezone' => 'UTC'],
        summary: ['total' => 0],
        series: [],
        comparison: ['mode' => 'none', 'from' => null, 'to' => null, 'summary' => [], 'series' => []],
      ));

    $provider = new GetOrganizationDashboardTrendProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(name: OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'metrics' => 'non_conformities_resolved',
        'nonConformitySeverity' => 'critical',
        'nonConformityStatus' => 'open',
      ]],
    );
  }

  /**
   * @return array<string, array{0: string, 1: array<string, string>, 2: string}>
   */
  public static function metricScopedUnsupportedFilterProvider(): array
  {
    return [
      'inspection trend rejects non-conformity status' => [
        OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND,
        ['nonConformityStatus' => 'open'],
        'Filter "nonConformityStatus" is not supported for the inspection trend.',
      ],
      'equipment trend rejects inspection status' => [
        OrganizationOperations::GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND,
        ['inspectionStatus' => 'closed'],
        'Filter "inspectionStatus" is not supported for the equipment-created trend.',
      ],
      'facilities trend rejects equipment type' => [
        OrganizationOperations::GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND,
        ['equipmentType' => 'fire_extinguisher'],
        'Filter "equipmentType" is not supported for the facilities-created trend.',
      ],
      'non-conformity trend rejects inspection status' => [
        OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND,
        ['inspectionStatus' => 'closed'],
        'Filter "inspectionStatus" is not supported for the non-conformity trend.',
      ],
    ];
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
