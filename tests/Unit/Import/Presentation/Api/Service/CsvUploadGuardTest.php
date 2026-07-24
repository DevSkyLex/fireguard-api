<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Presentation\Api\Service;

use Import\Presentation\Api\Service\{CsvUploadGuard, UploadedCsv};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnprocessableEntityHttpException};

use function file_put_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

/**
 * Test CsvUploadGuardTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CsvUploadGuard::class)]
final class CsvUploadGuardTest extends TestCase
{
  #[Test]
  public function itReturnsAValidatedUploadedCsv(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'import-');
    self::assertIsString($path);
    file_put_contents($path, "type,name\nsite,Main site\n");

    try {
      $request = Request::create('/api/imports', 'POST', ['kind' => 'facility', 'organization' => 'org-1'], [], [
        'file' => new UploadedFile($path, 'facilities.csv', 'text/csv', null, true),
      ]);

      $result = new CsvUploadGuard()->fromRequest($request);

      self::assertInstanceOf(UploadedCsv::class, $result);
      self::assertSame('facility', $result->kind);
      self::assertSame('facilities.csv', $result->fileName);
      self::assertSame('text/csv', $result->mimeType);
      self::assertStringContainsString('Main site', $result->contents);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function itThrowsBadRequestWhenTheKindFieldIsMissing(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'import-');
    self::assertIsString($path);
    file_put_contents($path, "type\nsite\n");

    try {
      $request = Request::create('/api/imports', 'POST', [], [], [
        'file' => new UploadedFile($path, 'facilities.csv', 'text/csv', null, true),
      ]);

      $this->expectException(BadRequestHttpException::class);

      new CsvUploadGuard()->fromRequest($request);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function itThrowsBadRequestWhenTheFileFieldIsMissing(): void
  {
    $request = Request::create('/api/imports', 'POST', ['kind' => 'equipment']);

    $this->expectException(BadRequestHttpException::class);

    new CsvUploadGuard()->fromRequest($request);
  }

  #[Test]
  public function itRejectsAFileWithoutTheCsvExtension(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'import-');
    self::assertIsString($path);
    file_put_contents($path, "type\nsite\n");

    try {
      $request = Request::create('/api/imports', 'POST', ['kind' => 'facility'], [], [
        'file' => new UploadedFile($path, 'facilities.txt', 'text/plain', null, true),
      ]);

      $this->expectException(UnprocessableEntityHttpException::class);

      new CsvUploadGuard()->fromRequest($request);
    } finally {
      unlink($path);
    }
  }

  #[Test]
  public function itRejectsAnOversizeFile(): void
  {
    $path = tempnam(sys_get_temp_dir(), 'import-');
    self::assertIsString($path);
    file_put_contents($path, "type\nsite\n");

    try {
      $file = new class ($path, 'facilities.csv', 'text/csv', null, true) extends UploadedFile {
        public function getSize(): int
        {
          return 999_999_999;
        }
      };

      $request = Request::create('/api/imports', 'POST', ['kind' => 'facility'], [], ['file' => $file]);

      $this->expectException(UnprocessableEntityHttpException::class);

      new CsvUploadGuard()->fromRequest($request);
    } finally {
      unlink($path);
    }
  }
}
