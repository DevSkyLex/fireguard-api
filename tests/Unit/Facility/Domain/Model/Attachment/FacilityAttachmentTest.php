<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\Attachment;

use DateTimeImmutable;
use Facility\Domain\Exception\FacilityAttachmentNotFloorPlanException;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Attachment\InvalidAttachmentException;

/**
 * Test FacilityAttachment.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityAttachment::class)]
final class FacilityAttachmentTest extends TestCase
{
  private const string ATTACHMENT_ID = '550e8400-e29b-41d4-a716-446655440100';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440200';

  #[Test]
  public function testCreateExposesAccessors(): void
  {
    $id = FacilityAttachmentId::fromString(self::ATTACHMENT_ID);
    $facilityId = FacilityId::fromString(self::FACILITY_ID);

    $before = new DateTimeImmutable();
    $attachment = FacilityAttachment::create(
      id: $id,
      facilityId: $facilityId,
      fileName: 'plan.pdf',
      storagePath: 'facilities/plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      label: 'Floor plan',
    );
    $after = new DateTimeImmutable();

    self::assertSame($id, $attachment->id());
    self::assertSame($facilityId, $attachment->facilityId());
    self::assertSame('plan.pdf', $attachment->fileName());
    self::assertSame('facilities/plan.pdf', $attachment->storagePath());
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(2048, $attachment->size());
    self::assertSame('Floor plan', $attachment->label());
    self::assertGreaterThanOrEqual($before->getTimestamp(), $attachment->uploadedAt()->getTimestamp());
    self::assertLessThanOrEqual($after->getTimestamp(), $attachment->uploadedAt()->getTimestamp());
  }

  #[Test]
  public function testCreateDefaultsLabelToNull(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'photo.jpg',
      storagePath: 'facilities/photo.jpg',
      mimeType: 'image/jpeg',
      size: 512,
    );

    self::assertNull($attachment->label());
  }

  #[Test]
  public function testReconstitutePreservesUploadedAt(): void
  {
    $uploadedAt = new DateTimeImmutable('2025-01-15T10:30:00+00:00');

    $attachment = FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'report.pdf',
      storagePath: 'facilities/report.pdf',
      mimeType: 'application/pdf',
      size: 4096,
      uploadedAt: $uploadedAt,
      label: 'Report',
    );

    self::assertSame($uploadedAt, $attachment->uploadedAt());
    self::assertSame('report.pdf', $attachment->fileName());
    self::assertSame('Report', $attachment->label());
    self::assertSame(4096, $attachment->size());
  }

  #[Test]
  public function testCreateDefaultsKindToDocumentAndIsNeverPrimary(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'report.pdf',
      storagePath: 'facilities/report.pdf',
      mimeType: 'application/pdf',
      size: 4096,
    );

    self::assertSame(AttachmentKind::DOCUMENT, $attachment->kind());
    self::assertFalse($attachment->isPrimaryPlan());
    self::assertNull($attachment->imageWidth());
    self::assertNull($attachment->imageHeight());
  }

  #[Test]
  public function testCreateAcceptsAFloorPlanWithAnAllowedImageMimeType(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'ground-floor.svg',
      storagePath: 'facilities/ground-floor.svg',
      mimeType: 'image/svg+xml',
      size: 1024,
      kind: AttachmentKind::FLOOR_PLAN,
      imageWidth: 800,
      imageHeight: 600,
    );

    self::assertSame(AttachmentKind::FLOOR_PLAN, $attachment->kind());
    self::assertSame(800, $attachment->imageWidth());
    self::assertSame(600, $attachment->imageHeight());
    self::assertFalse($attachment->isPrimaryPlan());
  }

  #[Test]
  public function testCreateRejectsAFloorPlanWithANonImageMimeType(): void
  {
    $this->expectException(InvalidAttachmentException::class);

    FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'plan.pdf',
      storagePath: 'facilities/plan.pdf',
      mimeType: 'application/pdf',
      size: 2048,
      kind: AttachmentKind::FLOOR_PLAN,
    );
  }

  #[Test]
  public function testReconstituteRejectsADocumentMarkedPrimary(): void
  {
    $this->expectException(FacilityAttachmentNotFloorPlanException::class);

    FacilityAttachment::reconstitute(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'report.pdf',
      storagePath: 'facilities/report.pdf',
      mimeType: 'application/pdf',
      size: 4096,
      uploadedAt: new DateTimeImmutable(),
      kind: AttachmentKind::DOCUMENT,
      isPrimaryPlan: true,
    );
  }

  #[Test]
  public function testMarkAsPrimaryPromotesAFloorPlan(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'plan.png',
      storagePath: 'facilities/plan.png',
      mimeType: 'image/png',
      size: 2048,
      kind: AttachmentKind::FLOOR_PLAN,
    );

    self::assertFalse($attachment->isPrimaryPlan());

    $attachment->markAsPrimary();

    self::assertTrue($attachment->isPrimaryPlan());
  }

  #[Test]
  public function testMarkAsPrimaryRefusesADocument(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'report.pdf',
      storagePath: 'facilities/report.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );

    $this->expectException(FacilityAttachmentNotFloorPlanException::class);

    $attachment->markAsPrimary();
  }

  #[Test]
  public function testClearPrimaryDemotesAPromotedFloorPlan(): void
  {
    $attachment = FacilityAttachment::create(
      id: FacilityAttachmentId::fromString(self::ATTACHMENT_ID),
      facilityId: FacilityId::fromString(self::FACILITY_ID),
      fileName: 'plan.png',
      storagePath: 'facilities/plan.png',
      mimeType: 'image/png',
      size: 2048,
      kind: AttachmentKind::FLOOR_PLAN,
    );
    $attachment->markAsPrimary();

    $attachment->clearPrimary();

    self::assertFalse($attachment->isPrimaryPlan());
  }
}
