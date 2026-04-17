<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Organization;

use Inspection\Application\Port\Outbound\InspectionRepositoryPort;
use Inspection\Domain\ValueObject\InspectionOrganizationId;
use Inspection\Infrastructure\Adapter\Organization\InspectionStatisticsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(InspectionStatisticsAdapter::class)]
final class InspectionStatisticsAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCountInspectionOverviewNormalizesMissingValuesToZero(): void
  {
    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countOverviewByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        'closed',
        'pass',
        'user',
      )
      ->willReturn(['total' => 6, 'closed' => 6, 'pass' => 6]);

    $adapter = new InspectionStatisticsAdapter($repository);

    self::assertSame([
      'total' => 6,
      'closed' => 6,
      'pass' => 6,
      'draft' => 0,
      'submitted' => 0,
      'fail' => 0,
      'partial' => 0,
    ], $adapter->countInspectionOverview(self::ORG_ID, 'closed', 'pass', 'user'));
  }

  #[Test]
  public function testCountInspectionPeriodMetricsDelegatesToRepository(): void
  {
    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countPeriodMetricsByOrganizationId')
      ->with(
        self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        '2026-03-01T00:00:00+00:00',
        '2026-03-03T23:59:59+00:00',
        'closed',
        'pass',
        'user',
      )
      ->willReturn(['total' => 2, 'closed' => 2, 'pass' => 2, 'fail' => 0, 'partial' => 0]);

    $adapter = new InspectionStatisticsAdapter($repository);

    self::assertSame(
      ['total' => 2, 'closed' => 2, 'pass' => 2, 'fail' => 0, 'partial' => 0],
      $adapter->countInspectionPeriodMetrics(self::ORG_ID, '2026-03-01T00:00:00+00:00', '2026-03-03T23:59:59+00:00', 'closed', 'pass', 'user'),
    );
  }

  #[Test]
  public function testCountInspectionsByStatusNormalizesMissingStatusesToZero(): void
  {
    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByStatusForOrganizationId')
      ->with(self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId))
      ->willReturn([
        'submitted' => 4,
      ]);

    $adapter = new InspectionStatisticsAdapter($repository);

    self::assertSame([
      'draft' => 0,
      'submitted' => 4,
      'closed' => 0,
    ], $adapter->countInspectionsByStatus(self::ORG_ID));
  }

  #[Test]
  public function testCountInspectionsByResultAndInspectorTypeNormalizeMissingValuesToZero(): void
  {
    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByResultForOrganizationId')
      ->with(self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId))
      ->willReturn([
        'pass' => 7,
        'fail' => 2,
      ]);
    $repository->expects(self::once())
      ->method('countByInspectorTypeForOrganizationId')
      ->with(self::callback(static fn (InspectionOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId))
      ->willReturn([
        'user' => 8,
      ]);

    $adapter = new InspectionStatisticsAdapter($repository);

    self::assertSame([
      'pass' => 7,
      'fail' => 2,
      'partial' => 0,
    ], $adapter->countInspectionsByResult(self::ORG_ID));
    self::assertSame([
      'user' => 8,
      'external' => 0,
    ], $adapter->countInspectionsByInspectorType(self::ORG_ID));
  }

  #[Test]
  public function testCountInspectionsPerformedByDayDelegatesTimezoneToRepository(): void
  {
    /** @var InspectionRepositoryPort&MockObject $repository */
    $repository = $this->createMock(InspectionRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByPerformedDayForOrganizationId')
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

    $adapter = new InspectionStatisticsAdapter($repository);

    self::assertSame([
      '2026-03-29' => 1,
      '2026-03-30' => 2,
    ], $adapter->countInspectionsPerformedByDay(
      self::ORG_ID,
      '2026-03-29T00:00:00+01:00',
      '2026-03-30T23:59:59+02:00',
      'Europe/Paris',
    ));
  }
}
