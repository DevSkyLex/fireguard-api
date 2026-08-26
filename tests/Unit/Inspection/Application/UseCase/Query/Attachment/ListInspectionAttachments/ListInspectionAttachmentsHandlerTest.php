<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments\{ListInspectionAttachmentsHandler, ListInspectionAttachmentsQuery, ListInspectionAttachmentsResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{
  InspectionAttachmentId,
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  Inspector,
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(ListInspectionAttachmentsHandler::class)]
final class ListInspectionAttachmentsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655446004';

  #[Test]
  public function testInvokeReturnsInspectionLevelAttachmentsWhenNoNonConformitySpecified(): void
  {
    $inspection = $this->inspection();
    $attachment = InspectionAttachment::reconstitute(
      id: InspectionAttachmentId::fromString(self::ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'report.pdf',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::ATTACHMENT_ID . '_report.pdf',
      mimeType: 'application/pdf',
      size: 10,
      uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::never())->method('findById');

    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findByInspectionId')->willReturn([$attachment]);
    $attachmentRepository->expects(self::never())->method('findByNonConformityId');

    $handler = new ListInspectionAttachmentsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
    );

    $result = $handler->__invoke(new ListInspectionAttachmentsQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
    ));

    self::assertInstanceOf(ListInspectionAttachmentsResult::class, $result);
    self::assertCount(1, $result->attachments);
    self::assertNull($result->attachments[0]['nonConformityId']);
  }

  #[Test]
  public function testInvokeReturnsNonConformityAttachmentsWhenSpecified(): void
  {
    $inspection = $this->inspection();
    $nonConformity = NonConformity::create(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Broken seal',
      severity: NonConformitySeverity::MEDIUM,
    );
    $attachment = InspectionAttachment::reconstitute(
      id: InspectionAttachmentId::fromString(self::ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'photo.jpg',
      storagePath: 'inspection/' . self::INSPECTION_ID . '/attachments/' . self::ATTACHMENT_ID . '_photo.jpg',
      mimeType: 'image/jpeg',
      size: 10,
      uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      nonConformityId: NonConformityId::fromString(self::NON_CONFORMITY_ID),
    );

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn($nonConformity);

    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('findByInspectionId');
    $attachmentRepository->expects(self::once())->method('findByNonConformityId')->willReturn([$attachment]);

    $handler = new ListInspectionAttachmentsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
    );

    $result = $handler->__invoke(new ListInspectionAttachmentsQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      nonConformityId: self::NON_CONFORMITY_ID,
    ));

    self::assertCount(1, $result->attachments);
    self::assertSame(self::NON_CONFORMITY_ID, $result->attachments[0]['nonConformityId']);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn(null);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $attachmentRepository = $this->createStub(InspectionAttachmentRepositoryPort::class);

    $handler = new ListInspectionAttachmentsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new ListInspectionAttachmentsQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNonConformityNotFound(): void
  {
    $inspection = $this->inspection();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn(null);

    $attachmentRepository = $this->createStub(InspectionAttachmentRepositoryPort::class);

    $handler = new ListInspectionAttachmentsHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
    );

    $this->expectException(NonConformityNotFoundException::class);

    $handler->__invoke(new ListInspectionAttachmentsQuery(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      nonConformityId: self::NON_CONFORMITY_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsAMalformedIdentifier(): void
  {
    $handler = new ListInspectionAttachmentsHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      attachmentRepository: $this->createStub(InspectionAttachmentRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListInspectionAttachmentsQuery(
      organizationId: self::ORG_ID,
      inspectionId: 'not-a-uuid',
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
}
