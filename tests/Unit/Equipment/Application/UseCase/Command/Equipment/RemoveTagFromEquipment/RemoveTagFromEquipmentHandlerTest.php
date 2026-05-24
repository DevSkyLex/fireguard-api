<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\RemoveTagFromEquipment\{RemoveTagFromEquipmentCommand, RemoveTagFromEquipmentHandler, RemoveTagFromEquipmentResult};
use Equipment\Domain\Exception\{EquipmentNotFoundException, TagNotFoundException};
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoveTagFromEquipmentHandler::class)]
final class RemoveTagFromEquipmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655445001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655445002';

  private const string TAG_ID = '550e8400-e29b-41d4-a716-446655445003';

  // #region Methods
  #[Test]
  public function testInvokeRemovesTagFromEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->method('isTagLinkedToEquipment')->willReturn(true);
    $tagRepository->expects(self::once())->method('removeTagFromEquipment');

    $handler = new RemoveTagFromEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $result = $handler->__invoke(new RemoveTagFromEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      tagId: self::TAG_ID,
    ));

    self::assertInstanceOf(RemoveTagFromEquipmentResult::class, $result);
    self::assertSame(self::EQUIP_ID, $result->equipmentId);
    self::assertSame(self::TAG_ID, $result->tagId);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::never())->method('removeTagFromEquipment');

    $handler = new RemoveTagFromEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new RemoveTagFromEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      tagId: self::TAG_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTagNotLinkedToEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->method('isTagLinkedToEquipment')->willReturn(false);
    $tagRepository->expects(self::never())->method('removeTagFromEquipment');

    $handler = new RemoveTagFromEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
    );

    $this->expectException(TagNotFoundException::class);

    $handler->__invoke(new RemoveTagFromEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      tagId: self::TAG_ID,
    ));
  }
  // #endregion
}
