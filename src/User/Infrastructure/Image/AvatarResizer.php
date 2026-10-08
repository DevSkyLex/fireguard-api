<?php

declare(strict_types=1);

namespace User\Infrastructure\Image;

use Intervention\Image\Drivers\Gd\Decoders\BinaryImageDecoder;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use Shared\Application\Contract\Image\InvalidImageInputException;
use Shared\Application\Port\Outbound\{FileStoragePort, ImageInputValidationPort};

use function sprintf;

/**
 * Service AvatarResizer.
 *
 * Resizes an uploaded image into the four canonical
 * avatar sizes and stores each variant as WebP via the
 * FileStoragePort.
 *
 * @category Infrastructure Service
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
readonly class AvatarResizer
{
  // #region Constants
  /**
   * Constant SIZES.
   *
   * Available avatar sizes in pixels (square).
   *
   * @since 1.0.0
   *
   * @var list<int>
   */
  public const array SIZES = [256, 128, 64, 32];

  /**
   * Constant STORAGE_BASE.
   *
   * Base path inside the file storage for avatar files.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string STORAGE_BASE = 'avatars';

  /**
   * Constant WEBP_QUALITY.
   *
   * WebP encoding quality (0–100).
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int WEBP_QUALITY = 85;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param FileStoragePort $fileStorage port used to persist image variants
   * @param ImageInputValidationPort $imageInputValidation bounds the source before decoding
   */
  public function __construct(
    private FileStoragePort $fileStorage,
    private ImageInputValidationPort $imageInputValidation,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method resize.
   *
   * Generates all size variants from the raw source image
   * contents and stores each one as a WebP file. Validation, single-frame
   * decoding and all encodings succeed before existing variants are replaced.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier used as storage key
   * @param string $sourceContents the raw binary content of the uploaded image
   *
   * @return void no return value
   *
   * @throws InvalidImageInputException if the source is invalid or exceeds the image budget
   */
  public function resize(string $userId, string $sourceContents): void
  {
    $this->imageInputValidation->validate($sourceContents);
    $manager = new ImageManager(new Driver(), decodeAnimation: false);

    try {
      $sourceImage = $manager->read($sourceContents, BinaryImageDecoder::class);
    } catch (DecoderException $exception) {
      throw new InvalidImageInputException('Unable to decode the uploaded image.', previous: $exception);
    }

    $variants = [];
    foreach (self::SIZES as $size) {
      $image = clone $sourceImage;
      $image->cover($size, $size);
      $variants[$size] = (string) $image->toWebp(self::WEBP_QUALITY);
    }

    foreach ($variants as $size => $contents) {
      $this->fileStorage->write(
        path: sprintf('%s/%s/%d.webp', self::STORAGE_BASE, $userId, $size),
        contents: $contents,
      );
    }
  }

  /**
   * Method delete.
   *
   * Removes all stored avatar variants for a user.
   * Silently skips missing files.
   *
   * @since 1.0.0
   *
   * @param string $userId the user identifier
   *
   * @return void no return value
   */
  public function delete(string $userId): void
  {
    foreach (self::SIZES as $size) {
      $path = sprintf('%s/%s/%d.webp', self::STORAGE_BASE, $userId, $size);

      if ($this->fileStorage->exists($path)) {
        $this->fileStorage->delete($path);
      }
    }
  }
  // #endregion
}
