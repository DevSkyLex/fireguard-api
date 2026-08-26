<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Command\Attachment\AddFacilityAttachment\{AddFacilityAttachmentCommand, AddFacilityAttachmentHandler, AddFacilityAttachmentResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Attachment\{AttachmentConstraints, InvalidAttachmentException};
use Shared\Domain\Exception\InvalidValueException;

use function base64_decode;

#[CoversClass(AddFacilityAttachmentHandler::class)]
final class AddFacilityAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  #[Test]
  public function testInvokeStoresAttachmentSuccessfully(): void
  {
    $facility = Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORG_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'floor-plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
      label: 'Ground floor plan',
    ));

    self::assertInstanceOf(AddFacilityAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertSame('floor-plan.pdf', $result->fileName);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn(null);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'floor-plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  #[Test]
  public function testInvokeDoesNotSaveRecordWhenStorageWriteFails(): void
  {
    $facility = Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORG_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->willThrowException(new RuntimeException('Storage unavailable.'));

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Storage unavailable.');

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'floor-plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  #[Test]
  public function testInvokeDeletesFileWhenDatabaseSaveFails(): void
  {
    $facility = Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORG_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())
      ->method('save')
      ->willThrowException(new RuntimeException('Database error.'));

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())->method('delete');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Database error.');

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'floor-plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  #[Test]
  public function testInvokeAppliesBasenameToPreventPathTraversal(): void
  {
    $facility = Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORG_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $attachmentRepository = $this->createStub(FacilityAttachmentRepositoryPort::class);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->with(
        self::matchesRegularExpression('#^facility/.+/attachments/.+_evil\.pdf$#'),
        self::anything(),
      );

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: '../../etc/evil.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
    ));
  }

  #[Test]
  public function testInvokeRejectsAnInvalidFacilityId(): void
  {
    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $this->createStub(FacilityRepositoryPort::class),
      attachmentRepository: $this->createStub(FacilityAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: 'not-a-uuid',
      fileName: 'plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
    ));
  }

  #[Test]
  public function testInvokeUsesTheProvidedAttachmentId(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $this->createStub(FacilityAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(AddFacilityAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeRejectsAnInvalidProvidedAttachmentId(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $this->createStub(FacilityAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'plan.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 7,
      attachmentId: 'not-a-uuid',
    ));
  }

  #[Test]
  public function testInvokeRejectsAnUploadWhenTheFacilityIsAtTheAttachmentCap(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn(null);
    $attachmentRepository->method('countByFacilityId')
      ->willReturn(AttachmentConstraints::MAX_ATTACHMENTS_PER_PARENT);
    $attachmentRepository->expects(self::never())->method('save');

    // The cap must be refused BEFORE any byte reaches storage.
    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidAttachmentException::class);

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'floor-plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));
  }

  #[Test]
  public function testInvokeStoresAFloorPlanWithItsProbedDimensions(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    // A real 1x1 PNG.
    $pngContents = (string) base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
      true,
    );

    $result = $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'ground-floor.png',
      contents: $pngContents,
      mimeType: 'image/png',
      size: 68,
      kind: 'floor_plan',
    ));

    self::assertSame('floor_plan', $result->kind);
    self::assertFalse($result->isPrimaryPlan);
    self::assertSame(1, $result->imageWidth);
    self::assertSame(1, $result->imageHeight);
  }

  #[Test]
  public function testInvokeDefaultsToDocumentWithNoDimensions(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    $attachmentRepository = $this->createStub(FacilityAttachmentRepositoryPort::class);
    $fileStorage = $this->createStub(FileStoragePort::class);
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'report.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
    ));

    self::assertSame('document', $result->kind);
    self::assertNull($result->imageWidth);
    self::assertNull($result->imageHeight);
  }

  #[Test]
  public function testInvokeRejectsAFloorPlanWithADisallowedMimeType(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InvalidAttachmentException::class);

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'plan.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
      kind: 'floor_plan',
    ));
  }

  #[Test]
  public function testInvokeRejectsAnUnknownKind(): void
  {
    $handler = new AddFacilityAttachmentHandler(
      facilityRepository: $this->createStub(FacilityRepositoryPort::class),
      attachmentRepository: $this->createStub(FacilityAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new AddFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      fileName: 'plan.png',
      contents: 'content',
      mimeType: 'image/png',
      size: 7,
      kind: 'not-a-kind',
    ));
  }

  private function facility(): Facility
  {
    return Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORG_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );
  }
}
