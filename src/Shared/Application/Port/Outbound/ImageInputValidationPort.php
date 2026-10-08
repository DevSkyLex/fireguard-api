<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Application\Contract\Image\InvalidImageInputException;

/**
 * Interface ImageInputValidationPort
 *
 * Bounds compressed image bytes and source dimensions before a raster decoder
 * runs. Successful header validation does not replace decoding validation.
 *
 * @category Port
 */
interface ImageInputValidationPort
{
  // #region Constants
  /**
   * Constant MAX_BYTES
   *
   * Maximum compressed upload size in bytes (5 MiB).
   */
  public const int MAX_BYTES = 5 * 1024 * 1024;

  /**
   * Constant MAX_DIMENSION
   *
   * Maximum source width or height in pixels.
   */
  public const int MAX_DIMENSION = 4096;

  /**
   * Constant MAX_PIXELS
   *
   * Maximum decoded raster area, independent of its compressed size.
   */
  public const int MAX_PIXELS = 2048 * 2048;
  // #endregion

  // #region Methods
  /**
   * Method validate
   *
   * Inspects the binary header without allocating a decoded raster. Only JPEG,
   * PNG, WebP and GIF sources with positive, bounded dimensions are accepted.
   * GIF screen and first-frame dimensions are checked separately, and the frame
   * must be contained within the logical screen.
   *
   * @access public
   *
   * @param string $contents the raw compressed image bytes
   *
   * @return void no return value
   *
   * @throws InvalidImageInputException if the source is malformed or exceeds the budget
   */
  public function validate(string $contents): void;
  // #endregion
}
