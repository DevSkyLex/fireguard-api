<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard\{GetOrganizationDashboardQuery, GetOrganizationDashboardResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationDashboardOutput;
use Organization\Presentation\Api\Provider\Organization\GetOrganizationDashboardProvider;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

use function get_object_vars;
use function in_array;

#[CoversClass(GetOrganizationDashboardProvider::class)]
final class GetOrganizationDashboardProviderTest extends TestCase
{
  #[Test]
  public function testProvideThrowsWhenUnauthenticated(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn(null);

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideReturnsNullWhenOrganizationIdIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createMock(OrganizationAuthorizationPort::class),
      security: $security,
    );

    self::assertNull($provider->provide(new Get(), []));
  }

  #[Test]
  public function testProvideThrowsWhenDashboardDependencyPermissionIsMissing(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock(['organization.equipment.read']);

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.equipment.read permission.');

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideMapsDashboardPayloadAndExtractsFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-01T00:00:00.500000+00:00' === $query->periodFrom
          && '2026-03-15T23:59:59.250000+00:00' === $query->periodTo
          && false === $query->compareWithPreviousPeriod
          && 'week' === $query->granularity
          && null === $query->timeZone;
      }))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-29T10:00:00.125000+00:00',
        period: [
          'from' => '2026-03-01T00:00:00.500000+00:00',
          'to' => '2026-03-15T23:59:59.250000+00:00',
          'granularity' => 'week',
          'comparison' => 'none',
          'timezone' => 'UTC',
        ],
        overview: [
          'members' => ['total' => 4],
          'facilities' => ['byType' => ['site' => 2]],
          'equipment' => ['byStatus' => ['operational' => 3]],
          'inspections' => ['byStatus' => ['closed' => 1]],
          'nonConformities' => ['bySeverity' => ['critical' => 1]],
        ],
        health: [
          'memberActivationRate' => 75.0,
          'periodInspectionCompletionRate' => 50.0,
        ],
        alerts: [['code' => 'expired_invitations', 'severity' => 'medium', 'count' => 2]],
        trends: ['inspectionsPerformed' => [['bucket' => '2026-W10', 'value' => 1]]],
        comparison: [
          'mode' => 'none',
          'current' => [],
          'previous' => [],
          'deltas' => [],
          'health' => [
            'current' => [],
            'previous' => [],
            'deltas' => [],
          ],
        ],
      ));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['from' => '2026-03-01T00:00:00.500000+00:00', 'to' => '2026-03-15T23:59:59.250000+00:00', 'compare' => 'false', 'granularity' => 'week']],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    self::assertSame('2026-03-29T10:00:00.125000+00:00', $output->generatedAt);
    self::assertArrayHasKey('from', $output->period);
    self::assertArrayHasKey('granularity', $output->period);
    /** @var array{from: string, granularity: string} $period */
    $period = $output->period;
    self::assertSame('2026-03-01T00:00:00.500000+00:00', $period['from']);
    self::assertSame('week', $period['granularity']);
    /** @var array{members: array{summary: list<array{key: string, value: int}>}, facilities: array{breakdowns: list<array{key: string, items: list<array{key: string, value: int}>}>}, equipment: array{breakdowns: list<array{key: string, items: list<array{key: string, value: int}>}>}, inspections: array{breakdowns: list<array{key: string, items: list<array{key: string, value: int}>}>}, nonConformities: array{breakdowns: list<array{key: string, items: list<array{key: string, value: int}>}>}} $overview */
    $overview = $output->overview;
    /** @var array{metrics: list<array{key: string, value: float, unit: string}>} $health */
    $health = $output->health;
    /** @var list<array{code: string, severity: string, count: int}> $alerts */
    $alerts = $output->alerts;
    /** @var array{mode: string, metrics: list<mixed>, health: array{metrics: list<mixed>}} $comparison */
    $comparison = $output->comparison;

    self::assertSame('total', $overview['members']['summary'][0]['key']);
    self::assertSame(4, $overview['members']['summary'][0]['value']);
    self::assertSame('type', $overview['facilities']['breakdowns'][0]['key']);
    self::assertSame('site', $overview['facilities']['breakdowns'][0]['items'][0]['key']);
    self::assertSame(2, $overview['facilities']['breakdowns'][0]['items'][0]['value']);
    self::assertSame('status', $overview['equipment']['breakdowns'][0]['key']);
    self::assertSame(3, $overview['equipment']['breakdowns'][0]['items'][0]['value']);
    self::assertSame('status', $overview['inspections']['breakdowns'][0]['key']);
    self::assertSame(1, $overview['inspections']['breakdowns'][0]['items'][0]['value']);
    self::assertSame('severity', $overview['nonConformities']['breakdowns'][0]['key']);
    self::assertSame(1, $overview['nonConformities']['breakdowns'][0]['items'][0]['value']);
    self::assertSame('memberActivationRate', $health['metrics'][0]['key']);
    self::assertSame(75.0, $health['metrics'][0]['value']);
    self::assertSame('percent', $health['metrics'][0]['unit']);
    self::assertSame('periodInspectionCompletionRate', $health['metrics'][1]['key']);
    self::assertSame(50.0, $health['metrics'][1]['value']);
    self::assertSame('expired_invitations', $alerts[0]['code']);
    self::assertSame('none', $comparison['mode']);
    self::assertSame([], $comparison['metrics']);
    self::assertSame([], $comparison['health']['metrics']);
    self::assertSame('inspections_performed', $output->trendMetrics[0]['metric']);
    self::assertSame(1, $output->trendMetrics[0]['summary']['total']);
    self::assertArrayNotHasKey('trends', get_object_vars($output));
    self::assertArrayNotHasKey('total', $overview['members']);
    self::assertArrayNotHasKey('byType', $overview['facilities']);
    self::assertArrayNotHasKey('memberActivationRate', $health);
    self::assertArrayNotHasKey('current', $comparison);
    self::assertArrayNotHasKey('previous', $comparison);
    self::assertArrayNotHasKey('deltas', $comparison);
  }

  #[Test]
  public function testProvideAcceptsAutoGranularityFilter(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return 'auto' === $query->granularity;
      }))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        period: [
          'from' => '2026-01-01T00:00:00+00:00',
          'to' => '2026-03-31T23:59:59+00:00',
          'granularity' => 'month',
          'comparison' => 'none',
        ],
        overview: [],
        health: [],
        alerts: [],
        trends: ['inspectionsPerformed' => [['bucket' => '2026-01', 'value' => 1]]],
        comparison: [
          'mode' => 'none',
          'current' => [],
          'previous' => [],
          'deltas' => [],
          'health' => [
            'current' => [],
            'previous' => [],
            'deltas' => [],
          ],
        ],
      ));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['granularity' => 'auto']],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    self::assertArrayHasKey('granularity', $output->period);
    /** @var array{granularity: string} $period */
    $period = $output->period;
    self::assertSame('month', $period['granularity']);
  }

  #[Test]
  public function testProvideThrowsWhenDatetimeFiltersAreInvalid(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['from' => 'not-a-date']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenDatetimeFiltersAreChronologicallyInvalidAcrossTimezones(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-01T09:30:00+01:00' === $query->periodFrom
          && '2026-03-01T10:00:00+02:00' === $query->periodTo
          && null === $query->timeZone;
      }))
      ->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
        new Envelope(new GetOrganizationDashboardQuery(
          organizationId: '550e8400-e29b-41d4-a716-446655441610',
          userId: '550e8400-e29b-41d4-a716-446655441600',
        )),
        [new InvalidArgumentException('The "from" datetime filter must be before or equal to "to".')],
      )));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The "from" datetime filter must be before or equal to "to".');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2026-03-01T09:30:00+01:00',
        'to' => '2026-03-01T10:00:00+02:00',
      ]],
    );
  }

  #[Test]
  public function testProvideThrowsWhenMixedOffsetsAreProvidedWithoutTimezone(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-29T00:00:00+01:00' === $query->periodFrom
          && '2026-03-30T23:59:59+02:00' === $query->periodTo
          && null === $query->timeZone;
      }))
      ->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
        new Envelope(new GetOrganizationDashboardQuery(
          organizationId: '550e8400-e29b-41d4-a716-446655441610',
          userId: '550e8400-e29b-41d4-a716-446655441600',
        )),
        [new InvalidArgumentException('Mixed timezone offsets require the "timezone" filter.')],
      )));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Mixed timezone offsets require the "timezone" filter.');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2026-03-29T00:00:00+01:00',
        'to' => '2026-03-30T23:59:59+02:00',
      ]],
    );
  }

  #[Test]
  public function testProvideAcceptsTimezoneFilterForMixedOffsets(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-29T00:00:00+01:00' === $query->periodFrom
          && '2026-03-30T23:59:59+02:00' === $query->periodTo
          && 'Europe/Paris' === $query->timeZone;
      }))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-30T10:00:00+02:00',
        period: [
          'from' => '2026-03-29T00:00:00+01:00',
          'to' => '2026-03-30T23:59:59+02:00',
          'granularity' => 'day',
          'comparison' => 'previous_period',
          'timezone' => 'Europe/Paris',
        ],
        overview: [],
        health: [],
        alerts: [],
        trends: ['inspectionsPerformed' => [['bucket' => '2026-03-29', 'value' => 1]]],
        comparison: [
          'mode' => 'previous_period',
          'from' => '2026-03-27T00:00:00+01:00',
          'to' => '2026-03-28T23:59:59+01:00',
          'current' => [],
          'previous' => [],
          'deltas' => [],
          'health' => [
            'current' => [],
            'previous' => [],
            'deltas' => [],
          ],
        ],
      ));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2026-03-29T00:00:00+01:00',
        'to' => '2026-03-30T23:59:59+02:00',
        'timezone' => 'Europe/Paris',
      ]],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    self::assertArrayHasKey('timezone', $output->period);
    /** @var array{timezone: string} $period */
    $period = $output->period;
    self::assertSame('Europe/Paris', $period['timezone']);
  }

  #[Test]
  public function testProvideAcceptsSingleExplicitBoundWithoutTimezoneFilter(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-01T00:00:00+00:00' === $query->periodFrom
          && null === $query->periodTo
          && null === $query->timeZone;
      }))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-30T10:00:00+00:00',
        period: [
          'from' => '2026-03-01T00:00:00+00:00',
          'to' => '2026-03-30T10:00:00+00:00',
          'granularity' => 'day',
          'comparison' => 'previous_period',
          'timezone' => 'UTC',
        ],
        overview: [],
        health: [],
        alerts: [],
        trends: ['inspectionsPerformed' => []],
        comparison: [
          'mode' => 'previous_period',
          'from' => '2026-01-31T13:59:59+00:00',
          'to' => '2026-03-01T23:59:59+00:00',
          'current' => [],
          'previous' => [],
          'deltas' => [],
          'health' => [
            'current' => [],
            'previous' => [],
            'deltas' => [],
          ],
        ],
      ));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $output = $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['from' => '2026-03-01T00:00:00+00:00']],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    self::assertArrayHasKey('timezone', $output->period);
    /** @var array{timezone: string} $period */
    $period = $output->period;
    self::assertSame('UTC', $period['timezone']);
  }

  #[Test]
  public function testProvideRejectsSingleExplicitNonUtcOffsetWithoutTimezoneFilter(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-01T00:00:00+01:00' === $query->periodFrom
          && null === $query->periodTo
          && null === $query->timeZone;
      }))
      ->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
        new Envelope(new GetOrganizationDashboardQuery(
          organizationId: '550e8400-e29b-41d4-a716-446655441610',
          userId: '550e8400-e29b-41d4-a716-446655441600',
        )),
        [new InvalidArgumentException('Non-UTC offset datetimes require the "timezone" filter.')],
      )));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Non-UTC offset datetimes require the "timezone" filter.');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['from' => '2026-03-01T00:00:00+01:00']],
    );
  }

  #[Test]
  public function testProvideRejectsFixedOffsetTimezoneFilter(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['timezone' => '+01:00']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenRequestedPeriodExceedsSupportedRange(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2025-01-01T00:00:00+00:00' === $query->periodFrom
          && '2026-12-31T23:59:59+00:00' === $query->periodTo
          && null === $query->timeZone;
      }))
      ->willThrowException(MessengerRuntimeException::wrap(new HandlerFailedException(
        new Envelope(new GetOrganizationDashboardQuery(
          organizationId: '550e8400-e29b-41d4-a716-446655441610',
          userId: '550e8400-e29b-41d4-a716-446655441600',
        )),
        [new InvalidArgumentException('Dashboard period cannot exceed 366 days.')],
      )));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Dashboard period cannot exceed 366 days.');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2025-01-01T00:00:00+00:00',
        'to' => '2026-12-31T23:59:59+00:00',
      ]],
    );
  }

  #[Test]
  public function testProvideThrowsWhenGranularityFilterIsInvalid(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['granularity' => 'quarter']],
    );
  }

  #[Test]
  public function testProvideMapsWrappedPermissionDenialToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationDashboardQuery('550e8400-e29b-41d4-a716-446655441610', '550e8400-e29b-41d4-a716-446655441600')),
      [OrganizationAccessDeniedException::missingPermission('organization.dashboard.read')],
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationDashboardQuery::class))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideMapsWrappedOrganizationNotFoundToHttp404(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())
      ->method('getUser')
      ->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $handlerFailure = new HandlerFailedException(
      new Envelope(new GetOrganizationDashboardQuery('550e8400-e29b-41d4-a716-446655441610', '550e8400-e29b-41d4-a716-446655441600')),
      [OrganizationNotFoundException::withId('550e8400-e29b-41d4-a716-446655441610')],
    );

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::isInstanceOf(GetOrganizationDashboardQuery::class))
      ->willThrowException(MessengerRuntimeException::wrap($handlerFailure));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $authorization,
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideExtractsAnalyticsFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return 'site' === $query->facilityType
          && 'fire_extinguisher' === $query->equipmentType
          && 'operational' === $query->equipmentStatus
          && 'closed' === $query->inspectionStatus
          && 'pass' === $query->inspectionResult
          && 'user' === $query->inspectorType
          && 'open' === $query->nonConformityStatus
          && 'critical' === $query->nonConformitySeverity;
      }))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'none', 'timezone' => 'UTC'],
        overview: [],
        health: [],
        alerts: [],
        trends: ['inspectionsPerformed' => []],
        comparison: ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
      ));

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $output = $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['facilityType' => 'site', 'equipmentType' => 'fire_extinguisher', 'equipmentStatus' => 'operational', 'inspectionStatus' => 'closed', 'inspectionResult' => 'pass', 'inspectorType' => 'user', 'nonConformityStatus' => 'open', 'nonConformitySeverity' => 'critical']],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
  }

  #[Test]
  public function testProvideThrowsWhenAnalyticsEnumFilterIsInvalid(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['equipmentStatus' => 'broken']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenCompareFilterIsInvalidBoolean(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "compare" filter.');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => 'maybe']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenCompareFilterIsEmptyString(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "compare" filter.');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => '']],
    );
  }

  #[Test]
  public function testProvideThrowsWhenCompareFilterIsNonScalar(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "compare" filter.');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => ['maybe']]],
    );
  }

  #[Test]
  public function testProvideAcceptsNativeBooleanCompareFilter(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationDashboardQuery $query): bool => true === $query->compareWithPreviousPeriod))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'previous_period', 'timezone' => 'UTC'],
        overview: [],
        health: [],
        alerts: [],
        trends: ['inspectionsPerformed' => []],
        comparison: ['mode' => 'previous_period', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
      ));

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => true]],
    );
  }

  #[Test]
  public function testProvideAcceptsCompareAliasOff(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationDashboardQuery $query): bool => false === $query->compareWithPreviousPeriod))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => 'none', 'timezone' => 'UTC'],
        overview: [],
        health: [],
        alerts: [],
        trends: ['inspectionsPerformed' => []],
        comparison: ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
      ));

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => 'off']],
    );
  }

  #[Test]
  #[DataProvider('documentedStringCompareFilterProvider')]
  public function testProvideAcceptsDocumentedStringCompareAliases(string $compare, bool $expected): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $authorization = $this->createDashboardAuthorizationMock();
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationDashboardQuery $query): bool => $expected === $query->compareWithPreviousPeriod))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-29T10:00:00+00:00',
        period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'granularity' => 'day', 'comparison' => $expected ? 'previous_period' : 'none', 'timezone' => 'UTC'],
        overview: [],
        health: [],
        alerts: [],
        trends: ['inspectionsPerformed' => []],
        comparison: ['mode' => $expected ? 'previous_period' : 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
      ));

    $provider = new GetOrganizationDashboardProvider(queryBus: $queryBus, authorization: $authorization, security: $security);

    $provider->provide(
      new Get(),
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
   */
  private function createDashboardAuthorizationMock(array $deniedPermissions = []): OrganizationAuthorizationPort
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->willReturnCallback(static function (string $userId, string $organizationId, array $permissions) use ($deniedPermissions): void {
        self::assertSame('550e8400-e29b-41d4-a716-446655441600', $userId);
        self::assertSame('550e8400-e29b-41d4-a716-446655441610', $organizationId);
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
