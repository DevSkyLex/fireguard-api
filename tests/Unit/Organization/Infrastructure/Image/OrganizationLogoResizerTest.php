<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Image;

use Organization\Infrastructure\Image\OrganizationLogoResizer;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Image\InvalidImageInputException;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Image\ImageInputValidationAdapter;
use Tests\Support\Image\ImageFixtures;

use function getimagesizefromstring;
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

    new OrganizationLogoResizer($fileStorage, new ImageInputValidationAdapter())->resize(self::ORGANIZATION_ID, $this->pngBytes(1024, 768));
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

    new OrganizationLogoResizer($fileStorage, new ImageInputValidationAdapter())->resize(self::ORGANIZATION_ID, $this->pngBytes(2048, 2048));

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

    new OrganizationLogoResizer($fileStorage, new ImageInputValidationAdapter())->delete(self::ORGANIZATION_ID);
  }

  #[Test]
  public function testDeleteIsANoOpWhenNoLogoIsStored(): void
  {
    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->method('exists')->willReturn(false);
    $fileStorage->expects(self::never())->method('delete');

    new OrganizationLogoResizer($fileStorage, new ImageInputValidationAdapter())->delete(self::ORGANIZATION_ID);
  }

  #[Test]
  public function testRejectsOversizedGeometryBeforeAnyStorageMutation(): void
  {
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');
    $fileStorage->expects(self::never())->method('delete');

    $this->expectException(InvalidImageInputException::class);
    $this->expectExceptionMessage('Image exceeds the 4194304 pixel limit.');

    new OrganizationLogoResizer($fileStorage, new ImageInputValidationAdapter())->resize(self::ORGANIZATION_ID, ImageFixtures::pngHeader(2048, 2049));
  }

  #[Test]
  #[DataProvider('undecodableSources')]
  public function testRejectsAnUndecodableImageBeforeAnyStorageMutation(string $contents): void
  {
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');
    $fileStorage->expects(self::never())->method('delete');

    $this->expectException(InvalidImageInputException::class);
    $this->expectExceptionMessage('Unable to decode the uploaded image.');

    new OrganizationLogoResizer($fileStorage, new ImageInputValidationAdapter())->resize(self::ORGANIZATION_ID, $contents);
  }

  #[Test]
  #[DataProvider('acceptedSources')]
  public function testAcceptedSourcesProduceAStaticWebpLogo(string $contents, int $expectedDimension): void
  {
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->willReturnCallback(static function (string $path, string $contents) use ($expectedDimension): void {
        $dimensions = getimagesizefromstring($contents);
        self::assertNotFalse($dimensions);
        self::assertSame([$expectedDimension, $expectedDimension], [$dimensions[0], $dimensions[1]]);
        self::assertSame('image/webp', $dimensions['mime']);
        self::assertStringNotContainsString('ANIM', $contents);
        self::assertStringNotContainsString('ANMF', $contents);
      });

    new OrganizationLogoResizer($fileStorage, new ImageInputValidationAdapter())->resize(self::ORGANIZATION_ID, $contents);
  }

  /**
   * Method undecodableSources
   *
   * Exercises sources with bounded headers that native decoding must reject.
   *
   * @access public
   *
   * @return iterable<string, array{string}> the malformed image sources
   */
  public static function undecodableSources(): iterable
  {
    yield 'truncated PNG pixels' => [ImageFixtures::pngHeader(1, 1)];
    yield 'WebP lossy canvas mismatch' => [ImageFixtures::webpWithCanvas(false, 1)];
    yield 'WebP lossless canvas mismatch' => [ImageFixtures::webpWithCanvas(true, 1)];
  }

  /**
   * Method acceptedSources
   *
   * Exercises first-frame GIF conversion and consistent extended WebP sources.
   *
   * @access public
   *
   * @return iterable<string, array{string, int}> the valid source and output dimension
   */
  public static function acceptedSources(): iterable
  {
    yield 'animated GIF' => [ImageFixtures::animatedGif(), 1];
    yield 'WebP lossy matching canvas' => [ImageFixtures::webpWithCanvas(false, 32), 32];
    yield 'WebP lossless matching canvas' => [ImageFixtures::webpWithCanvas(true, 32), 32];
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
