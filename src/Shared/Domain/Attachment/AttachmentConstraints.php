<?php

declare(strict_types=1);

namespace Shared\Domain\Attachment;

use function in_array;

/**
 * ValueObject AttachmentConstraints.
 *
 * Single source of truth for the MIME-type and size policy applied to
 * every generalized module attachment (Inspection, Intervention, Facility).
 * Equipment attachments predate this kernel and are not required to route
 * through it (see `src/Shared/MODULE.md`), but new consumers must.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AttachmentConstraints
{
  // #region Constants
  /**
   * Constant MAX_SIZE_BYTES.
   *
   * The maximum accepted attachment size, in bytes (10 MB — aligned with
   * the existing equipment attachment / avatar / logo precedent).
   *
   * @since 1.0.0
   *
   * @var int MAX_SIZE_BYTES
   */
  public const int MAX_SIZE_BYTES = 10 * 1024 * 1024;
  // #endregion

  // #region Methods
  /**
   * Method allowedMimeTypes.
   *
   * @static
   *
   * Lists every MIME type accepted across all attachment categories.
   *
   * @since 1.0.0
   *
   * @return list<string> the accepted MIME types
   */
  public static function allowedMimeTypes(): array
  {
    return [
      ...AttachmentCategory::IMAGE->allowedMimeTypes(),
      ...AttachmentCategory::DOCUMENT->allowedMimeTypes(),
    ];
  }

  /**
   * Method validate.
   *
   * @static
   *
   * Validates an uploaded attachment's MIME type and size against the
   * shared policy. Size must be checked BEFORE any file contents are read
   * into memory by the caller.
   *
   * @since 1.0.0
   *
   * @param string $mimeType the uploaded MIME type
   * @param int $size the uploaded size in bytes
   *
   * @throws InvalidAttachmentException when the MIME type or size is rejected
   */
  public static function validate(string $mimeType, int $size): void
  {
    if ($size > self::MAX_SIZE_BYTES) {
      throw InvalidAttachmentException::forSize($size, self::MAX_SIZE_BYTES);
    }

    if (!in_array($mimeType, self::allowedMimeTypes(), true)) {
      throw InvalidAttachmentException::forMimeType($mimeType);
    }
  }
  // #endregion
}
