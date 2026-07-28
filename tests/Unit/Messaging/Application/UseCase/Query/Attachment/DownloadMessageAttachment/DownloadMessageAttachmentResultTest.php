<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment;

use Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment\DownloadMessageAttachmentResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DownloadMessageAttachmentResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DownloadMessageAttachmentResult::class)]
final class DownloadMessageAttachmentResultTest extends TestCase
{
  #[Test]
  public function testItCarriesTheBytesAndTheDownloadMetadata(): void
  {
    $result = new DownloadMessageAttachmentResult(
      contents: 'raw-bytes',
      fileName: 'report.pdf',
      mimeType: 'application/pdf',
      size: 9,
    );

    self::assertSame('raw-bytes', $result->contents);
    self::assertSame('report.pdf', $result->fileName);
    self::assertSame('application/pdf', $result->mimeType);
    self::assertSame(9, $result->size);
  }
}
