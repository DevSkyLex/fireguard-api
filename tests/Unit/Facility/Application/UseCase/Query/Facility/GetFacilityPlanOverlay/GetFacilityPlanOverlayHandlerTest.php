<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay;

use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityAttachmentAncestryGuard;
use Facility\Application\UseCase\Query\Facility\GetFacilityPlanOverlay\{
  GetFacilityPlanOverlayHandler,
  GetFacilityPlanOverlayQuery,
  GetFacilityPlanOverlayResult
};
use Facility\Domain\Exception\{
  FacilityAttachmentNotAncestorException,
  FacilityAttachmentNotFloorPlanException,
  FacilityAttachmentNotFoundException,
  FacilityNotFoundException
};
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test GetFacilityPlanOverlayHandlerTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetFacilityPlanOverlayHandler::class)]
final class GetFacilityPlanOverlayHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440941';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440940';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440950';

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::once())->method('findById')->willReturn(null);

    $handler = $this->handler($facilityRepository, $this->createStub(FacilityAttachmentRepositoryPort::class));

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new GetFacilityPlanOverlayQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNoPrimaryPlanAndNoExplicitAttachmentId(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::atLeastOnce())->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findPrimaryFloorPlan')->willReturn(null);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(FacilityAttachmentNotFoundException::class);

    $handler->__invoke(new GetFacilityPlanOverlayQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: null,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenExplicitAttachmentNotFound(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::atLeastOnce())->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findById')->willReturn(null);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(FacilityAttachmentNotFoundException::class);

    $handler->__invoke(new GetFacilityPlanOverlayQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenExplicitAttachmentIsNotAFloorPlan(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::DOCUMENT);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::atLeastOnce())->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(FacilityAttachmentNotFloorPlanException::class);

    $handler->__invoke(new GetFacilityPlanOverlayQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenExplicitAttachmentBelongsToAnUnrelatedFacility(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $unrelatedFacilityId = '550e8400-e29b-41d4-a716-446655449001';
    $attachment = $this->attachment(self::ATTACHMENT_ID, $unrelatedFacilityId, AttachmentKind::FLOOR_PLAN);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::atLeastOnce())->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $this->expectException(FacilityAttachmentNotAncestorException::class);

    $handler->__invoke(new GetFacilityPlanOverlayQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));
  }

  #[Test]
  public function testInvokeReturnsZonesForTheDefaultPrimaryPlan(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::FLOOR_PLAN, 800, 600);

    $zones = [
      ['facilityId' => self::FACILITY_ID, 'name' => 'Test Zone', 'type' => 'zone', 'status' => 'active', 'points' => [[0.1, 0.1], [0.4, 0.1], [0.4, 0.4]]],
    ];

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::atLeastOnce())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())
      ->method('findZonesForPlanAttachment')
      ->willReturn($zones);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findPrimaryFloorPlan')->willReturn($attachment);
    $attachmentRepository->expects(self::never())->method('findById');

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $result = $handler->__invoke(new GetFacilityPlanOverlayQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: null,
    ));

    self::assertInstanceOf(GetFacilityPlanOverlayResult::class, $result);
    self::assertSame(self::ATTACHMENT_ID, $result->attachmentId);
    self::assertSame(800, $result->imageWidth);
    self::assertSame(600, $result->imageHeight);
    self::assertSame($zones, $result->zones);
  }

  #[Test]
  public function testInvokeReturnsEmptyZonesWhenNothingMatches(): void
  {
    $facility = $this->facility(self::FACILITY_ID, self::ORGANIZATION_ID);
    $attachment = $this->attachment(self::ATTACHMENT_ID, self::FACILITY_ID, AttachmentKind::FLOOR_PLAN);

    /** @var FacilityRepositoryPort&MockObject $facilityRepository */
    $facilityRepository = $this->createMock(FacilityRepositoryPort::class);
    $facilityRepository->expects(self::atLeastOnce())->method('findById')->willReturn($facility);
    $facilityRepository->expects(self::once())
      ->method('findZonesForPlanAttachment')
      ->willReturn([]);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findById')->willReturn($attachment);

    $handler = $this->handler($facilityRepository, $attachmentRepository);

    $result = $handler->__invoke(new GetFacilityPlanOverlayQuery(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::FACILITY_ID,
      attachmentId: self::ATTACHMENT_ID,
    ));

    self::assertSame([], $result->zones);
  }

  /**
   * Method handler.
   *
   * @since 1.0.0
   */
  private function handler(
    FacilityRepositoryPort $facilityRepository,
    FacilityAttachmentRepositoryPort $attachmentRepository,
  ): GetFacilityPlanOverlayHandler {
    return new GetFacilityPlanOverlayHandler(
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
  private function facility(string $id, string $organizationId): Facility
  {
    return Facility::create(
      id: FacilityId::fromString($id),
      organizationId: FacilityOrganizationId::fromString($organizationId),
      type: FacilityType::SITE,
      name: new FacilityName('Test Facility'),
    );
  }

  /**
   * Method attachment.
   *
   * @since 1.0.0
   */
  private function attachment(
    string $id,
    string $facilityId,
    AttachmentKind $kind,
    ?int $imageWidth = null,
    ?int $imageHeight = null,
  ): FacilityAttachment {
    return FacilityAttachment::create(
      id: FacilityAttachmentId::fromString($id),
      facilityId: FacilityId::fromString($facilityId),
      fileName: 'plan.png',
      storagePath: 'facility/' . $facilityId . '/attachments/' . $id . '_plan.png',
      mimeType: AttachmentKind::FLOOR_PLAN === $kind ? 'image/png' : 'application/pdf',
      size: 1024,
      kind: $kind,
      imageWidth: $imageWidth,
      imageHeight: $imageHeight,
    );
  }
}
