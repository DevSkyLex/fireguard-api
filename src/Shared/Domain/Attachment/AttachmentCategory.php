<?php

declare(strict_types=1);

namespace Shared\Domain\Attachment;

/**
 * ValueObject AttachmentCategory.
 *
 * Groups the MIME types accepted for a generalized module attachment
 * (Inspection, Intervention, Facility, Equipment) into broad categories.
 * This is the single source of truth consumed by
 * {@see AttachmentConstraints} — no module maintains its own MIME allow-list.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum AttachmentCategory: string
{
  // #region Cases
  case IMAGE = 'image';

  case DOCUMENT = 'document';
  // #endregion

  // #region Methods
  /**
   * Method allowedMimeTypes.
   *
   * Lists the MIME types accepted for this category.
   *
   * @since 1.0.0
   *
   * @return list<string> the accepted MIME types
   */
  public function allowedMimeTypes(): array
  {
    return match ($this) {
      self::IMAGE => ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
      self::DOCUMENT => ['application/pdf'],
    };
  }
  // #endregion
}
