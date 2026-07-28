<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Command\Equipment\AddAttachment;

use Equipment\Application\Port\Outbound\{AttachmentRepositoryPort, EquipmentRepositoryPort};
use Equipment\Application\UseCase\Command\Equipment\AddAttachment\{AddAttachmentCommand, AddAttachmentHandler, AddAttachmentResult};
use Equipment\Domain\Exception\EquipmentNotFoundException;
use Equipment\Domain\Model\Equipment\Equipment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, EquipmentOrganizationId, EquipmentType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\FileStoragePort;

#[CoversClass(AddAttachmentHandler::class)]
final class AddAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  // #region Methods
  #[Test]
  public function testInvokeStoresAttachmentSuccessfully(): void
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
    $attachmentRepository->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new AttachmentId(self::ATTACHMENT_ID));

    $handler = new AddAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      fileName: 'report.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
      label: 'Inspection report',
    ));

    self::assertInstanceOf(AddAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertSame('report.pdf', $result->fileName);
  }

  #[Test]
  public function testInvokeThrowsWhenEquipmentNotFound(): void
  {
    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn(null);

    /** @var AttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(AttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new AttachmentId(self::ATTACHMENT_ID));

    $handler = new AddAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(EquipmentNotFoundException::class);

    $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      fileName: 'report.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  #[Test]
  public function testInvokeDoesNotSaveRecordWhenStorageWriteFails(): void
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
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->willThrowException(new RuntimeException('Storage unavailable.'));

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new AttachmentId(self::ATTACHMENT_ID));

    $handler = new AddAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Storage unavailable.');

    $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      fileName: 'report.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  #[Test]
  public function testInvokeDeletesFileWhenDatabaseSaveFails(): void
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
    $attachmentRepository->expects(self::once())
      ->method('save')
      ->willThrowException(new RuntimeException('Database error.'));

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())->method('delete');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new AttachmentId(self::ATTACHMENT_ID));

    $handler = new AddAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Database error.');

    $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      fileName: 'report.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  #[Test]
  public function testInvokeAppliesBasenameToPreventPathTraversal(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    $attachmentRepository = $this->createStub(AttachmentRepositoryPort::class);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->with(
        self::matchesRegularExpression('#^equipment/.+/attachments/.+_evil\.pdf$#'),
        self::anything(),
      );

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new AttachmentId(self::ATTACHMENT_ID));

    $handler = new AddAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      fileName: '../../etc/evil.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
    ));
  }

  #[Test]
  public function testInvokeRejectsAnInvalidEquipmentId(): void
  {
    $handler = new AddAttachmentHandler(
      equipmentRepository: $this->createStub(EquipmentRepositoryPort::class),
      attachmentRepository: $this->createStub(AttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: 'not-a-uuid',
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
    ));
  }

  #[Test]
  public function testInvokeUsesTheProvidedAttachmentId(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = new AddAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $this->createStub(AttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeRejectsAnInvalidProvidedAttachmentId(): void
  {
    $equipment = Equipment::create(
      id: EquipmentId::fromString(self::EQUIP_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      type: EquipmentType::FIRE_EXTINGUISHER,
    );

    $equipmentRepository = $this->createStub(EquipmentRepositoryPort::class);
    $equipmentRepository->method('findById')->willReturn($equipment);

    $handler = new AddAttachmentHandler(
      equipmentRepository: $equipmentRepository,
      attachmentRepository: $this->createStub(AttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new AddAttachmentCommand(
      organizationId: self::ORG_ID,
      equipmentId: self::EQUIP_ID,
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
      attachmentId: 'not-a-uuid',
    ));
  }
  // #endregion
}
