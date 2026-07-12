<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\{DecommissionEquipmentCommand, DecommissionEquipmentHandler, DecommissionEquipmentResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType, MaintenanceLogId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecommissionEquipmentHandler::class)]
final class DecommissionEquipmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655441001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655441002';

  // #region Methods
  #[Test]
  public function testInvokeDecommissionsEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $result = $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(DecommissionEquipmentResult::class, $result);
    self::assertSame('decommissioned', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655441099'),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAlreadyDecommissioned(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->decommission();

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createStub(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeClosesOpenMaintenanceLogWhenDecommissioningUnderMaintenanceEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(
      EquipmentFacilityId::fromString('550e8400-e29b-41d4-a716-446655441003'),
      new DateTimeImmutable(),
    );
    $equipment->commission();
    $equipment->putUnderMaintenance();

    $openLog = EquipmentMaintenanceLog::open(
      MaintenanceLogId::fromString('550e8400-e29b-41d4-a716-446655441004'),
      EquipmentId::fromString(self::EQUIP_ID),
      EquipmentOrganizationId::fromString(self::ORG_ID),
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::once())
      ->method('findOpenByEquipmentId')
      ->willReturn($openLog);
    $maintenanceLogRepository->expects(self::once())
      ->method('save')
      ->with(self::identicalTo($openLog));

    $handler = new DecommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
    );

    $result = $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertSame('decommissioned', $result->status);
  }
  // #endregion
}
