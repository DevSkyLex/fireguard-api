<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments\{ListFacilityAttachmentsHandler, ListFacilityAttachmentsQuery, ListFacilityAttachmentsResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(ListFacilityAttachmentsHandler::class)]
final class ListFacilityAttachmentsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  #[Test]
  public function testInvokeReturnsAttachmentsForTheFacility(): void
  {
    $facility = Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORG_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );

    $attachment = FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 10,
      uploadedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      label: 'Ground floor',
    );

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $attachmentRepository = $this->createStub(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findByFacilityId')->willReturn([$attachment]);

    $handler = new ListFacilityAttachmentsHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
    );

    $result = $handler->__invoke(new ListFacilityAttachmentsQuery(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
    ));

    self::assertInstanceOf(ListFacilityAttachmentsResult::class, $result);
    self::assertCount(1, $result->attachments);
    self::assertSame(self::ATTACHMENT_ID, $result->attachments[0]['id']);
    self::assertSame('Ground floor', $result->attachments[0]['label']);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn(null);

    $attachmentRepository = $this->createStub(FacilityAttachmentRepositoryPort::class);

    $handler = new ListFacilityAttachmentsHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
    );

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new ListFacilityAttachmentsQuery(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
    ));
  }
}
