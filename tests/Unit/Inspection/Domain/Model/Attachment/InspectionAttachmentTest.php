<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\Attachment;

use DateTimeImmutable;
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, NonConformityId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectionAttachment.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionAttachment::class)]
final class InspectionAttachmentTest extends TestCase
{
  private const string ATTACHMENT_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string INSPECTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a02';

  private const string NON_CONFORMITY_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a03';

  #[Test]
  public function itCreatesAnAttachmentWithGeneratedTimestamp(): void
  {
    $attachment = InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'report.pdf',
      storagePath: 'inspections/org-1/report.pdf',
      mimeType: 'application/pdf',
      size: 2048,
    );

    self::assertSame(self::ATTACHMENT_ID, (string) $attachment->id());
    self::assertSame(self::INSPECTION_ID, (string) $attachment->inspectionId());
    self::assertSame('report.pdf', $attachment->fileName());
    self::assertSame('inspections/org-1/report.pdf', $attachment->storagePath());
    self::assertSame('application/pdf', $attachment->mimeType());
    self::assertSame(2048, $attachment->size());
    self::assertNull($attachment->nonConformityId());
    self::assertNull($attachment->label());
    self::assertInstanceOf(DateTimeImmutable::class, $attachment->uploadedAt());
  }

  #[Test]
  public function itCreatesAFieldProofPhotoLinkedToANonConformity(): void
  {
    $attachment = InspectionAttachment::create(
      id: InspectionAttachmentId::fromString(self::ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'proof.jpg',
      storagePath: 'inspections/org-1/proof.jpg',
      mimeType: 'image/jpeg',
      size: 512,
      nonConformityId: NonConformityId::fromString(self::NON_CONFORMITY_ID),
      label: 'Cracked valve',
    );

    self::assertNotNull($attachment->nonConformityId());
    self::assertSame(self::NON_CONFORMITY_ID, (string) $attachment->nonConformityId());
    self::assertSame('Cracked valve', $attachment->label());
  }

  #[Test]
  public function itReconstitutesFromPersistedState(): void
  {
    $uploadedAt = new DateTimeImmutable('2026-01-01T10:00:00+00:00');

    $attachment = InspectionAttachment::reconstitute(
      id: InspectionAttachmentId::fromString(self::ATTACHMENT_ID),
      inspectionId: InspectionId::fromString(self::INSPECTION_ID),
      fileName: 'report.pdf',
      storagePath: 'inspections/org-1/report.pdf',
      mimeType: 'application/pdf',
      size: 4096,
      uploadedAt: $uploadedAt,
    );

    self::assertSame($uploadedAt, $attachment->uploadedAt());
    self::assertSame(4096, $attachment->size());
    self::assertNull($attachment->nonConformityId());
  }
}
