<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\Model\Attachment;

use DateTimeImmutable;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

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
}
