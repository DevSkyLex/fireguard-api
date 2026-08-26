<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Equipment\GetEquipmentAttachmentContent;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Application\UseCase\Query\Equipment\GetEquipmentAttachmentContent\{GetEquipmentAttachmentContentHandler, GetEquipmentAttachmentContentQuery, GetEquipmentAttachmentContentResult};
use Equipment\Domain\Exception\{AttachmentNotFoundException, EquipmentNotFoundException};
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test GetEquipmentAttachmentContentHandlerTest.
 *
 * The organization-level `organization.equipment.read` permission is
 * enforced by `DownloadEquipmentAttachmentController`, not this handler —
 * see its own docblock. This handler is the AUTHORITATIVE enforcer of the
 * per-record ownership chain: the equipment must belong to the given
 * organization, and the attachment must belong to that equipment.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetEquipmentAttachmentContentHandler::class)]
final class GetEquipmentAttachmentContentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655448001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655448002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655448003';

  // #region Methods
  #[Test]
  public function testInvokeReturnsTheStoredBytes(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );
    $attachment = $this->buildAttachment();

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    $attachmentRepository = $this->createStub(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('read')->willReturn('PDF-BYTES');

    $handler = new GetEquipmentAttachmentContentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $result = $handler->__invoke(new GetEquipmentAttachmentContentQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(GetEquipmentAttachmentContentResult::class, $result);
    self::assertSame('PDF-BYTES', $result->contents);
    self::assertSame('report.pdf', $result->fileName);
    self::assertSame('application/pdf', $result->mimeType);
    self::assertSame(12345, $result->size);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('findById');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $handler = new GetEquipmentAttachmentContentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentAttachmentContentQuery(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentBelongsToAnotherOrganization(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString('550e8400-e29b-41d4-a716-446655448099'),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('findById');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $handler = new GetEquipmentAttachmentContentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentAttachmentContentQuery(
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

    $attachmentRepository = $this->createStub(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $handler = new GetEquipmentAttachmentContentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(AttachmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentAttachmentContentQuery(
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
      equipmentId: EquipmentId::fromString('550e8400-e29b-41d4-a716-446655448098'),
      fileName: 'report.pdf',
      storagePath: 'equipment/other/report.pdf',
      mimeType: 'application/pdf',
      size: 12345,
      label: null,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    $attachmentRepository = $this->createStub(AttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($otherEquipmentAttachment);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('read');

    $handler = new GetEquipmentAttachmentContentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(AttachmentNotFoundException::class);

    $handler->__invoke(new GetEquipmentAttachmentContentQuery(
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
    $fileStorage->expects(self::never())->method('read');

    $handler = new GetEquipmentAttachmentContentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $this->createStub(AttachmentRepositoryPort::class),
      fileStorage: $fileStorage,
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetEquipmentAttachmentContentQuery(
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
