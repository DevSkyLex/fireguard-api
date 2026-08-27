<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\UseCase\Command\Attachment\SetPrimaryFacilityAttachment\{SetPrimaryFacilityAttachmentCommand, SetPrimaryFacilityAttachmentHandler, SetPrimaryFacilityAttachmentResult};
use Facility\Domain\Exception\{FacilityAttachmentNotFloorPlanException, FacilityAttachmentNotFoundException, FacilityNotFoundException};
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\TransactionManagerPort;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test SetPrimaryFacilityAttachmentHandlerTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetPrimaryFacilityAttachmentHandler::class)]
final class SetPrimaryFacilityAttachmentHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655447001';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655447002';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655447003';

  #[Test]
  public function testInvokePromotesAFloorPlanAtomically(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    $attachment = $this->floorPlanAttachment();

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::once())
      ->method('clearPrimaryPlan')
      ->with(
        self::callback(static fn (FacilityId $id): bool => self::FACILITY_ID === (string) $id),
        self::callback(static fn (FacilityAttachmentId $id): bool => self::ATTACHMENT_ID === (string) $id),
      );
    $attachmentRepository->expects(self::once())->method('save')->with($attachment);

    $transactionManager = $this->transactionManager();

    $handler = new SetPrimaryFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new SetPrimaryFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertInstanceOf(SetPrimaryFacilityAttachmentResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertTrue($result->isPrimaryPlan);
    self::assertSame('floor_plan', $result->kind);
    self::assertTrue($attachment->isPrimaryPlan());
  }

  #[Test]
  public function testInvokeRefusesADocumentAttachmentWithoutTouchingThePreviousPrimary(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'report.pdf',
      storagePath: 'facilities/report.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);
    $attachmentRepository->expects(self::never())->method('clearPrimaryPlan');
    $attachmentRepository->expects(self::never())->method('save');

    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $handler = new SetPrimaryFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      transactionManager: $transactionManager,
    );

    $this->expectException(FacilityAttachmentNotFloorPlanException::class);

    $handler->__invoke(new SetPrimaryFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn(null);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('findById');

    $handler = new SetPrimaryFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      transactionManager: $this->createStub(TransactionManagerPort::class),
    );

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new SetPrimaryFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnotherFacility(): void
  {
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($this->facility());

    $otherFacilityAttachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString('550e8400-e29b-41d4-a716-446655447099'),
      fileName: 'plan.png',
      storagePath: 'facilities/plan.png',
      mimeType: 'image/png',
      size: 2048,
      kind: AttachmentKind::FLOOR_PLAN,
    );

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($otherFacilityAttachment);
    $attachmentRepository->expects(self::never())->method('save');

    $handler = new SetPrimaryFacilityAttachmentHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      transactionManager: $this->createStub(TransactionManagerPort::class),
    );

    $this->expectException(FacilityAttachmentNotFoundException::class);

    $handler->__invoke(new SetPrimaryFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeRejectsAnInvalidFacilityId(): void
  {
    $handler = new SetPrimaryFacilityAttachmentHandler(
      facilityRepository: $this->createStub(FacilityRepositoryPort::class),
      attachmentRepository: $this->createStub(FacilityAttachmentRepositoryPort::class),
      transactionManager: $this->createStub(TransactionManagerPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new SetPrimaryFacilityAttachmentCommand(
      organizationId: self::ORG_ID,
      facilityId: 'not-a-uuid',
      attachmentId: self::ATTACHMENT_ID,
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

  private function floorPlanAttachment(): FacilityAttachment
  {
    return FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'plan.png',
      storagePath: 'facilities/plan.png',
      mimeType: 'image/png',
      size: 2048,
      kind: AttachmentKind::FLOOR_PLAN,
    );
  }

  private function transactionManager(): TransactionManagerPort
  {
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    return $transactionManager;
  }
}
