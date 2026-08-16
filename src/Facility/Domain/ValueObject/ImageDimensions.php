<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

use function getimagesizefromstring;
use function is_array;
use function preg_match;
use function round;

/**
 * ValueObject ImageDimensions.
 *
 * Probes the pixel dimensions of an already-in-memory image payload — no
 * filesystem or network access, so this stays a pure Domain concern. Raster
 * formats (PNG, JPEG, WebP) are read via `getimagesizefromstring()`. SVG is
 * read by regex over the opening `<svg>` tag rather than a full XML parse,
 * deliberately: an SVG is untrusted input and even a hardened XML parser is
 * a larger attack surface than two attribute look-ups. SVG dimensions come
 * from the `width`/`height` attributes (unitless or `px`) when present, else
 * from `viewBox`; a plan authored with percentage or other CSS-unit
 * dimensions (`cm`, `in`, `em`, …), or with none of the above, yields no
 * dimensions at all — `fromContents()` returns null rather than guessing.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ImageDimensions
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $width the width in pixels
   * @param int $height the height in pixels
   */
  private function __construct(
    private int $width,
    private int $height,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromContents.
   *
   * @static
   *
   * Probes the pixel dimensions of an uploaded image's raw bytes.
   *
   * @since 1.0.0
   *
   * @param string $contents the raw file bytes
   * @param string $mimeType the sniffed MIME type
   *
   * @return ?self the probed dimensions, or null when they could not be determined
   */
  public static function fromContents(string $contents, string $mimeType): ?self
  {
    if ('image/svg+xml' === $mimeType) {
      return self::fromSvg($contents);
    }

    $info = @getimagesizefromstring($contents);
    if (!is_array($info)) {
      return null;
    }

    $width = (int) $info[0];
    $height = (int) $info[1];
    if ($width <= 0 || $height <= 0) {
      return null;
    }

    return new self($width, $height);
  }

  /**
   * Method width.
   *
   * @since 1.0.0
   */
  public function width(): int
  {
    return $this->width;
  }

  /**
   * Method height.
   *
   * @since 1.0.0
   */
  public function height(): int
  {
    return $this->height;
  }

  /**
   * Method fromSvg.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @param string $contents the raw SVG bytes
   *
   * @return ?self the probed dimensions, or null when neither the width/height
   *               attributes nor a viewBox could be read
   */
  private static function fromSvg(string $contents): ?self
  {
    if (1 !== preg_match('/<svg\b[^>]*>/i', $contents, $tagMatch)) {
      return null;
    }

    $tag = $tagMatch[0];
    $width = self::svgAttribute($tag, 'width');
    $height = self::svgAttribute($tag, 'height');

    if (null !== $width && null !== $height) {
      return new self($width, $height);
    }

    if (1 === preg_match('/\bviewBox="\s*[\d.\-]+\s+[\d.\-]+\s+([\d.]+)\s+([\d.]+)\s*"/i', $tag, $viewBoxMatch)) {
      $viewBoxWidth = (int) round((float) $viewBoxMatch[1]);
      $viewBoxHeight = (int) round((float) $viewBoxMatch[2]);

      if ($viewBoxWidth > 0 && $viewBoxHeight > 0) {
        return new self($viewBoxWidth, $viewBoxHeight);
      }
    }

    return null;
  }

  /**
   * Method svgAttribute.
   *
   * @static
   *
   * Reads a unitless or `px` numeric `<svg>` attribute.
   *
   * @since 1.0.0
   *
   * @param string $tag the opening `<svg …>` tag
   * @param string $attribute the attribute name (`width` or `height`)
   *
   * @return ?int the attribute value in pixels, or null when absent or non-numeric
   */
  private static function svgAttribute(string $tag, string $attribute): ?int
  {
    if (1 !== preg_match('/\b' . $attribute . '="([\d.]+)(?:px)?"/i', $tag, $match)) {
      return null;
    }

    $value = (int) round((float) $match[1]);

    return $value > 0 ? $value : null;
  }
  // #endregion
}
