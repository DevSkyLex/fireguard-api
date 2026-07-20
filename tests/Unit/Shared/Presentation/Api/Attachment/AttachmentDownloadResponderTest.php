<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Attachment;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Attachment\AttachmentDownloadResponder;

use function strlen;

/**
 * Test AttachmentDownloadResponderTest.
 *
 * @category Attachment Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentDownloadResponder::class)]
final class AttachmentDownloadResponderTest extends TestCase
{
  #[Test]
  public function testDownloadForcesAttachmentDispositionAndHardenedHeaders(): void
  {
    $response = new AttachmentDownloadResponder()->download('PDF-BYTES', 'report.pdf', 'application/pdf');

    self::assertSame('PDF-BYTES', $response->getContent());
    self::assertSame('application/pdf', $response->headers->get('Content-Type'));
    self::assertSame((string) strlen('PDF-BYTES'), $response->headers->get('Content-Length'));
    self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    // Symfony normalises Cache-Control directives alphabetically, so assert on
    // the directives themselves rather than their rendered order.
    $cacheControl = (string) $response->headers->get('Cache-Control');
    self::assertStringContainsString('private', $cacheControl);
    self::assertStringContainsString('no-store', $cacheControl);

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment;', $disposition);
    self::assertStringContainsString('report.pdf', $disposition);
  }

  #[Test]
  public function testDownloadFallsBackToOctetStreamWhenMimeTypeIsBlank(): void
  {
    $response = new AttachmentDownloadResponder()->download('bytes', 'data.bin', '   ');

    self::assertSame('application/octet-stream', $response->headers->get('Content-Type'));
  }

  #[Test]
  public function testDownloadEncodesNonAsciiFileNamesWithoutThrowing(): void
  {
    $response = new AttachmentDownloadResponder()->download('bytes', 'rapport-é.pdf', 'application/pdf');

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringStartsWith('attachment;', $disposition);
    // RFC 5987 extended parameter carries the original UTF-8 name.
    self::assertStringContainsString('filename*=', $disposition);
  }

  #[Test]
  public function testDownloadFallsBackToGenericNameWhenFileNameIsBlank(): void
  {
    $response = new AttachmentDownloadResponder()->download('bytes', '   ', 'application/pdf');

    $disposition = (string) $response->headers->get('Content-Disposition');
    self::assertStringContainsString('download', $disposition);
  }
}
