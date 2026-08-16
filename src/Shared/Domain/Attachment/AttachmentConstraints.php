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

  /**
   * Constant MAX_ATTACHMENTS_PER_PARENT.
   *
   * The maximum number of attachments a single parent record may carry
   * (an intervention, an inspection, a facility, an equipment, a message).
   * Without it the per-file size cap bounds nothing: storage grows without
   * limit per tenant, and the unpaginated `findBy<Parent>Id` listings that
   * feed the detail screens degrade with the row count.
   *
   * @since 1.0.0
   *
   * @var int MAX_ATTACHMENTS_PER_PARENT
   */
  public const int MAX_ATTACHMENTS_PER_PARENT = 25;
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
    self::validateSize($size);

    if (!in_array($mimeType, self::allowedMimeTypes(), true)) {
      throw InvalidAttachmentException::forMimeType($mimeType);
    }
  }

  /**
   * Method validateSize.
   *
   * @static
   *
   * Validates only the size half of the policy — used by a caller that
   * substitutes its own MIME allow-list (e.g. a kind-specific one narrower
   * or wider than {@see self::allowedMimeTypes()}) while still enforcing the
   * shared size cap.
   *
   * @since 1.1.0
   *
   * @param int $size the uploaded size in bytes
   *
   * @throws InvalidAttachmentException when the size is rejected
   */
  public static function validateSize(int $size): void
  {
    if ($size > self::MAX_SIZE_BYTES) {
      throw InvalidAttachmentException::forSize($size, self::MAX_SIZE_BYTES);
    }
  }

  /**
   * Method validateCount.
   *
   * @static
   *
   * Asserts that one more attachment may be added to a parent that already
   * carries `$currentCount` of them. Called from the use-case handler — the
   * count is a business rule over persisted state, not something the
   * multipart guard can see.
   *
   * @since 1.0.0
   *
   * @param int $currentCount the number of attachments the parent already carries
   *
   * @throws InvalidAttachmentException when the parent is already at the cap
   */
  public static function validateCount(int $currentCount): void
  {
    if ($currentCount >= self::MAX_ATTACHMENTS_PER_PARENT) {
      throw InvalidAttachmentException::forCount($currentCount, self::MAX_ATTACHMENTS_PER_PARENT);
    }
  }
  // #endregion
}
