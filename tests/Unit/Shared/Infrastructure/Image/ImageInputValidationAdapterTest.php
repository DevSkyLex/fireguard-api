<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Image;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Image\InvalidImageInputException;
use Shared\Application\Port\Outbound\ImageInputValidationPort;
use Shared\Infrastructure\Image\ImageInputValidationAdapter;
use Tests\Support\Image\ImageFixtures;

use function str_repeat;
use function strlen;

/**
 * Class ImageInputValidationAdapterTest
 *
 * Proves source budgets with small headers, without decoding oversized rasters.
 *
 * @category Infrastructure Tests
 */
#[CoversClass(ImageInputValidationAdapter::class)]
final class ImageInputValidationAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  #[DataProvider('invalidSources')]
  public function testRejectsInvalidOrOversizedSources(string $contents, string $message): void
  {
    $this->expectException(InvalidImageInputException::class);
    $this->expectExceptionMessage($message);

    new ImageInputValidationAdapter()->validate($contents);
  }

  /**
   * Method invalidSources
   *
   * Exercises byte, positive-dimension, individual-axis and combined-area guards.
   *
   * @access public
   *
   * @return iterable<string, array{string, string}> the rejected source cases
   */
  public static function invalidSources(): iterable
  {
    yield 'empty' => ['', 'Image size must be between'];
    yield 'too many bytes' => [str_repeat('x', ImageInputValidationPort::MAX_BYTES + 1), 'Image size must be between'];
    yield 'malformed' => ['not an image', 'Invalid image content.'];
    yield 'zero width' => [ImageFixtures::pngHeader(0, 1), 'Image width and height'];
    yield 'zero height' => [ImageFixtures::pngHeader(1, 0), 'Image width and height'];
    yield 'wide' => [ImageFixtures::pngHeader(ImageInputValidationPort::MAX_DIMENSION + 1, 1), 'Image width and height'];
    yield 'tall' => [ImageFixtures::pngHeader(1, ImageInputValidationPort::MAX_DIMENSION + 1), 'Image width and height'];
    yield 'too many pixels' => [ImageFixtures::pngHeader(2048, 2049), 'Image exceeds the 4194304 pixel limit.'];
    yield 'GIF first-frame pixels exceed screen budget' => [ImageFixtures::gifFirstFrameHeader(2048, 2049), 'Image exceeds the 4194304 pixel limit.'];
    yield 'GIF first-frame axis exceeds screen budget' => [ImageFixtures::gifFirstFrameHeader(4097, 1), 'Image width and height'];
    yield 'GIF first frame exceeds screen' => [ImageFixtures::gifFirstFrameHeader(32, 32), 'GIF first frame is outside its logical screen.'];
    yield 'GIF zero frame width' => [ImageFixtures::gifFirstFrameHeader(0, 1), 'Image width and height'];
    yield 'GIF missing first descriptor' => ["GIF89a\x01\x00\x01\x00\x00\x00\x00;", 'Invalid GIF image content.'];
    yield 'GIF truncated extension block' => ["GIF89a\x01\x00\x01\x00\x00\x00\x00\x21\xFE\xFFx", 'Invalid GIF image content.'];
  }

  #[Test]
  public function testAcceptsTheExactPixelBoundaryWithoutDecoding(): void
  {
    new ImageInputValidationAdapter()->validate(ImageFixtures::pngHeader(2048, 2048));

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testAcceptsTheExactAxisAndByteBoundaries(): void
  {
    $contents = ImageFixtures::pngHeader(ImageInputValidationPort::MAX_DIMENSION, 1);
    $contents .= str_repeat("\0", ImageInputValidationPort::MAX_BYTES - strlen($contents));

    new ImageInputValidationAdapter()->validate($contents);

    $this->addToAssertionCount(1);
  }
  // #endregion
}
