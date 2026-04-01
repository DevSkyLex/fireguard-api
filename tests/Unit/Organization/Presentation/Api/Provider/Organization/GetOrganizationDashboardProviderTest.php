<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
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
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

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
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createDashboardAuthorizationMock(['organization.equipment.read']),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.equipment.read permission.');

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideMapsLightDashboardPayloadAndExtractsFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-01T00:00:00.500000+00:00' === $query->periodFrom
          && '2026-03-15T23:59:59.250000+00:00' === $query->periodTo
          && false === $query->compareWithPreviousPeriod
          && 'Europe/Paris' === $query->timeZone;
      }))
      ->willReturn(new GetOrganizationDashboardResult(
        generatedAt: '2026-03-29T10:00:00.125000+00:00',
        period: [
          'from' => '2026-03-01T01:00:00+01:00',
          'to' => '2026-03-16T00:59:59+01:00',
          'comparison' => 'previous_period',
          'timezone' => 'Europe/Paris',
        ],
        overview: [
          'members' => ['total' => 4, 'active' => 3, 'inactive' => 1],
          'facilities' => ['total' => 2, 'active' => 2, 'archived' => 0],
        ],
        health: [
          'memberActivationRate' => 75.0,
          'periodInspectionCompletionRate' => 50.0,
        ],
        alerts: [['code' => 'expired_invitations', 'severity' => 'medium', 'count' => 2]],
        comparison: [
          'mode' => 'previous_period',
          'from' => '2026-02-14T01:00:00+01:00',
          'to' => '2026-03-01T00:59:59+01:00',
          'current' => ['inspectionsPerformed' => 8],
          'previous' => ['inspectionsPerformed' => 4],
          'deltas' => ['inspectionsPerformed' => 100.0],
          'health' => [
            'current' => ['inspectionCompletionRate' => 50.0],
            'previous' => ['inspectionCompletionRate' => 40.0],
            'deltas' => ['inspectionCompletionRate' => 25.0],
          ],
        ],
      ));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $output = $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'from' => '2026-03-01T00:00:00.500000+00:00',
        'to' => '2026-03-15T23:59:59.250000+00:00',
        'compare' => 'false',
        'timezone' => 'Europe/Paris',
      ]],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    /** @var array{from: string, to: string, comparison: string, timezone: string} $period */
    $period = $output->period;

    self::assertSame('2026-03-29T10:00:00.125000+00:00', $output->generatedAt);
    self::assertSame('Europe/Paris', $period['timezone']);
    self::assertArrayNotHasKey('granularity', $period);
    self::assertSame('total', $output->overview['members']['summary'][0]['key']);
    self::assertSame(4, $output->overview['members']['summary'][0]['value']);
    self::assertSame('active', $output->overview['members']['summary'][1]['key']);
    self::assertArrayNotHasKey('breakdowns', $output->overview['facilities']);
    self::assertSame('memberActivationRate', $output->health['metrics'][0]['key']);
    self::assertSame(75.0, $output->health['metrics'][0]['value']);
    self::assertSame('expired_invitations', $output->alerts[0]['code']);
    self::assertSame('previous_period', $output->comparison['mode']);
    self::assertSame('inspections_performed', $output->comparison['metrics'][0]['metric']);
    self::assertSame(8, $output->comparison['metrics'][0]['current']);
    self::assertSame('inspectionCompletionRate', $output->comparison['health']['metrics'][0]['key']);
    self::assertArrayNotHasKey('trendMetrics', get_object_vars($output));
    self::assertArrayNotHasKey('total', $output->overview['members']);
  }

  #[Test]
  public function testProvideThrowsWhenDatetimeFiltersAreInvalid(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $this->createMock(QueryBusPort::class),
      authorization: $this->createDashboardAuthorizationMock(),
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
  public function testProvideAcceptsTimezoneFilterForMixedOffsets(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return '2026-03-29T00:00:00+01:00' === $query->periodFrom
          && '2026-03-30T23:59:59+02:00' === $query->periodTo
          && 'Europe/Paris' === $query->timeZone;
      }))
      ->willReturn($this->createEmptyResult(
        period: [
          'from' => '2026-03-29T00:00:00+01:00',
          'to' => '2026-03-30T23:59:59+02:00',
          'comparison' => 'previous_period',
          'timezone' => 'Europe/Paris',
        ],
      ));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
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
    /** @var array{from: string, to: string, comparison: string, timezone: string} $period */
    $period = $output->period;

    self::assertSame('Europe/Paris', $period['timezone']);
  }

  #[Test]
  public function testProvideExtractsAnalyticsFilters(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

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
      ->willReturn($this->createEmptyResult());

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $output = $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => [
        'facilityType' => 'site',
        'equipmentType' => 'fire_extinguisher',
        'equipmentStatus' => 'operational',
        'inspectionStatus' => 'closed',
        'inspectionResult' => 'pass',
        'inspectorType' => 'user',
        'nonConformityStatus' => 'open',
        'nonConformitySeverity' => 'critical',
      ]],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
  }

  #[Test]
  public function testProvideThrowsWhenAnalyticsEnumFilterIsInvalid(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

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

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::never())->method('ask');

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Invalid "compare" filter.');

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => 'maybe']],
    );
  }

  #[Test]
  #[DataProvider('documentedStringCompareFilterProvider')]
  public function testProvideAcceptsDocumentedStringCompareAliases(string $compare, bool $expected): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static fn (GetOrganizationDashboardQuery $query): bool => $expected === $query->compareWithPreviousPeriod))
      ->willReturn($this->createEmptyResult(period: ['comparison' => $expected ? 'previous_period' : 'none']));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $provider->provide(
      new Get(),
      ['organizationId' => '550e8400-e29b-41d4-a716-446655441610'],
      ['filters' => ['compare' => $compare]],
    );
  }

  #[Test]
  public function testProvideMapsWrappedPermissionDenialToHttp403(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

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
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $this->expectException(AccessDeniedHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
  }

  #[Test]
  public function testProvideMapsWrappedOrganizationNotFoundToHttp404(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

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
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);
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

  /**
   * @param array<string, string> $period
   * @param array<string, array<string, int>> $overview
   * @param array<string, float> $health
   * @param list<array{code: string, severity: string, count: int}> $alerts
   * @param array<string, mixed> $comparison
   */
  private function createEmptyResult(
    array $period = ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'comparison' => 'none', 'timezone' => 'UTC'],
    array $overview = [],
    array $health = [],
    array $alerts = [],
    array $comparison = ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
  ): GetOrganizationDashboardResult {
    return new GetOrganizationDashboardResult(
      generatedAt: '2026-03-29T10:00:00+00:00',
      period: $period,
      overview: $overview,
      health: $health,
      alerts: $alerts,
      comparison: $comparison,
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
