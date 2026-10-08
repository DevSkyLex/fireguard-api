<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Image;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Image\InvalidImageInputException;
use Shared\Application\Port\Outbound\FileStoragePort;
use Shared\Infrastructure\Image\ImageInputValidationAdapter;
use Tests\Support\Image\ImageFixtures;
use User\Infrastructure\Image\AvatarResizer;

use function array_map;
use function base64_decode;
use function count;
use function getimagesizefromstring;
use function in_array;
use function sprintf;

/**
 * Test AvatarResizerTest.
 *
 * @category Infrastructure Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AvatarResizer::class)]
final class AvatarResizerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResizeWritesAllVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    $expectedPaths = array_map(
      fn (int $size) => sprintf('avatars/user-1/%d.webp', $size),
      AvatarResizer::SIZES,
    );

    $storage->expects(self::exactly(count(AvatarResizer::SIZES)))
      ->method('write')
      ->with(
        self::callback(fn (string $path) => in_array($path, $expectedPaths, strict: true)),
        self::isString(),
      );

    $resizer = new AvatarResizer($storage, new ImageInputValidationAdapter());

    // Minimal valid 1x1 PNG (smallest valid PNG binary)
    $png = base64_decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
      true,
    );
    self::assertIsString($png, 'Failed to decode base64 PNG fixture.');

    $resizer->resize('user-1', $png);
  }

  #[Test]
  public function testDeleteRemovesExistingVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    $storage->expects(self::exactly(count(AvatarResizer::SIZES)))
      ->method('exists')
      ->willReturn(true);

    $storage->expects(self::exactly(count(AvatarResizer::SIZES)))
      ->method('delete');

    $resizer = new AvatarResizer($storage, new ImageInputValidationAdapter());
    $resizer->delete('user-1');
  }

  #[Test]
  public function testDeleteSkipsMissingVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    $storage->method('exists')->willReturn(false);

    $storage->expects(self::never())->method('delete');

    $resizer = new AvatarResizer($storage, new ImageInputValidationAdapter());
    $resizer->delete('user-1');
  }

  #[Test]
  public function testDeleteSkipsPartiallyMissingVariants(): void
  {
    /** @var FileStoragePort&MockObject $storage */
    $storage = $this->createMock(FileStoragePort::class);

    // Only the first two sizes exist
    $storage->method('exists')
      ->willReturnOnConsecutiveCalls(true, true, false, false);

    $storage->expects(self::exactly(2))->method('delete');

    $resizer = new AvatarResizer($storage, new ImageInputValidationAdapter());
    $resizer->delete('user-1');
  }

  #[Test]
  public function testSizesConstantContainsFourEntries(): void
  {
    self::assertCount(4, AvatarResizer::SIZES);
    self::assertContains(256, AvatarResizer::SIZES);
    self::assertContains(128, AvatarResizer::SIZES);
    self::assertContains(64, AvatarResizer::SIZES);
    self::assertContains(32, AvatarResizer::SIZES);
  }

  #[Test]
  public function testRejectsOversizedGeometryBeforeAnyStorageMutation(): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');

    $this->expectException(InvalidImageInputException::class);
    $this->expectExceptionMessage('Image exceeds the 4194304 pixel limit.');

    new AvatarResizer($storage, new ImageInputValidationAdapter())->resize('user-1', ImageFixtures::pngHeader(2048, 2049));
  }

  #[Test]
  #[DataProvider('undecodableSources')]
  public function testRejectsAnUndecodableImageBeforeAnyStorageMutation(string $contents): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::never())->method('write');
    $storage->expects(self::never())->method('delete');

    $this->expectException(InvalidImageInputException::class);
    $this->expectExceptionMessage('Unable to decode the uploaded image.');

    new AvatarResizer($storage, new ImageInputValidationAdapter())->resize('user-1', $contents);
  }

  #[Test]
  #[DataProvider('acceptedSources')]
  public function testAcceptedSourcesProduceStaticWebpVariantsAtEverySize(string $contents): void
  {
    $storage = $this->createMock(FileStoragePort::class);
    $storage->expects(self::exactly(count(AvatarResizer::SIZES)))
      ->method('write')
      ->willReturnCallback(static function (string $path, string $contents): void {
        $dimensions = getimagesizefromstring($contents);
        self::assertNotFalse($dimensions);
        self::assertSame('image/webp', $dimensions['mime']);
        self::assertSame($dimensions[0], $dimensions[1]);
        self::assertContains($dimensions[0], AvatarResizer::SIZES);
        self::assertSame(sprintf('avatars/user-1/%d.webp', $dimensions[0]), $path);
        self::assertStringNotContainsString('ANIM', $contents);
        self::assertStringNotContainsString('ANMF', $contents);
      });

    new AvatarResizer($storage, new ImageInputValidationAdapter())->resize('user-1', $contents);
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
   * @return iterable<string, array{string}> the valid image sources
   */
  public static function acceptedSources(): iterable
  {
    yield 'animated GIF' => [ImageFixtures::animatedGif()];
    yield 'WebP lossy matching canvas' => [ImageFixtures::webpWithCanvas(false, 32)];
    yield 'WebP lossless matching canvas' => [ImageFixtures::webpWithCanvas(true, 32)];
  }
  // #endregion
}
