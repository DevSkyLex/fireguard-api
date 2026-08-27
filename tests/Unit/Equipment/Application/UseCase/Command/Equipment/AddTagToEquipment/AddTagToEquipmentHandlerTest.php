<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment;

use Equipment\Application\Port\Outbound\{EquipmentRepositoryPort, TagRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment\{AddTagToEquipmentCommand, AddTagToEquipmentHandler, AddTagToEquipmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType, TagId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(AddTagToEquipmentHandler::class)]
final class AddTagToEquipmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655444001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655444002';

  private const string TAG_ID = '550e8400-e29b-41d4-a716-446655444003';

  // #region Methods
  #[Test]
  public function testInvokeCreatesAndLinksNewTag(): void
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
    $tagRepository->method('findByNameAndOrganizationId')->willReturn(null);
    $tagRepository->expects(self::once())->method('saveAndLinkToEquipment');
    $tagRepository->expects(self::never())->method('addTagToEquipment');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new TagId(self::TAG_ID));

    $handler = new AddTagToEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddTagToEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      tagName: 'Urgent',
    ));

    self::assertInstanceOf(AddTagToEquipmentResult::class, $result);
    self::assertSame('urgent', $result->tagName);
  }

  #[Test]
  public function testInvokeLinksExistingTag(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $existingTag = Tag::create(
      id: TagId::fromString(self::TAG_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      name: 'urgent',
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->method('findByNameAndOrganizationId')->willReturn($existingTag);
    $tagRepository->expects(self::never())->method('saveAndLinkToEquipment');
    $tagRepository->expects(self::once())->method('addTagToEquipment');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = new AddTagToEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddTagToEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      tagName: 'urgent',
    ));

    self::assertInstanceOf(AddTagToEquipmentResult::class, $result);
    self::assertSame(self::TAG_ID, $result->tagId);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);

    $tagRepository = $this->createStub(TagRepositoryPort::class);

    $uuidFactory = $this->createStub(UuidFactory::class);

    $handler = new AddTagToEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $tagRepository,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new AddTagToEquipmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      tagName: 'urgent',
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::never())->method('findById');

    $handler = new AddTagToEquipmentHandler(
      equipmentRepository: $equipmentRepository,
      tagRepository: $this->createStub(TagRepositoryPort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new AddTagToEquipmentCommand(
      organizationId: 'not-a-uuid',
      equipmentId: 'also-not-a-uuid',
      tagName: 'urgent',
    ));
  }
  // #endregion
}
