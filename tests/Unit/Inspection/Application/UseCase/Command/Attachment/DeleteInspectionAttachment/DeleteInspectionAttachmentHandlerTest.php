<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort};
use Inspection\Application\UseCase\Command\Attachment\DeleteInspectionAttachment\{DeleteInspectionAttachmentCommand, DeleteInspectionAttachmentHandler, DeleteInspectionAttachmentResult};
use Inspection\Domain\Exception\{InspectionAttachmentNotFoundException, InspectionNotFoundException};
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionAttachmentId,
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(DeleteInspectionAttachmentHandler::class)]
final class DeleteInspectionAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  private const string OTHER_INSPECTION_ID = '550e8400-e29b-41d4-a716-446655446005';

  #[Test]
  public function testInvokeDeletesRecordThenStorageObject(): void
  {
    $inspection = $this->inspection();
    $attachment = $this->attachment(self::INSPECTION_ID);

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    /** @var InspectionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::once())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('delete')->with($attachment->storagePath());

    $handler = new DeleteInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $result = $handler->__invoke(new DeleteInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(DeleteInspectionAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn(null);

    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new DeleteInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnotherInspection(): void
  {
    $inspection = $this->inspection();
    $attachment = $this->attachment(self::OTHER_INSPECTION_ID);

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::never())->method('delete');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(InspectionAttachmentNotFoundException::class);

    $handler->__invoke(new DeleteInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsAMalformedIdentifier(): void
  {
    $handler = new DeleteInspectionAttachmentHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      attachmentRepository: $this->createStub(InspectionAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new DeleteInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: 'not-a-uuid',
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  private function inspection(): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSPECTION_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString('550e8400-e29b-41d4-a716-446655448888'),
      inspector: Inspector::forUser('550e8400-e29b-41d4-a716-446655447777', 'Jane Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function attachment(string $inspectionId): InspectionAttachment
  {
    return InspectionAttachment::reconstitute(
      id: InspectionAttachmentId::fromString(self::ATTACHMENT_ID),
      inspectionId: InspectionId::fromString($inspectionId),
      fileName: 'report.pdf',
      storagePath: 'inspection/' . $inspectionId . '/attachments/' . self::ATTACHMENT_ID . '_report.pdf',
      mimeType: 'application/pdf',
      size: 10,
      uploadedAt: new DateTimeImmutable(),
    );
  }
}
