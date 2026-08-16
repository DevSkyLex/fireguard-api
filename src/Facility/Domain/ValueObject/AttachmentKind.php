<?php

declare(strict_types=1);

namespace Facility\Domain\ValueObject;

/**
 * Enum AttachmentKind.
 *
 * Distinguishes a plain document attachment from a floor plan. Only a
 * `FLOOR_PLAN` attachment may carry image dimensions or be promoted to the
 * facility's primary plan (see `FacilityAttachment`).
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum AttachmentKind: string
{
  case DOCUMENT = 'document';

  case FLOOR_PLAN = 'floor_plan';

  // #region Methods
  /**
   * Method values.
   *
   * @static
   *
   * @since 1.0.0
   *
   * @return list<string> every supported attachment kind value
   */
  public static function values(): array
  {
    return [
      self::DOCUMENT->value,
      self::FLOOR_PLAN->value,
    ];
  }

  /**
   * Method allowedMimeTypes.
   *
   * The MIME allow-list for this kind. A `DOCUMENT` carries no kind-specific
   * restriction beyond the shared {@see \Shared\Domain\Attachment\AttachmentConstraints}
   * policy (`null`); a `FLOOR_PLAN` is restricted to the raster/vector image
   * types a plan is realistically authored in — narrower than
   * {@see \Shared\Domain\Attachment\AttachmentCategory::IMAGE} (no `image/gif`)
   * but wider (`image/svg+xml`, which the shared category deliberately
   * excludes for generic uploads).
   *
   * @since 1.0.0
   *
   * @return ?list<string> the accepted MIME types, or null when unrestricted
   */
  public function allowedMimeTypes(): ?array
  {
    return match ($this) {
      self::DOCUMENT => null,
      self::FLOOR_PLAN => ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
    };
  }
  // #endregion
}
