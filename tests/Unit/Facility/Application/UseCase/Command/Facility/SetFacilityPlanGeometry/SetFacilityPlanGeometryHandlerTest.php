<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityAttachmentAncestryGuard;
use Facility\Application\UseCase\Command\Facility\SetFacilityPlanGeometry\{
  SetFacilityPlanGeometryCommand,
  SetFacilityPlanGeometryHandler,
  SetFacilityPlanGeometryResult
};
use Facility\Domain\Exception\{
  FacilityAttachmentNotAncestorException,
  FacilityAttachmentNotFloorPlanException,
  FacilityAttachmentNotFoundException,
  FacilityNotFoundException
};
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType, PlanGeometry};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test SetFacilityPlanGeometryHandlerTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SetFacilityPlanGeometryHandler::class)]
final class SetFacilityPlanGeometryHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440941';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440940';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440950';

  private const array VALID_POINTS = [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]];

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn(null);
    $facilityRepository->expects(self::never())->method('save');

    $handler = $this->handler($facilityRepository, $this->createStub(FacilityAttachmentRepositoryPort::class));

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganization(): void
  {
    $facility = $this->facility(self::FACILITY_ID, '550e8400-e29b-41d4-a716-446655449999');

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('save');

    $handler = $this->handler($facilityRepository, $this->createStub(FacilityAttachmentRepositoryPort::class));

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentIdProvidedWithoutPoints(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('save');

    $handler = $this->handler($facilityRepository, $this->createStub(FacilityAttachmentRepositoryPort::class));

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: null,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenPointsProvidedWithoutAttachmentId(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('save');

    $handler = $this->handler($facilityRepository, $this->createStub(FacilityAttachmentRepositoryPort::class));

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: null,
      points: self::VALID_POINTS,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentNotFound(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('save');

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findById')->willReturn(null);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(FacilityAttachmentNotFoundException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentIsNotAFloorPlan(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::DOCUMENT);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('save');

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::atLeastOnce())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(FacilityAttachmentNotFloorPlanException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenAttachmentBelongsToAnUnrelatedFacility(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $unrelatedFacilityId = '550e8400-e29b-41d4-a716-446655449001';
    $attachment = $this->attachment(self::ATTACHMENT_ID, $unrelatedFacilityId, AttachmentKind::FLOOR_PLAN);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('save');

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::atLeastOnce())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(FacilityAttachmentNotAncestorException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenPointsAreMalformed(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::FLOOR_PLAN);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::never())->method('save');

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::atLeastOnce())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: [[0.1, 0.1], [0.4, 0.1]],
    ));
  }

  #[Test]
  public function testInvokeAssignsGeometryOnTheHappyPath(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::FLOOR_PLAN);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())->method('save')->with($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::atLeastOnce())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $result = $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));

    self::assertInstanceOf(SetFacilityPlanGeometryResult::class, $result);
    self::assertSame(self::FACILITY_ID, $result->facilityId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame(['attachmentId' => self::ATTACHMENT_ID, 'points' => self::VALID_POINTS], $result->planGeometry);
    self::assertNotNull($facility->planGeometry());
  }

  #[Test]
  public function testInvokeAssignsGeometryToAnAncestorAttachment(): void
  {
    $ancestorId = '550e8400-e29b-41d4-a716-446655449100';
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID, $ancestorId);
    $ancestor = $this->facility($ancestorId, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, $ancestorId, AttachmentKind::FLOOR_PLAN);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturnCallback(
      static fn (FacilityId $id): ?Facility => match ((string) $id) {
        self::FACILITY_ID => $facility,
        $ancestorId => $ancestor,
        default => null,
      },
    );
    $facilityRepository->expects(self::once())->method('save')->with($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::atLeastOnce())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $result = $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));

    self::assertSame(['attachmentId' => self::ATTACHMENT_ID, 'points' => self::VALID_POINTS], $result->planGeometry);
  }

  #[Test]
  public function testInvokeAllowsWritingAnArchivedFacility(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $facility->archive();
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::FLOOR_PLAN);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())->method('save')->with($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::atLeastOnce())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $result = $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
      points: self::VALID_POINTS,
    ));

    self::assertNotNull($result->planGeometry);
  }

  #[Test]
  public function testInvokeClearsGeometryWhenBothFieldsAreNull(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::FLOOR_PLAN);
    $facility->assignPlanGeometry(new PlanGeometry(self::ATTACHMENT_ID, self::VALID_POINTS));

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())->method('save')->with($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::never())->method('findById');

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $result = $handler->__invoke(new SetFacilityPlanGeometryCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: null,
      points: null,
    ));

    self::assertNull($result->planGeometry);
    self::assertNull($facility->planGeometry());
  }

  /**
   * Method handler.
   *
   * @since 1.0.0
   */
  private function handler(
    FacilityRepositoryPort $facilityRepository,
    FacilityAttachmentRepositoryPort $attachmentRepository,
  ): SetFacilityPlanGeometryHandler {
    return new SetFacilityPlanGeometryHandler(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      ancestryGuard: new FacilityAttachmentAncestryGuard($facilityRepository),
    );
  }

  /**
   * Method facility.
   *
   * @since 1.0.0
   */
  private function facility(string $id, string $organizationId, ?string $parentFacilityId = null): Facility
  {
    return Facility::create(
      id: FacilityId::fromString($id),
      organizationId: FacilityOrganizationId::fromString($organizationId),
      type: FacilityType::ZONE,
      name: new FacilityName('Test Zone'),
      parentFacilityId: null !== $parentFacilityId ? FacilityId::fromString($parentFacilityId) : null,
    );
  }

  /**
   * Method attachment.
   *
   * @since 1.0.0
   */
  private function attachment(string $id, string $facilityId, AttachmentKind $kind): FacilityAttachment
  {
    return FacilityAttachment::create(
      id: FacilityAttachmentId::fromString($id),
      facilityId: FacilityId::fromString($facilityId),
      fileName: 'plan.png',
      storagePath: 'facility/' . $facilityId . '/attachments/' . $id . '_plan.png',
      mimeType: AttachmentKind::FLOOR_PLAN === $kind ? 'image/png' : 'application/pdf',
      size: 1024,
      kind: $kind,
    );
  }
}
