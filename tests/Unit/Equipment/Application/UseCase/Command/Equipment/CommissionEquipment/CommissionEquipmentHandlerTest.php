<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\CommissionEquipment;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, MaintenanceLogRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\CommissionEquipment\{CommissionEquipmentCommand, CommissionEquipmentHandler, CommissionEquipmentResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType, MaintenanceLogId};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommissionEquipmentHandler::class)]
final class CommissionEquipmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440003';

  // #region Methods
  #[Test]
  public function testInvokeCommissionsEquipment(): void
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

    $handler = new CommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createMock(MaintenanceLogRepositoryPort::class),
    );

    $result = $handler->__invoke(new CommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(CommissionEquipmentResult::class, $result);
    self::assertSame('operational', $result->status);
    self::assertNotNull($result->commissionedAt);
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

    $handler = new CommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createMock(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new CommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655440099'),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);

    $handler = new CommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createMock(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new CommissionEquipmentCommand(
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

    $handler = new CommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createMock(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $handler->__invoke(new CommissionEquipmentCommand(
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

    $handler = new CommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $this->createMock(MaintenanceLogRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Equipment must be assigned to a facility before commissioning.');

    $handler->__invoke(new CommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeClosesMaintenanceLogWhenCommissioningFromUnderMaintenance(): void
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
    $equipment->putUnderMaintenance();

    $openLog = EquipmentMaintenanceLog::open(
      id: MaintenanceLogId::fromString('550e8400-e29b-41d4-a716-446655510001'),
      equipmentId: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
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

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::once())
      ->method('findOpenByEquipmentId')
      ->willReturn($openLog);
    $maintenanceLogRepository->expects(self::once())->method('save');

    $handler = new CommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
    );

    $result = $handler->__invoke(new CommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertSame('operational', $result->status);
    self::assertNotNull($openLog->completedAt());
  }

  #[Test]
  public function testInvokeDoesNotTouchMaintenanceLogWhenNotUnderMaintenance(): void
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

    /** @var MaintenanceLogRepositoryPort&MockObject $maintenanceLogRepository */
    $maintenanceLogRepository = $this->createMock(MaintenanceLogRepositoryPort::class);
    $maintenanceLogRepository->expects(self::never())->method('findOpenByEquipmentId');
    $maintenanceLogRepository->expects(self::never())->method('save');

    $handler = new CommissionEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      maintenanceLogRepository: $maintenanceLogRepository,
    );

    $result = $handler->__invoke(new CommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertSame('operational', $result->status);
  }
  // #endregion
}
