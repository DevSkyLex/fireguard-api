<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Image;

use Organization\Infrastructure\Image\OrganizationLogoResizer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\FileStoragePort;

use function imagecreatetruecolor;
use function imagepng;
use function ob_get_clean;
use function ob_start;
use function strlen;
use function substr;

/**
 * Test OrganizationLogoResizerTest.
 *
 * Logos are uploaded at arbitrary sizes and served on every page of the
 * app, so the resizer is the only thing keeping a 4000px original from
 * being stored and re-served verbatim. It must cap the dimensions,
 * re-encode to WebP, and write under a path derived from the organization
 * so one tenant can never overwrite another's logo.
 *
 * @category Infrastructure Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationLogoResizer::class)]
final class OrganizationLogoResizerTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655478001';
  // #endregion

  // #region Methods
  #[Test]
  public function testPathForScopesTheLogoToItsOrganization(): void
  {
    self::assertSame(
      'organization-logos/' . self::ORGANIZATION_ID . '/logo.webp',
      OrganizationLogoResizer::pathFor(self::ORGANIZATION_ID),
    );

    self::assertNotSame(
      OrganizationLogoResizer::pathFor(self::ORGANIZATION_ID),
      OrganizationLogoResizer::pathFor('550e8400-e29b-41d4-a716-446655478002'),
    );
  }

  #[Test]
  public function testResizeWritesWebpUnderTheOrganizationPath(): void
  {
    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->with(
        OrganizationLogoResizer::pathFor(self::ORGANIZATION_ID),
        self::callback(static fn (string $contents): bool => 'RIFF' === substr($contents, 0, 4)
          && 'WEBP' === substr($contents, 8, 4)),
      );

    new OrganizationLogoResizer($fileStorage)->resize(self::ORGANIZATION_ID, $this->pngBytes(1024, 768));
  }

  #[Test]
  public function testResizeCapsAnOversizedSourceToTheMaximumDimension(): void
  {
    $written = null;

    $fileStorage = $this->createStub(FileStoragePort::class);
    $fileStorage->method('write')
      ->willReturnCallback(static function (string $path, string $contents) use (&$written): void {
        $written = $contents;
      });

    new OrganizationLogoResizer($fileStorage)->resize(self::ORGANIZATION_ID, $this->pngBytes(2048, 2048));

    self::assertNotNull($written);
    self::assertLessThan(
      strlen($this->pngBytes(2048, 2048)),
      strlen($written),
      'A 2048px source must be scaled down, not stored at full size.',
    );
  }

  #[Test]
  public function testDeleteRemovesAnExistingLogo(): void
  {
    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->method('exists')->willReturn(true);
    $fileStorage->expects(self::once())
      ->method('delete')
      ->with(OrganizationLogoResizer::pathFor(self::ORGANIZATION_ID));

    new OrganizationLogoResizer($fileStorage)->delete(self::ORGANIZATION_ID);
  }

  #[Test]
  public function testDeleteIsANoOpWhenNoLogoIsStored(): void
  {
    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->method('exists')->willReturn(false);
    $fileStorage->expects(self::never())->method('delete');

    new OrganizationLogoResizer($fileStorage)->delete(self::ORGANIZATION_ID);
  }

  /**
   * @param positive-int $width
   * @param positive-int $height
   */
  private function pngBytes(int $width, int $height): string
  {
    $image = imagecreatetruecolor($width, $height);
    self::assertNotFalse($image);

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
  }
  // #endregion
}
