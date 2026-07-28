<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment;

use DateTimeImmutable;
use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Command\Attachment\DeleteFacilityAttachment\{DeleteFacilityAttachmentCommand, DeleteFacilityAttachmentHandler, DeleteFacilityAttachmentResult};
use Facility\Domain\Exception\{FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;

#[CoversClass(DeleteFacilityAttachmentHandler::class)]
final class DeleteFacilityAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  private const string OTHER_FACILITY_ID = '550e8400-e29b-41d4-a716-446655446004';

  #[Test]
  public function testInvokeDeletesRecordThenStorageObject(): void
  {
    $facility = $this->facility();
    $attachment = $this->attachment();

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::once())->method('delete');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('delete')->with($attachment->storagePath());

    $handler = new DeleteFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $result = $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(DeleteFacilityAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn(null);

    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('delete');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnotherFacility(): void
  {
    $facility = $this->facility();
    $attachment = FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::OTHER_FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::OTHER_FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 10,
      uploadedAt: new DateTimeImmutable(),
    );

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::never())->method('delete');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
    );

    $this->expectException(FacilityAttachmentNotFoundException::class);

    $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiers(): void
  {
    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::never())->method('findById');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $handler = new DeleteFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $this->createStub(FacilityAttachmentRepositoryPort::class),
      fileStorage: $fileStorage,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new DeleteFacilityAttachmentCommand(
      organizationId: 'not-a-uuid',
      facilityId: 'also-not-a-uuid',
      attachmentId: 'still-not-a-uuid',
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

  private function attachment(): FacilityAttachment
  {
    return FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'floor-plan.pdf',
      storagePath: 'facility/' . self::FACILITY_ID . '/attachments/' . self::ATTACHMENT_ID . '_floor-plan.pdf',
      mimeType: 'application/pdf',
      size: 10,
      uploadedAt: new DateTimeImmutable(),
    );
  }
}
