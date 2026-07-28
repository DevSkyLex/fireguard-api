<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\DeleteAttachment;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\DeleteAttachment\{DeleteAttachmentCommand, DeleteAttachmentHandler, DeleteAttachmentResult};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;

#[CoversClass(DeleteAttachmentHandler::class)]
final class DeleteAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655447002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655447003';

  // #region Methods
  #[Test]
  public function testInvokeDeletesAttachment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $attachment = $this->buildAttachment();

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::once())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('delete');

    $handler = new DeleteAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $result = $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(DeleteAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertSame(self::EQUIP_ID, $result->equipmentId);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentNotFound(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->expects(self::never())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(AttachmentNotFoundException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnotherEquipment(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $otherEquipmentAttachment = EquipmentAttachment::create(
      id: AttachmentId::fromString(self::ATTACHMENT_ID),
      equipmentId: EquipmentId::fromString('550e8400-e29b-41d4-a716-446655447099'),
      fileName: 'report.pdf',
      storagePath: 'equipment/other/report.pdf',
      mimeType: 'application/pdf',
      size: 12345,
      label: null,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($otherEquipmentAttachment);
    $attachmentRepository->expects(self::never())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(AttachmentNotFoundException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var EquipmentRepositoryPort&MockObject $equipmentRepository */
    $equipmentRepository = $this->createMock(EquipmentRepositoryPort::class);
    $equipmentRepository->expects(self::never())->method('findById');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $this->createStub(AttachmentRepositoryPort::class),
      fileStorage: $fileStorage,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new DeleteAttachmentCommand(
      organizationId: 'not-a-uuid',
      equipmentId: 'also-not-a-uuid',
      attachmentId: 'still-not-a-uuid',
    ));
  }

  private function buildAttachment(): EquipmentAttachment
  {
    return EquipmentAttachment::create(
      id: AttachmentId::fromString(self::ATTACHMENT_ID),
      equipmentId: EquipmentId::fromString(self::EQUIP_ID),
      fileName: 'report.pdf',
      storagePath: 'equipment/' . self::EQUIP_ID . '/attachments/' . self::ATTACHMENT_ID . '_report.pdf',
      mimeType: 'application/pdf',
      size: 12345,
      label: null,
    );
  }
  // #endregion
}
