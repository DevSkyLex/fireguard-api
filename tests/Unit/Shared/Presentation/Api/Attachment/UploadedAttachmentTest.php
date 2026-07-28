<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Attachment;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Attachment\UploadedAttachment;

/**
 * Test UploadedAttachmentTest.
 *
 * Every module's media processor reads this DTO rather than the raw request,
 * so the sniffed MIME type, the measured size and the optional label must
 * survive construction exactly as the guard produced them.
 *
 * @category DTO Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UploadedAttachment::class)]
final class UploadedAttachmentTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorExposesTheValidatedUploadAsIs(): void
  {
    $attachment = new UploadedAttachment(
      fileName: 'report.pdf',
      contents: '%PDF-1.7',
      mimeType: 'application/pdf',
      size: 8,
      label: 'Inspection report',
    );

    self::assertSame('report.pdf', $attachment->fileName);
    self::assertSame('%PDF-1.7', $attachment->contents);
    self::assertSame('application/pdf', $attachment->mimeType);
    self::assertSame(8, $attachment->size);
    self::assertSame('Inspection report', $attachment->label);
  }

  #[Test]
  public function testLabelDefaultsToNullWhenTheUploadCarriesNone(): void
  {
    $attachment = new UploadedAttachment(
      fileName: 'photo.jpg',
      contents: 'binary',
      mimeType: 'image/jpeg',
      size: 6,
    );

    self::assertNull($attachment->label);
  }
  // #endregion
}
