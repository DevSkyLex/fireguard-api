<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Get;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
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
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

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
      queryBus: $this->createStub(QueryBusPort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
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
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
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
      queryBus: $this->createStub(QueryBusPort::class),
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
          'current' => [
            'inspectionsPerformed' => 8,
            'facilitiesCreated' => 3,
            'membersJoined' => 4,
            'equipmentCreated' => 6,
            'nonConformitiesOpened' => 2,
            'nonConformitiesResolved' => 1,
          ],
          'previous' => [
            'inspectionsPerformed' => 4,
            'facilitiesCreated' => 2,
            'membersJoined' => 1,
            'equipmentCreated' => 5,
            'nonConformitiesOpened' => 1,
            'nonConformitiesResolved' => 0,
          ],
          'deltas' => [
            'inspectionsPerformed' => 100.0,
            'facilitiesCreated' => 50.0,
            'membersJoined' => 300.0,
            'equipmentCreated' => 20.0,
            'nonConformitiesOpened' => 100.0,
            'nonConformitiesResolved' => 100.0,
          ],
          'health' => [
            'current' => ['inspectionCompletionRate' => 50.0],
            'previous' => ['inspectionCompletionRate' => 40.0],
            'deltas' => ['inspectionCompletionRate' => 25.0],
          ],
        ],
        trends: [
          'facilities' => [['bucket' => '2026-03-01', 'value' => 2]],
          'members' => [['bucket' => '2026-03-01', 'value' => 4]],
          'equipment' => [['bucket' => '2026-03-01', 'value' => 6]],
          'inspections' => [['bucket' => '2026-03-01', 'value' => 8]],
        ],
        recentInterventions: [],
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
    $membersPrimary = $output->overview['members']['primary'];
    self::assertNotNull($membersPrimary);
    self::assertSame('total', $membersPrimary['key']);
    self::assertSame(4, $membersPrimary['value']);
    self::assertSame('active', $output->overview['members']['summary'][1]['key']);
    self::assertArrayNotHasKey('breakdowns', $output->overview['facilities']);
    $facilitiesPrimary = $output->overview['facilities']['primary'];
    self::assertNotNull($facilitiesPrimary);
    self::assertSame('total', $facilitiesPrimary['key']);
    self::assertSame(2, $facilitiesPrimary['value']);
    self::assertSame('memberActivationRate', $output->health['metrics'][0]['key']);
    self::assertSame(75.0, $output->health['metrics'][0]['value']);
    self::assertSame('percent', $output->health['metrics'][0]['unit']);
    self::assertSame(100.0, $output->health['metrics'][0]['max']);
    self::assertSame('expired_invitations', $output->alerts[0]['code']);
    self::assertSame('previous_period', $output->comparison['mode']);
    self::assertSame('inspections', $output->comparison['metrics'][0]['key']);
    self::assertSame('inspections_performed', $output->comparison['metrics'][0]['metric']);
    self::assertSame('Inspections', $output->comparison['metrics'][0]['label']);
    self::assertSame('+4', $output->comparison['metrics'][0]['value']);
    self::assertSame(8, $output->comparison['metrics'][0]['current']);
    self::assertSame('up', $output->comparison['metrics'][0]['direction']);
    self::assertSame('facilities', $output->comparison['metrics'][1]['key']);
    self::assertSame('facilities_created', $output->comparison['metrics'][1]['metric']);
    self::assertSame('Facilities', $output->comparison['metrics'][1]['label']);
    self::assertSame('+1', $output->comparison['metrics'][1]['value']);
    self::assertSame(3, $output->comparison['metrics'][1]['current']);
    self::assertSame(2, $output->comparison['metrics'][1]['previous']);
    self::assertSame(50.0, $output->comparison['metrics'][1]['delta']);
    self::assertSame('up', $output->comparison['metrics'][1]['direction']);
    self::assertSame('members', $output->comparison['metrics'][2]['key']);
    self::assertSame('members_joined', $output->comparison['metrics'][2]['metric']);
    self::assertSame('Members', $output->comparison['metrics'][2]['label']);
    self::assertSame('+3', $output->comparison['metrics'][2]['value']);
    self::assertSame(4, $output->comparison['metrics'][2]['current']);
    self::assertSame(1, $output->comparison['metrics'][2]['previous']);
    self::assertSame(300.0, $output->comparison['metrics'][2]['delta']);
    self::assertSame('up', $output->comparison['metrics'][2]['direction']);
    self::assertSame('equipment', $output->comparison['metrics'][3]['key']);
    self::assertSame('equipment_created', $output->comparison['metrics'][3]['metric']);
    self::assertSame('Equipment', $output->comparison['metrics'][3]['label']);
    self::assertSame('+1', $output->comparison['metrics'][3]['value']);
    self::assertSame(6, $output->comparison['metrics'][3]['current']);
    self::assertSame(5, $output->comparison['metrics'][3]['previous']);
    self::assertSame(20.0, $output->comparison['metrics'][3]['delta']);
    self::assertSame('up', $output->comparison['metrics'][3]['direction']);
    self::assertSame('nonConformitiesOpened', $output->comparison['metrics'][4]['key']);
    self::assertSame('non_conformities_opened', $output->comparison['metrics'][4]['metric']);
    self::assertSame('Non-Conformities Opened', $output->comparison['metrics'][4]['label']);
    self::assertSame('+1', $output->comparison['metrics'][4]['value']);
    self::assertSame(2, $output->comparison['metrics'][4]['current']);
    self::assertSame(1, $output->comparison['metrics'][4]['previous']);
    self::assertSame(100.0, $output->comparison['metrics'][4]['delta']);
    self::assertSame('up', $output->comparison['metrics'][4]['direction']);
    self::assertSame('nonConformitiesResolved', $output->comparison['metrics'][5]['key']);
    self::assertSame('non_conformities_resolved', $output->comparison['metrics'][5]['metric']);
    self::assertSame('Non-Conformities Resolved', $output->comparison['metrics'][5]['label']);
    self::assertSame('+1', $output->comparison['metrics'][5]['value']);
    self::assertSame(1, $output->comparison['metrics'][5]['current']);
    self::assertSame(0, $output->comparison['metrics'][5]['previous']);
    self::assertSame(100.0, $output->comparison['metrics'][5]['delta']);
    self::assertSame('up', $output->comparison['metrics'][5]['direction']);
    self::assertSame('inspectionCompletionRate', $output->comparison['health']['metrics'][0]['key']);
    self::assertSame('percent', $output->comparison['health']['metrics'][0]['unit']);
    self::assertSame(100.0, $output->comparison['health']['metrics'][0]['max']);
    self::assertSame('up', $output->comparison['health']['metrics'][0]['direction']);
    self::assertArrayNotHasKey('trendMetrics', get_object_vars($output));
    self::assertSame([['bucket' => '2026-03-01', 'value' => 2]], $output->trends['facilities']);
    self::assertSame([['bucket' => '2026-03-01', 'value' => 4]], $output->trends['members']);
    self::assertSame([['bucket' => '2026-03-01', 'value' => 6]], $output->trends['equipment']);
    self::assertSame([['bucket' => '2026-03-01', 'value' => 8]], $output->trends['inspections']);
    self::assertSame([], $output->recentInterventions);
    self::assertArrayNotHasKey('total', $output->overview['members']);
  }

  #[Test]
  public function testProvideFallsBackToFirstPrimaryMetricAndMapsDirectionVariants(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($this->createEmptyResult(
        period: [
          'from' => '2026-03-01T00:00:00+00:00',
          'to' => '2026-03-31T23:59:59+00:00',
          'comparison' => 'previous_period',
          'timezone' => 'UTC',
        ],
        overview: [
          'roles' => ['custom' => 1],
          'invitations' => ['accepted' => 1, 'pending' => 2],
        ],
        health: [
          'memberActivationRate' => 100.0,
        ],
        comparison: [
          'mode' => 'previous_period',
          'from' => '2026-01-30T00:00:00+00:00',
          'to' => '2026-02-28T23:59:59+00:00',
          'current' => [
            'inspectionsPerformed' => 5,
            'facilitiesCreated' => 1,
            'membersJoined' => 0,
            'equipmentCreated' => 3,
            'nonConformitiesOpened' => 1,
            'nonConformitiesResolved' => 0,
          ],
          'previous' => [
            'inspectionsPerformed' => 5,
            'facilitiesCreated' => 2,
            'membersJoined' => 0,
            'equipmentCreated' => 1,
            'nonConformitiesOpened' => 2,
            'nonConformitiesResolved' => 1,
          ],
          'deltas' => [
            'inspectionsPerformed' => 0.0,
            'facilitiesCreated' => -50.0,
            'equipmentCreated' => 200.0,
            'nonConformitiesOpened' => -50.0,
            'nonConformitiesResolved' => -100.0,
          ],
          'health' => [
            'current' => [
              'memberActivationRate' => 100.0,
              'inspectionCompletionRate' => 75.0,
              'periodInspectionCompletionRate' => 75.0,
            ],
            'previous' => [
              'memberActivationRate' => 100.0,
              'inspectionCompletionRate' => 80.0,
              'periodInspectionCompletionRate' => 80.0,
            ],
            'deltas' => [
              'memberActivationRate' => 0.0,
              'inspectionCompletionRate' => -6.25,
            ],
          ],
        ],
      ));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    $rolesPrimary = $output->overview['roles']['primary'];
    self::assertNotNull($rolesPrimary);
    self::assertSame('custom', $rolesPrimary['key']);
    self::assertSame(1, $rolesPrimary['value']);
    $invitationsPrimary = $output->overview['invitations']['primary'];
    self::assertNotNull($invitationsPrimary);
    self::assertSame('pending', $invitationsPrimary['key']);
    self::assertSame(2, $invitationsPrimary['value']);

    $comparisonMetrics = [];
    foreach ($output->comparison['metrics'] as $metric) {
      $comparisonMetrics[$metric['key']] = $metric;
    }

    self::assertSame('stable', $comparisonMetrics['inspections']['direction']);
    self::assertSame('0', $comparisonMetrics['inspections']['value']);
    self::assertSame('down', $comparisonMetrics['facilities']['direction']);
    self::assertSame('-1', $comparisonMetrics['facilities']['value']);
    self::assertSame('stable', $comparisonMetrics['members']['direction']);
    self::assertSame('0', $comparisonMetrics['members']['value']);
    self::assertSame('up', $comparisonMetrics['equipment']['direction']);
    self::assertSame('+2', $comparisonMetrics['equipment']['value']);
    self::assertSame('down', $comparisonMetrics['nonConformitiesOpened']['direction']);
    self::assertSame('-1', $comparisonMetrics['nonConformitiesOpened']['value']);
    self::assertSame('down', $comparisonMetrics['nonConformitiesResolved']['direction']);
    self::assertSame('-1', $comparisonMetrics['nonConformitiesResolved']['value']);

    $comparisonHealthMetrics = [];
    foreach ($output->comparison['health']['metrics'] as $metric) {
      $comparisonHealthMetrics[$metric['key']] = $metric;
    }

    self::assertSame('stable', $comparisonHealthMetrics['memberActivationRate']['direction']);
    self::assertSame('down', $comparisonHealthMetrics['inspectionCompletionRate']['direction']);
    self::assertNull($comparisonHealthMetrics['periodInspectionCompletionRate']['direction']);
  }

  #[Test]
  public function testProvideUsesSelectedStatusFiltersAsOverviewPrimaryMetrics(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->with(self::callback(static function (GetOrganizationDashboardQuery $query): bool {
        return 'in_stock' === $query->equipmentStatus
          && 'submitted' === $query->inspectionStatus
          && 'in_progress' === $query->nonConformityStatus;
      }))
      ->willReturn($this->createEmptyResult(
        overview: [
          'equipment' => [
            'total' => 1,
            'inStock' => 1,
            'operational' => 0,
            'underMaintenance' => 0,
            'decommissioned' => 0,
          ],
          'inspections' => [
            'total' => 4,
            'draft' => 0,
            'submitted' => 4,
            'closed' => 0,
            'pass' => 0,
            'fail' => 2,
            'partial' => 2,
          ],
          'nonConformities' => [
            'total' => 3,
            'open' => 0,
            'inProgress' => 3,
            'done' => 0,
            'waived' => 0,
            'overdue' => 1,
            'criticalOpen' => 0,
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
        'equipmentStatus' => 'in_stock',
        'inspectionStatus' => 'submitted',
        'nonConformityStatus' => 'in_progress',
      ]],
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    $equipmentPrimary = $output->overview['equipment']['primary'];
    self::assertNotNull($equipmentPrimary);
    self::assertSame('inStock', $equipmentPrimary['key']);
    self::assertSame(1, $equipmentPrimary['value']);
    $inspectionsPrimary = $output->overview['inspections']['primary'];
    self::assertNotNull($inspectionsPrimary);
    self::assertSame('submitted', $inspectionsPrimary['key']);
    self::assertSame(4, $inspectionsPrimary['value']);
    $nonConformitiesPrimary = $output->overview['nonConformities']['primary'];
    self::assertNotNull($nonConformitiesPrimary);
    self::assertSame('inProgress', $nonConformitiesPrimary['key']);
    self::assertSame(3, $nonConformitiesPrimary['value']);
  }

  /**
   * L3.10: the by-severity breakdown the handler adds to
   * `overview.nonConformities` (`severityLow`/`severityMedium`/
   * `severityHigh`/`severityCritical`) are plain integer fields, so they
   * flow through `normalizeOverview()`'s generic summary-list mapping like
   * every other overview count — no dedicated provider mapping code exists
   * or is needed for them. This test pins that pass-through.
   */
  #[Test]
  public function testProvidePassesThroughNonConformitySeverityBreakdownAsSummaryEntries(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::once())
      ->method('ask')
      ->willReturn($this->createEmptyResult(
        overview: [
          'nonConformities' => [
            'total' => 7,
            'open' => 4,
            'inProgress' => 1,
            'done' => 1,
            'waived' => 1,
            'overdue' => 0,
            'criticalOpen' => 0,
            'severityLow' => 2,
            'severityMedium' => 3,
            'severityHigh' => 1,
            'severityCritical' => 1,
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
    );

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    $summary = [];
    foreach ($output->overview['nonConformities']['summary'] as $entry) {
      $summary[$entry['key']] = $entry['value'];
    }

    self::assertSame(2, $summary['severityLow']);
    self::assertSame(3, $summary['severityMedium']);
    self::assertSame(1, $summary['severityHigh']);
    self::assertSame(1, $summary['severityCritical']);
    // Unaffected by the L3.10 addition: the primary metric still defaults to
    // the status-based `open` key (no `nonConformityStatus` filter here).
    $nonConformitiesPrimary = $output->overview['nonConformities']['primary'];
    self::assertNotNull($nonConformitiesPrimary);
    self::assertSame('open', $nonConformitiesPrimary['key']);
    self::assertSame(4, $nonConformitiesPrimary['value']);
  }

  #[Test]
  public function testProvideThrowsWhenDatetimeFiltersAreInvalid(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $this->createStub(QueryBusPort::class),
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

  #[Test]
  public function testProvideEnrichesRecentInterventionsWithResolvedUserProfile(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::exactly(2))
      ->method('ask')
      ->willReturnCallback(static fn (object $query): object => match (true) {
        $query instanceof GetOrganizationDashboardQuery => new GetOrganizationDashboardResult(
          generatedAt: '2026-03-29T10:00:00+00:00',
          period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'comparison' => 'none', 'timezone' => 'UTC'],
          overview: [],
          health: [],
          alerts: [],
          comparison: ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
          trends: ['facilities' => [], 'members' => [], 'equipment' => [], 'inspections' => []],
          recentInterventions: [
            [
              'id' => 'intervention-1',
              'number' => 42,
              'name' => 'Contrôle annuel extincteurs',
              'status' => 'in_progress',
              'priority' => 'high',
              'siteId' => 'site-1',
              'siteName' => 'Siège — Paris 12e',
              'responsibleId' => 'member-1',
              'responsibleUserId' => 'user-1',
              'dueAt' => '2026-04-01T00:00:00+00:00',
              'updatedAt' => '2026-03-28T09:00:00+00:00',
            ],
          ],
        ),
        $query instanceof GetUserQuery => new GetUserResult(new UserView(
          id: 'user-1',
          username: 'claire.lefevre',
          email: 'claire@example.com',
          firstName: 'Claire',
          lastName: 'Lefèvre',
          avatarUrl: 'https://api.example.com/api/users/user-1/avatar',
          status: 'active',
          emailVerified: true,
          tenantId: null,
          createdAt: $createdAt,
          lastLoginAt: null,
          canLogin: true,
        )),
        default => throw new AccessDeniedHttpException('Unexpected query.'),
      });

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    self::assertCount(1, $output->recentInterventions);
    self::assertSame('intervention-1', $output->recentInterventions[0]['id']);
    self::assertSame(42, $output->recentInterventions[0]['number']);
    self::assertSame('site-1', $output->recentInterventions[0]['siteId']);
    self::assertSame('Siège — Paris 12e', $output->recentInterventions[0]['siteName']);
    self::assertSame('member-1', $output->recentInterventions[0]['responsibleId']);
    self::assertSame('Claire Lefèvre', $output->recentInterventions[0]['responsibleName']);
    self::assertSame('https://api.example.com/api/users/user-1/avatar', $output->recentInterventions[0]['responsibleAvatarUrl']);
    self::assertArrayNotHasKey('responsibleUserId', $output->recentInterventions[0]);
  }

  #[Test]
  public function testProvideFallsBackToMemberIdWhenResponsibleUserProfileIsUnavailable(): void
  {
    $security = $this->createMock(Security::class);
    $security->expects(self::once())->method('getUser')->willReturn($this->createSecurityUser('550e8400-e29b-41d4-a716-446655441600'));

    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->expects(self::exactly(2))
      ->method('ask')
      ->willReturnCallback(static fn (object $query): object => match (true) {
        $query instanceof GetOrganizationDashboardQuery => new GetOrganizationDashboardResult(
          generatedAt: '2026-03-29T10:00:00+00:00',
          period: ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'comparison' => 'none', 'timezone' => 'UTC'],
          overview: [],
          health: [],
          alerts: [],
          comparison: ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
          trends: ['facilities' => [], 'members' => [], 'equipment' => [], 'inspections' => []],
          recentInterventions: [
            [
              'id' => 'intervention-2',
              'number' => 41,
              'name' => 'Inventaire équipements',
              'status' => 'draft',
              'priority' => 'low',
              'siteId' => null,
              'siteName' => null,
              'responsibleId' => 'member-2',
              'responsibleUserId' => 'user-2',
              'dueAt' => null,
              'updatedAt' => '2026-03-27T09:00:00+00:00',
            ],
          ],
        ),
        $query instanceof GetUserQuery => new GetUserResult(null),
        default => throw new AccessDeniedHttpException('Unexpected query.'),
      });

    $provider = new GetOrganizationDashboardProvider(
      queryBus: $queryBus,
      authorization: $this->createDashboardAuthorizationMock(),
      security: $security,
    );

    $output = $provider->provide(new Get(), ['organizationId' => '550e8400-e29b-41d4-a716-446655441610']);

    self::assertInstanceOf(OrganizationDashboardOutput::class, $output);
    self::assertCount(1, $output->recentInterventions);
    self::assertNull($output->recentInterventions[0]['siteId']);
    self::assertNull($output->recentInterventions[0]['siteName']);
    self::assertSame('member-2', $output->recentInterventions[0]['responsibleId']);
    self::assertSame('member-2', $output->recentInterventions[0]['responsibleName']);
    self::assertNull($output->recentInterventions[0]['responsibleAvatarUrl']);
    self::assertNull($output->recentInterventions[0]['dueAt']);
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
   * @param array{facilities: list<array{bucket: string, value: int}>, members: list<array{bucket: string, value: int}>, equipment: list<array{bucket: string, value: int}>, inspections: list<array{bucket: string, value: int}>} $trends
   * @param list<array{id: string, number: int, name: string, status: string, priority: string, siteId: ?string, siteName: ?string, responsibleId: ?string, responsibleUserId: ?string, dueAt: ?string, updatedAt: string}> $recentInterventions
   */
  private function createEmptyResult(
    array $period = ['from' => '2026-03-01T00:00:00+00:00', 'to' => '2026-03-29T23:59:59+00:00', 'comparison' => 'none', 'timezone' => 'UTC'],
    array $overview = [],
    array $health = [],
    array $alerts = [],
    array $comparison = ['mode' => 'none', 'current' => [], 'previous' => [], 'deltas' => [], 'health' => ['current' => [], 'previous' => [], 'deltas' => []]],
    array $trends = ['facilities' => [], 'members' => [], 'equipment' => [], 'inspections' => []],
    array $recentInterventions = [],
  ): GetOrganizationDashboardResult {
    return new GetOrganizationDashboardResult(
      generatedAt: '2026-03-29T10:00:00+00:00',
      period: $period,
      overview: $overview,
      health: $health,
      alerts: $alerts,
      comparison: $comparison,
      trends: $trends,
      recentInterventions: $recentInterventions,
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
