<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Adapter\Organization;

use Equipment\Application\Port\Outbound\EquipmentRepositoryPort;
use Equipment\Domain\ValueObject\EquipmentOrganizationId;
use Equipment\Infrastructure\Adapter\Organization\EquipmentStatisticsAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(EquipmentStatisticsAdapter::class)]
final class EquipmentStatisticsAdapterTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testCountEquipmentOverviewNormalizesMissingStatusesToZero(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countOverviewByOrganizationId')
      ->with(
        self::callback(static fn (EquipmentOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        'fire_extinguisher',
        'operational',
      )
      ->willReturn(['total' => 5, 'operational' => 5]);

    $adapter = new EquipmentStatisticsAdapter($repository);

    self::assertSame([
      'total' => 5,
      'operational' => 5,
      'in_stock' => 0,
      'under_maintenance' => 0,
      'decommissioned' => 0,
    ], $adapter->countEquipmentOverview(self::ORG_ID, 'fire_extinguisher', 'operational'));
  }

  #[Test]
  public function testCountEquipmentByStatusNormalizesMissingStatusesToZero(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByStatusForOrganizationId')
      ->with(self::callback(static fn (EquipmentOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId))
      ->willReturn([
        'operational' => 5,
        'under_maintenance' => 1,
      ]);

    $adapter = new EquipmentStatisticsAdapter($repository);

    self::assertSame([
      'in_stock' => 0,
      'operational' => 5,
      'under_maintenance' => 1,
      'decommissioned' => 0,
    ], $adapter->countEquipmentByStatus(self::ORG_ID));
  }

  #[Test]
  public function testCountEquipmentByTypeNormalizesMissingTypesToZero(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $repository */
    $repository = $this->createMock(EquipmentRepositoryPort::class);
    $repository->expects(self::once())
      ->method('countByTypeForOrganizationId')
      ->with(self::callback(static fn (EquipmentOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId))
      ->willReturn([
        'fire_extinguisher' => 3,
        'camera' => 2,
      ]);

    $adapter = new EquipmentStatisticsAdapter($repository);
    $counts = $adapter->countEquipmentByType(self::ORG_ID);

    self::assertSame(3, $counts['fire_extinguisher']);
    self::assertSame(2, $counts['camera']);
    self::assertSame(0, $counts['smoke_detector']);
    self::assertSame(0, $counts['other']);
    self::assertCount(12, $counts);
  }
}
