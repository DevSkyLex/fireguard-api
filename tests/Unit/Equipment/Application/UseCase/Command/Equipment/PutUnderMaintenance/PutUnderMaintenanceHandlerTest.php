<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\PutUnderMaintenance\{PutUnderMaintenanceCommand, PutUnderMaintenanceHandler, PutUnderMaintenanceResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(PutUnderMaintenanceHandler::class)]
final class PutUnderMaintenanceHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655442001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655442002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655442003';

  // #region Methods
  #[Test]
  public function testInvokePutsEquipmentUnderMaintenance(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $equipment->assignToFacility(
      EquipmentFacilityId::fromString(self::FACILITY_ID),
      new DateTimeImmutable(),
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn($equipment);
    $equipmentRepository->expects(self::once())->method('save');

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->method('findByEquipmentId')->willReturn([]);

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(PutUnderMaintenanceResult::class, $result);
    self::assertSame('under_maintenance', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentIsDecommissioned(): void
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

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNoFacilityAssigned(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);

    $handler = new PutUnderMaintenanceHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Equipment must be assigned to a facility before putting it under maintenance.');

    $handler->__invoke(new PutUnderMaintenanceCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }
  // #endregion
}
