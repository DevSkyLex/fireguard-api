<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility;

use DateTimeImmutable;
use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\UnassignFromFacility\{UnassignFromFacilityCommand, UnassignFromFacilityHandler, UnassignFromFacilityResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentFacilityId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(UnassignFromFacilityHandler::class)]
final class UnassignFromFacilityHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655443001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655443002';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655443003';

  // #region Methods
  #[Test]
  public function testInvokeUnassignsEquipmentFromFacility(): void
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

    $handler = new UnassignFromFacilityHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new UnassignFromFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(UnassignFromFacilityResult::class, $result);
    self::assertNull($result->facilityId);
    self::assertSame('in_stock', $result->status);
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

    $handler = new UnassignFromFacilityHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new UnassignFromFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655443099'),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);
    $equipmentRepository->expects(self::never())->method('save');

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);

    $handler = new UnassignFromFacilityHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new UnassignFromFacilityCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }
  // #endregion
}
