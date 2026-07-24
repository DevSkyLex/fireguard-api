<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Attachment;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Attachment\{MultipartAttachmentGuard, UploadedAttachment};
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnprocessableEntityHttpException};

use function base64_decode;
use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

#[CoversClass(MultipartAttachmentGuard::class)]
final class MultipartAttachmentGuardTest extends TestCase
{
  /**
   * A minimal valid 1x1 transparent GIF. MIME sniffing (fileinfo) reads the
   * actual bytes, not the client-declared content type, so the fixture must
   * be a real image for the "accepted" path to exercise real detection.
   */
  private const string MINIMAL_GIF_BASE64 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBTAA7';

  #[Test]
  public function testFromRequestReturnsAValidatedUploadedAttachment(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'attachment-');
    self::assertIsString($path);
    file_put_contents($path, (string) base64_decode(self::MINIMAL_GIF_BASE64, true));

    try {
      $request = Request::create('/api/facilities/facility-id/attachments', 'POST', ['label' => 'Front view'], [], [
        'file' => new UploadedFile($path, 'photo.gif', 'image/gif', null, true),
      ]);

      $result = new MultipartAttachmentGuard()->fromRequest($request);

      self::assertInstanceOf(UploadedAttachment::class, $result);
      self::assertSame('photo.gif', $result->fileName);
      self::assertSame('image/gif', $result->mimeType);
      self::assertSame('Front view', $result->label);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testFromRequestThrowsBadRequestWhenFileIsMissing(): void
  {
    $request = Request::create('/api/facilities/facility-id/attachments', 'POST');

    $this->expectException(BadRequestHttpException::class);

    new MultipartAttachmentGuard()->fromRequest($request);
  }

  #[Test]
  public function testFromRequestRejectsDisallowedMimeType(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'attachment-');
    self::assertIsString($path);
    file_put_contents($path, 'binary-bytes');

    try {
      $request = Request::create('/api/facilities/facility-id/attachments', 'POST', [], [], [
        'file' => new UploadedFile($path, 'evil.exe', 'application/x-msdownload', null, true),
      ]);

      $this->expectException(UnprocessableEntityHttpException::class);

      new MultipartAttachmentGuard()->fromRequest($request);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function testFromRequestRejectsOversizeAttachment(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'attachment-');
    self::assertIsString($path);
    file_put_contents($path, 'small-bytes');

    try {
      $file = new class ($path, 'huge.pdf', 'application/pdf', null, true) extends UploadedFile {
        public function getSize(): int
        {
          return 999_999_999;
        }
      };

      $request = Request::create('/api/facilities/facility-id/attachments', 'POST', [], [], ['file' => $file]);

      $this->expectException(UnprocessableEntityHttpException::class);

      new MultipartAttachmentGuard()->fromRequest($request);
    } finally {
      unlink($path);
    }
  }
}
