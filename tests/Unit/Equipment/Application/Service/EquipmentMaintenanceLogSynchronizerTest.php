<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Service;

use Equipment\Application\Port\Outbound\MaintenanceLogRepositoryPort;
use Equipment\Application\Service\EquipmentMaintenanceLogSynchronizer;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;

#[CoversClass(EquipmentMaintenanceLogSynchronizer::class)]
final class EquipmentMaintenanceLogSynchronizerTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string LOG_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testEnteringMaintenanceOpensAnOngoingLog(): void
  {
    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(MaintenanceLogId::class)
      ->willReturn(new MaintenanceLogId(self::LOG_ID));

    /** @var MaintenanceLogRepositoryPort&MockObject $repository */
    $repository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $repository->expects(self::never())->method('findOpenByEquipmentId');
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (EquipmentMaintenanceLog $log): bool {
        return self::LOG_ID === (string) $log->id()
          && self::EQUIPMENT_ID === (string) $log->equipmentId()
          && $log->isOngoing();
      }));

    $this->synchronizer($repository, $uuidFactory)
      ->syncForStatusTransition(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'operational', 'under_maintenance');
  }

  #[Test]
  public function testLeavingMaintenanceClosesTheOpenLog(): void
  {
    $openLog = EquipmentMaintenanceLog::open(
      new MaintenanceLogId(self::LOG_ID),
      EquipmentId::fromString(self::EQUIPMENT_ID),
      EquipmentOrganizationId::fromString(self::ORGANIZATION_ID),
    );

    /** @var MaintenanceLogRepositoryPort&MockObject $repository */
    $repository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findOpenByEquipmentId')
      ->willReturn($openLog);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (EquipmentMaintenanceLog $log): bool => !$log->isOngoing()));

    $this->synchronizer($repository)
      ->syncForStatusTransition(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'under_maintenance', 'decommissioned');
  }

  #[Test]
  public function testLeavingMaintenanceWithNoOpenLogIsANoOp(): void
  {
    /** @var MaintenanceLogRepositoryPort&MockObject $repository */
    $repository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $repository->expects(self::once())->method('findOpenByEquipmentId')->willReturn(null);
    $repository->expects(self::never())->method('save');

    $this->synchronizer($repository)
      ->syncForStatusTransition(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'under_maintenance', 'operational');
  }

  #[Test]
  public function testUnchangedStatusTouchesNothing(): void
  {
    /** @var MaintenanceLogRepositoryPort&MockObject $repository */
    $repository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('findOpenByEquipmentId');

    $this->synchronizer($repository)
      ->syncForStatusTransition(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'operational', 'operational');
  }

  #[Test]
  public function testTransitionNotTouchingMaintenanceTouchesNothing(): void
  {
    /** @var MaintenanceLogRepositoryPort&MockObject $repository */
    $repository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('findOpenByEquipmentId');

    $this->synchronizer($repository)
      ->syncForStatusTransition(self::EQUIPMENT_ID, self::ORGANIZATION_ID, 'in_stock', 'operational');
  }

  private function synchronizer(
    MaintenanceLogRepositoryPort $repository,
    ?UuidFactory $uuidFactory = null,
  ): EquipmentMaintenanceLogSynchronizer {
    return new EquipmentMaintenanceLogSynchronizer(
      $repository,
      $uuidFactory ?? $this->createStub(UuidFactory::class),
    );
  }
}
