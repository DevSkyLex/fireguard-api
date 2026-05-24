<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\DecommissionEquipment\{DecommissionEquipmentCommand, DecommissionEquipmentHandler, DecommissionEquipmentResult};
use Equipment\Domain\Exception\{EquipmentAlreadyDecommissionedException, EquipmentNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
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
    );

    $this->expectException(EquipmentAlreadyDecommissionedException::class);

    $handler->__invoke(new DecommissionEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }
  // #endregion
}
