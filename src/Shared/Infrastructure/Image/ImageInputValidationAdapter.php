<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Image;

use Shared\Application\Contract\Image\InvalidImageInputException;
use Shared\Application\Port\Outbound\ImageInputValidationPort;

use function getimagesizefromstring;
use function in_array;
use function ord;
use function strlen;
use function substr;

use const IMAGETYPE_GIF;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;
use const IMAGETYPE_WEBP;

/**
 * Class ImageInputValidationAdapter
 *
 * Uses PHP's header inspection to reject image amplification before GD reads
 * a source. The compressed byte limit also bounds header-parser work.
 *
 * @category Infrastructure Adapter
 */
final readonly class ImageInputValidationAdapter implements ImageInputValidationPort
{
  // #region Constants
  /**
   * Constant INVALID_GIF_CONTENT.
   *
   * Shared failure text for malformed GIF blocks.
   *
   * @since 1.1.0
   *
   * @var string INVALID_GIF_CONTENT
   */
  private const string INVALID_GIF_CONTENT = 'Invalid GIF image content.';
  // #endregion

  // #region Methods
  /**
   * Method validate
   *
   * Checks source type and geometry without constructing the raster.
   *
   * @access public
   *
   * @param string $contents the raw compressed image bytes
   *
   * @return void no return value
   *
   * @throws InvalidImageInputException if the source is malformed or exceeds the budget
   */
  public function validate(string $contents): void
  {
    if ('' === $contents || strlen($contents) > self::MAX_BYTES) {
      throw new InvalidImageInputException('Image size must be between 1 byte and 5 MiB.');
    }

    $metadata = @getimagesizefromstring($contents);
    if (false === $metadata || !in_array($metadata[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
      throw new InvalidImageInputException('Invalid image content. Allowed types: JPEG, PNG, WebP and GIF.');
    }

    [$width, $height] = $metadata;
    $this->validateDimensions($width, $height);

    if (IMAGETYPE_GIF === $metadata[2]) {
      $this->validateGifFirstFrame($contents, $width, $height);
    }
  }

  /**
   * Method validateDimensions
   *
   * Applies the source budget to each geometry a decoder may allocate.
   *
   * @access private
   *
   * @param int $width the declared raster width
   * @param int $height the declared raster height
   *
   * @return void no return value
   *
   * @throws InvalidImageInputException if the dimensions exceed the budget
   */
  private function validateDimensions(int $width, int $height): void
  {
    if ($width <= 0 || $height <= 0 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
      throw new InvalidImageInputException('Image width and height must be between 1 and 4096 pixels.');
    }

    if ($width * $height > self::MAX_PIXELS) {
      throw new InvalidImageInputException('Image exceeds the 4194304 pixel limit.');
    }
  }

  /**
   * Method validateGifFirstFrame
   *
   * GIF's logical screen and first image descriptor carry separate dimensions.
   * Inspect both before the static GD decoder can allocate the first frame.
   *
   * @access private
   *
   * @param string $contents the bounded binary GIF source
   * @param int $canvasWidth the already-validated logical screen width
   * @param int $canvasHeight the already-validated logical screen height
   *
   * @return void no return value
   *
   * @throws InvalidImageInputException if the first frame is malformed or outside the budget
   */
  private function validateGifFirstFrame(string $contents, int $canvasWidth, int $canvasHeight): void
  {
    $length = strlen($contents);
    if ($length < 13) {
      throw new InvalidImageInputException(self::INVALID_GIF_CONTENT);
    }

    $packed = ord($contents[10]);
    $offset = 13 + (0 !== ($packed & 0x80) ? 3 * (2 << ($packed & 0x07)) : 0);

    while ($offset < $length) {
      $marker = $contents[$offset];

      if ('!' === $marker && $offset + 2 <= $length) {
        $offset = $this->skipGifSubBlocks($contents, $offset + 2);

        continue;
      }

      if (',' !== $marker || $offset + 10 > $length) {
        throw new InvalidImageInputException(self::INVALID_GIF_CONTENT);
      }

      $descriptor = substr($contents, $offset + 1, 9);
      $left = ord($descriptor[0]) | (ord($descriptor[1]) << 8);
      $top = ord($descriptor[2]) | (ord($descriptor[3]) << 8);
      $width = ord($descriptor[4]) | (ord($descriptor[5]) << 8);
      $height = ord($descriptor[6]) | (ord($descriptor[7]) << 8);
      $this->validateDimensions($width, $height);

      if ($left + $width > $canvasWidth || $top + $height > $canvasHeight) {
        throw new InvalidImageInputException('GIF first frame is outside its logical screen.');
      }

      return;
    }

    throw new InvalidImageInputException(self::INVALID_GIF_CONTENT);
  }

  /**
   * Method skipGifSubBlocks
   *
   * Advances over a GIF extension's length-prefixed blocks without decoding it.
   *
   * @access private
   *
   * @param string $contents the bounded binary GIF source
   * @param int $offset the first sub-block offset
   *
   * @return int the offset after the terminating zero-length block
   *
   * @throws InvalidImageInputException if an extension block is truncated
   */
  private function skipGifSubBlocks(string $contents, int $offset): int
  {
    $length = strlen($contents);

    while ($offset < $length) {
      $blockLength = ord($contents[$offset++]);
      if (0 === $blockLength) {
        return $offset;
      }

      $offset += $blockLength;
    }

    throw new InvalidImageInputException(self::INVALID_GIF_CONTENT);
  }
  // #endregion
}
