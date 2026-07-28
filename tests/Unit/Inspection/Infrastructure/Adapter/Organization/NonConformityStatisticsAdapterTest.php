<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Organization;

use Inspection\Application\Port\Outbound\NonConformityRepositoryPort;
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Inspection\Infrastructure\Adapter\Organization\NonConformityStatisticsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(NonConformityStatisticsAdapter::class)]
final class NonConformityStatisticsAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCountNonConformityOverviewNormalizesMissingStatusesToZero(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countOverviewByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        '2026-03-01T00:00:00+00:00',
        'critical',
        'open',
      )
      ->willReturn(['total' => 4, 'open' => 4, 'overdue' => 1, 'critical_open' => 2]);

    $adapter = new NonConformityStatisticsAdapter($repository);

    self::assertSame([
      'total' => 4,
      'open' => 4,
      'overdue' => 1,
      'critical_open' => 2,
      'in_progress' => 0,
      'done' => 0,
      'waived' => 0,
    ], $adapter->countNonConformityOverview(self::ORG_ID, '2026-03-01T00:00:00+00:00', 'critical', 'open'));
  }

  #[Test]
  public function testCountNonConformityPeriodMetricsDelegatesToRepository(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countPeriodMetricsByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        '2026-03-01T00:00:00+00:00',
        '2026-03-03T23:59:59+00:00',
        '2026-03-01T00:00:00+00:00',
        'critical',
        'open',
      )
      ->willReturn(['opened' => 1, 'resolved' => 1, 'activeAtStart' => 1]);

    $adapter = new NonConformityStatisticsAdapter($repository);

    self::assertSame(
      ['opened' => 1, 'resolved' => 1, 'activeAtStart' => 1],
      $adapter->countNonConformityPeriodMetrics(self::ORG_ID, '2026-03-01T00:00:00+00:00', '2026-03-03T23:59:59+00:00', '2026-03-01T00:00:00+00:00', 'critical', 'open'),
    );
  }

  #[Test]
  public function testCountNonConformitiesByStatusAndSeverityNormalizeMissingValuesToZero(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByStatusForOrganizationId')
      ->with(self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId))
      ->willReturn([
        'open' => 3,
        'done' => 1,
      ]);
    $repository->expects(self::once())
      ->method('countBySeverityForOrganizationId')
      ->with(self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId))
      ->willReturn([
        'high' => 2,
      ]);

    $adapter = new NonConformityStatisticsAdapter($repository);

    self::assertSame([
      'open' => 3,
      'in_progress' => 0,
      'done' => 1,
      'waived' => 0,
    ], $adapter->countNonConformitiesByStatus(self::ORG_ID));
    self::assertSame([
      'low' => 0,
      'medium' => 0,
      'high' => 2,
      'critical' => 0,
    ], $adapter->countNonConformitiesBySeverity(self::ORG_ID));
  }

  #[Test]
  public function testCountActiveNonConformitiesAtDateDelegatesToRepository(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countActiveByOrganizationIdAtDate')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        '2026-03-01T00:00:00+00:00',
      )
      ->willReturn(4);

    $adapter = new NonConformityStatisticsAdapter($repository);

    self::assertSame(4, $adapter->countActiveNonConformitiesAtDate(self::ORG_ID, '2026-03-01T00:00:00+00:00'));
  }

  #[Test]
  public function testCountNonConformitiesCreatedByDayDelegatesTimezoneToRepository(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByCreatedDayForOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        '2026-03-29T00:00:00+01:00',
        '2026-03-30T23:59:59+02:00',
        'Europe/Paris',
      )
      ->willReturn([
        '2026-03-29' => 1,
        '2026-03-30' => 2,
      ]);

    $adapter = new NonConformityStatisticsAdapter($repository);

    self::assertSame([
      '2026-03-29' => 1,
      '2026-03-30' => 2,
    ], $adapter->countNonConformitiesCreatedByDay(
      self::ORG_ID,
      '2026-03-29T00:00:00+01:00',
      '2026-03-30T23:59:59+02:00',
      'Europe/Paris',
    ));
  }

  #[Test]
  public function testCountNonConformitiesDelegatesSeverityAndStatusFilters(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        'critical',
        'open',
      )
      ->willReturn(7);

    self::assertSame(7, new NonConformityStatisticsAdapter($repository)->countNonConformities(self::ORG_ID, 'critical', 'open'));
  }

  #[Test]
  public function testCountOverdueNonConformitiesDelegatesToRepository(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countOverdueByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        '2026-03-01T00:00:00+00:00',
        'high',
        'in_progress',
      )
      ->willReturn(3);

    self::assertSame(3, new NonConformityStatisticsAdapter($repository)->countOverdueNonConformities(
      self::ORG_ID,
      '2026-03-01T00:00:00+00:00',
      'high',
      'in_progress',
    ));
  }

  #[Test]
  public function testCountNonConformitiesResolvedByDayDelegatesTimezoneToRepository(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByResolvedDayForOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        '2026-03-29T00:00:00+01:00',
        '2026-03-30T23:59:59+02:00',
        'Europe/Paris',
        'medium',
        'done',
      )
      ->willReturn(['2026-03-30' => 5]);

    self::assertSame(['2026-03-30' => 5], new NonConformityStatisticsAdapter($repository)->countNonConformitiesResolvedByDay(
      self::ORG_ID,
      '2026-03-29T00:00:00+01:00',
      '2026-03-30T23:59:59+02:00',
      'Europe/Paris',
      'medium',
      'done',
    ));
  }

  #[Test]
  public function testCountOpenCriticalNonConformitiesDelegatesToRepository(): void
  {
    /** @var NonConformityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(NonConformityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countOpenCriticalByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        'open',
      )
      ->willReturn(2);

    self::assertSame(2, new NonConformityStatisticsAdapter($repository)->countOpenCriticalNonConformities(self::ORG_ID, 'open'));
  }
}
