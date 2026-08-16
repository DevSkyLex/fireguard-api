<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\ImageDimensions;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function base64_decode;

/**
 * Test ImageDimensionsTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImageDimensions::class)]
final class ImageDimensionsTest extends TestCase
{
  /**
   * A real 1x1 transparent PNG.
   */
  private const string MINIMAL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

  #[Test]
  public function testFromContentsReadsRasterDimensions(): void
  {
    $contents = (string) base64_decode(self::MINIMAL_PNG_BASE64, true);

    $dimensions = ImageDimensions::fromContents($contents, 'image/png');

    self::assertNotNull($dimensions);
    self::assertSame(1, $dimensions->width());
    self::assertSame(1, $dimensions->height());
  }

  #[Test]
  public function testFromContentsReturnsNullForUnparsableRasterBytes(): void
  {
    self::assertNull(ImageDimensions::fromContents('not-an-image', 'image/png'));
  }

  #[Test]
  public function testFromContentsReadsSvgWidthAndHeightAttributes(): void
  {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150"><rect/></svg>';

    $dimensions = ImageDimensions::fromContents($svg, 'image/svg+xml');

    self::assertNotNull($dimensions);
    self::assertSame(200, $dimensions->width());
    self::assertSame(150, $dimensions->height());
  }

  #[Test]
  public function testFromContentsReadsSvgPixelSuffixedAttributes(): void
  {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64px" height="32px"></svg>';

    $dimensions = ImageDimensions::fromContents($svg, 'image/svg+xml');

    self::assertNotNull($dimensions);
    self::assertSame(64, $dimensions->width());
    self::assertSame(32, $dimensions->height());
  }

  #[Test]
  public function testFromContentsFallsBackToTheSvgViewBoxWhenNoWidthHeightAttributes(): void
  {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"></svg>';

    $dimensions = ImageDimensions::fromContents($svg, 'image/svg+xml');

    self::assertNotNull($dimensions);
    self::assertSame(400, $dimensions->width());
    self::assertSame(300, $dimensions->height());
  }

  #[Test]
  public function testFromContentsReturnsNullForAnSvgWithNeitherAttributesNorViewBox(): void
  {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';

    self::assertNull(ImageDimensions::fromContents($svg, 'image/svg+xml'));
  }

  #[Test]
  public function testFromContentsReturnsNullForAnSvgWithPercentageDimensions(): void
  {
    // Documented behavior: percentage/CSS-unit dimensions are not guessed at.
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"></svg>';

    self::assertNull(ImageDimensions::fromContents($svg, 'image/svg+xml'));
  }

  #[Test]
  public function testFromContentsReturnsNullForBytesThatAreNotAnSvgTagAtAll(): void
  {
    self::assertNull(ImageDimensions::fromContents('not xml at all', 'image/svg+xml'));
  }
}
