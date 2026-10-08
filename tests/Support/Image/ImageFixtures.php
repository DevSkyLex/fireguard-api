<?php

declare(strict_types=1);

namespace Tests\Support\Image;

use function base64_decode;
use function hash;
use function pack;
use function strlen;
use function substr;

/**
 * Class ImageFixtures
 *
 * Provides tiny header-only sources for validation failures and a two-frame
 * GIF for the static-image policy without allocating large test rasters.
 *
 * @category Test Support
 */
final readonly class ImageFixtures
{
  // #region Methods
  /**
   * Method pngHeader
   *
   * Creates a valid PNG dimensions header without pixel data. A bounded header
   * passes preflight but must still fail full image decoding.
   *
   * @access public
   *
   * @param int $width the declared source width
   * @param int $height the declared source height
   *
   * @return string the header bytes
   */
  public static function pngHeader(int $width, int $height): string
  {
    $header = 'IHDR' . pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n" . pack('N', strlen($header) - 4) . $header . hash('crc32b', $header, true);
  }

  /**
   * Method animatedGif
   *
   * Creates two one-pixel frames with a shared black-and-white palette.
   *
   * @access public
   *
   * @return string the animated GIF bytes
   */
  public static function animatedGif(): string
  {
    $header = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xFF\xFF\xFF";
    $frame = "\x21\xF9\x04\x00\x01\x00\x00\x00\x2C\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00";

    return $header . $frame . $frame . ';';
  }

  /**
   * Method gifFirstFrameHeader
   *
   * Declares first-frame geometry independently of a one-pixel logical screen.
   * The descriptor needs no pixel data to test pre-decode budget enforcement.
   *
   * @access public
   *
   * @param int $width the declared first-frame width
   * @param int $height the declared first-frame height
   *
   * @return string the GIF screen and first-frame descriptor bytes
   */
  public static function gifFirstFrameHeader(int $width, int $height): string
  {
    return "GIF89a\x01\x00\x01\x00\x00\x00\x00," . pack('vvvvC', 0, 0, $width, $height, 0);
  }

  /**
   * Method webpWithCanvas
   *
   * Wraps a tiny 32-pixel VP8 or VP8L bitstream in a VP8X canvas. A one-pixel
   * canvas disagrees with the bitstream and must be rejected by native decoding.
   *
   * @access public
   *
   * @param bool $lossless whether the fixture uses the VP8L bitstream
   * @param int $canvasSize the declared square VP8X canvas dimension
   *
   * @return string the extended WebP container bytes
   */
  public static function webpWithCanvas(bool $lossless, int $canvasSize): string
  {
    $original = (string) base64_decode(
      $lossless
        ? 'UklGRhoAAABXRUJQVlA4TA4AAAAvH8AHAAcQEf0PRET/Aw=='
        : 'UklGRjIAAABXRUJQVlA4ICYAAADQAgCdASogACAAPlEkj0WjoiEUBAA4BQS0gAAe0dAAAP78+EAAAA==',
      true,
    );
    $dimension = substr(pack('V', $canvasSize - 1), 0, 3);
    $extendedHeader = 'VP8X' . pack('V', 10) . "\0\0\0\0" . $dimension . $dimension;
    $chunks = $extendedHeader . substr($original, 12);

    return 'RIFF' . pack('V', strlen($chunks) + 4) . 'WEBP' . $chunks;
  }

  // #endregion
}
