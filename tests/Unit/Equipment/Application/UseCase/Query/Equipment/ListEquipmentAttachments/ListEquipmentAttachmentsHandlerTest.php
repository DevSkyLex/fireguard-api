<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments\{ListEquipmentAttachmentsHandler, ListEquipmentAttachmentsQuery, ListEquipmentAttachmentsResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListEquipmentAttachmentsHandler::class)]
final class ListEquipmentAttachmentsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655449002';

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidEquipmentId(): void
  {
    $handler = new ListEquipmentAttachmentsHandler(
      equipmentRepository: $this->createMock(EquipmentRepositoryPort::class),
      attachmentRepository: $this->createMock(AttachmentRepositoryPort::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ListEquipmentAttachmentsQuery(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
    ));
  }

  #[Test]
  public function testInvokeThrowsEquipmentNotFoundWhenRepositoryReturnsNull(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    $handler = new ListEquipmentAttachmentsHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $this->createMock(AttachmentRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new ListEquipmentAttachmentsQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsEquipmentNotFoundWhenOrganizationMismatch(): void
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

    $handler = new ListEquipmentAttachmentsHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $this->createMock(AttachmentRepositoryPort::class),
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new ListEquipmentAttachmentsQuery(
      organizationId: '550e8400-e29b-41d4-a716-446655449999',
      equipmentId: self::EQUIP_ID,
    ));
  }

  #[Test]
  public function testInvokeReturnsEmptyResultWhenNoAttachments(): void
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

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())
      ->method('findByEquipmentId')
      ->willReturn([]);

    $handler = new ListEquipmentAttachmentsHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
    );

    $result = $handler->__invoke(new ListEquipmentAttachmentsQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
    ));

    self::assertInstanceOf(ListEquipmentAttachmentsResult::class, $result);
    self::assertSame([], $result->attachments);
  }
}
