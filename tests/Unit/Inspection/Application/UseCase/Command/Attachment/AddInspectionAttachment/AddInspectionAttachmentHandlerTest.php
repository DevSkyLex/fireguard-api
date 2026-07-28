<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\{InspectionAttachmentRepositoryPort, InspectionRepositoryPort, NonConformityRepositoryPort};
use Inspection\Application\UseCase\Command\Attachment\AddInspectionAttachment\{AddInspectionAttachmentCommand, AddInspectionAttachmentHandler, AddInspectionAttachmentResult};
use Inspection\Domain\Exception\{InspectionNotFoundException, NonConformityNotFoundException};
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
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\FileStoragePort;

#[CoversClass(AddInspectionAttachmentHandler::class)]
final class AddInspectionAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655446001';

  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655446002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655446003';

  private const string NON_CONFORMITY_ID = '550e8400-e29b-41d4-a716-446655446004';

  #[Test]
  public function testInvokeStoresInspectionLevelAttachmentSuccessfully(): void
  {
    $inspection = $this->inspection();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createMock(NonConformityRepositoryPort::class);
    $nonConformityRepository->expects(self::never())->method('findById');

    /** @var InspectionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InspectionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      fileName: 'report.pdf',
      contents: '%PDF-content',
      mimeType: 'application/pdf',
      size: 12345,
      label: 'Inspection report',
    ));

    self::assertInstanceOf(AddInspectionAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertNull($result->nonConformityId);
  }

  #[Test]
  public function testInvokeStoresNonConformityFieldProofPhoto(): void
  {
    $inspection = $this->inspection();
    $nonConformity = $this->nonConformity();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn($nonConformity);

    /** @var InspectionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InspectionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $result = $handler->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      fileName: 'photo.jpg',
      contents: 'jpg-content',
      mimeType: 'image/jpeg',
      size: 512,
      nonConformityId: self::NON_CONFORMITY_ID,
    ));

    self::assertSame(self::NON_CONFORMITY_ID, $result->nonConformityId);
  }

  #[Test]
  public function testInvokeThrowsWhenInspectionNotFound(): void
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn(null);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);

    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InspectionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(InspectionNotFoundException::class);

    $handler->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNonConformityDoesNotBelongToInspection(): void
  {
    $inspection = $this->inspection();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $otherNonConformity = NonConformity::create(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString('550e8400-e29b-41d4-a716-446655449999'),
      description: 'Some other inspection deficiency',
      severity: NonConformitySeverity::LOW,
    );

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);
    $nonConformityRepository->method('findById')->willReturn($otherNonConformity);

    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InspectionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(NonConformityNotFoundException::class);

    $handler->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      fileName: 'photo.jpg',
      contents: 'content',
      mimeType: 'image/jpeg',
      size: 100,
      nonConformityId: self::NON_CONFORMITY_ID,
    ));
  }

  #[Test]
  public function testInvokeDeletesFileWhenDatabaseSaveFails(): void
  {
    $inspection = $this->inspection();

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($inspection);

    $nonConformityRepository = $this->createStub(NonConformityRepositoryPort::class);

    /** @var InspectionAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(InspectionAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())
      ->method('save')
      ->willThrowException(new RuntimeException('Database error.'));

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())->method('delete');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new InspectionAttachmentId(self::ATTACHMENT_ID));

    $handler = new AddInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $nonConformityRepository,
      attachmentRepository: $attachmentRepository,
      fileStorage: $fileStorage,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Database error.');

    $handler->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnAMalformedInspectionId(): void
  {
    $handler = new AddInspectionAttachmentHandler(
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      attachmentRepository: $this->createStub(InspectionAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: 'not-a-uuid',
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
    ));
  }

  #[Test]
  public function testInvokeHonoursAClientSuppliedAttachmentId(): void
  {
    $clientAttachmentId = '550e8400-e29b-41d4-a716-446655446099';

    $result = $this->authorizedHandler()->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
      attachmentId: $clientAttachmentId,
    ));

    self::assertInstanceOf(AddInspectionAttachmentResult::class, $result);
    self::assertSame($clientAttachmentId, $result->attachmentId);
  }

  #[Test]
  public function testInvokeRefusesAMalformedClientSuppliedAttachmentId(): void
  {
    $this->expectException(InvalidArgumentException::class);

    $this->authorizedHandler()->__invoke(new AddInspectionAttachmentCommand(
      organizationId: self::ORG_ID,
      inspectionId: self::INSPECTION_ID,
      fileName: 'report.pdf',
      contents: 'content',
      mimeType: 'application/pdf',
      size: 100,
      attachmentId: 'not-a-uuid',
    ));
  }

  private function authorizedHandler(): AddInspectionAttachmentHandler
  {
    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('findById')->willReturn($this->inspection());

    return new AddInspectionAttachmentHandler(
      inspectionRepository: $inspectionRepository,
      nonConformityRepository: $this->createStub(NonConformityRepositoryPort::class),
      attachmentRepository: $this->createStub(InspectionAttachmentRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      uuidFactory: $this->createStub(UuidFactory::class),
    );
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

  private function nonConformity(): NonConformity
  {
    return NonConformity::create(
      id: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSPECTION_ID),
      description: 'Fire extinguisher pressure too low',
      severity: NonConformitySeverity::HIGH,
    );
  }
}
