<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Infrastructure\Adapter\Equipment;

use Equipment\Application\Contract\FloorPlan\{
  FloorPlanAttachmentNotAncestorException,
  FloorPlanAttachmentNotFloorPlanException,
  FloorPlanAttachmentNotFoundException
};
use Facility\Application\Port\Outbound\{FacilityAttachmentRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityAttachmentAncestryGuard;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Facility\Infrastructure\Adapter\Equipment\EquipmentFloorPlanValidationAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentFloorPlanValidationAdapterTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentFloorPlanValidationAdapter::class)]
final class EquipmentFloorPlanValidationAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440941';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440940';

  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440950';

  #[Test]
  public function testAssertThrowsWhenAttachmentIsUnknown(): void
  {
    $facility = $this->facility();

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    /** @var FacilityAttachmentRepositoryPort&MockObject $attachmentRepository */
    $attachmentRepository = $this->createMock(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->expects(self::once())->method('findById')->willReturn(null);

    $adapter = $this->adapter($facilityRepository, $attachmentRepository);

    $this->expectException(FloorPlanAttachmentNotFoundException::class);

    $adapter->assertAttachmentUsableForFacility(self::ATTACHMENT_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testAssertThrowsWhenAttachmentIsNotAFloorPlan(): void
  {
    $facility = $this->facility();
    $attachment = $this->attachment(self::FACILITY_ID, AttachmentKind::DOCUMENT);

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $attachmentRepository = $this->createStub(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);

    $adapter = $this->adapter($facilityRepository, $attachmentRepository);

    $this->expectException(FloorPlanAttachmentNotFloorPlanException::class);

    $adapter->assertAttachmentUsableForFacility(self::ATTACHMENT_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testAssertThrowsWhenAttachmentBelongsToAnUnrelatedFacility(): void
  {
    $facility = $this->facility();
    $unrelatedFacilityId = '550e8400-e29b-41d4-a716-446655449001';
    $attachment = $this->attachment($unrelatedFacilityId, AttachmentKind::FLOOR_PLAN);

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $attachmentRepository = $this->createStub(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);

    $adapter = $this->adapter($facilityRepository, $attachmentRepository);

    $this->expectException(FloorPlanAttachmentNotAncestorException::class);

    $adapter->assertAttachmentUsableForFacility(self::ATTACHMENT_ID, self::FACILITY_ID);
  }

  #[Test]
  public function testAssertSucceedsWhenAttachmentBelongsToTheFacilityItself(): void
  {
    $facility = $this->facility();
    $attachment = $this->attachment(self::FACILITY_ID, AttachmentKind::FLOOR_PLAN);

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $attachmentRepository = $this->createStub(FacilityAttachmentRepositoryPort::class);
    $attachmentRepository->method('findById')->willReturn($attachment);

    $adapter = $this->adapter($facilityRepository, $attachmentRepository);

    $adapter->assertAttachmentUsableForFacility(self::ATTACHMENT_ID, self::FACILITY_ID);

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAssertTreatsAMalformedAttachmentIdAsNotFound(): void
  {
    $facility = $this->facility();

    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('findById')->willReturn($facility);

    $adapter = $this->adapter($facilityRepository, $this->createStub(FacilityAttachmentRepositoryPort::class));

    $this->expectException(FloorPlanAttachmentNotFoundException::class);

    $adapter->assertAttachmentUsableForFacility('not-a-uuid', self::FACILITY_ID);
  }

  private function adapter(
    FacilityRepositoryPort $facilityRepository,
    FacilityAttachmentRepositoryPort $attachmentRepository,
  ): EquipmentFloorPlanValidationAdapter {
    return new EquipmentFloorPlanValidationAdapter(
      facilityRepository: $facilityRepository,
      attachmentRepository: $attachmentRepository,
      ancestryGuard: new FacilityAttachmentAncestryGuard($facilityRepository),
    );
  }

  private function facility(): Facility
  {
    return Facility::create(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      type: FacilityType::SITE,
      name: new FacilityName('Test Facility'),
    );
  }

  private function attachment(string $facilityId, AttachmentKind $kind): FacilityAttachment
  {
    return FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString($facilityId),
      fileName: 'plan.png',
      storagePath: 'facility/' . $facilityId . '/attachments/' . self::ATTACHMENT_ID . '_plan.png',
      mimeType: AttachmentKind::FLOOR_PLAN === $kind ? 'image/png' : 'application/pdf',
      size: 1024,
      kind: $kind,
    );
  }
}
