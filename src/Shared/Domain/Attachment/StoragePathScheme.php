<?php

declare(strict_types=1);

namespace Shared\Domain\Attachment;

use function basename;
use function sprintf;
use function str_replace;

/**
 * Service StoragePathScheme.
 *
 * Builds the deterministic, path-traversal-safe storage key shared by
 * every module attachment: `{module}/{parentId}/attachments/{attachmentId}_{fileName}`.
 * Mirrors the scheme `Equipment\Application\UseCase\Command\Equipment\AddAttachment\AddAttachmentHandler`
 * inlines today, so every attachment (equipment included) coexists under one
 * storage root/bucket.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class StoragePathScheme
{
  // #region Methods
  /**
   * Method build.
   *
   * @static
   *
   * Builds the storage key for an attachment.
   *
   * @since 1.0.0
   *
   * @param string $module the owning module segment (e.g. `inspection`, `intervention`, `facility`)
   * @param string $parentId the owning resource identifier
   * @param string $attachmentId the attachment identifier
   * @param string $fileName the original (untrusted) file name
   *
   * @return string the storage key
   */
  public static function build(string $module, string $parentId, string $attachmentId, string $fileName): string
  {
    return sprintf(
      '%s/%s/attachments/%s_%s',
      $module,
      $parentId,
      $attachmentId,
      self::sanitizeFileName($fileName),
    );
  }

  /**
   * Method sanitizeFileName.
   *
   * @static
   *
   * Strips any directory component from the given file name so a hostile
   * `../../etc/passwd`-style value can never escape the attachment prefix.
   *
   * @since 1.0.0
   *
   * @param string $fileName the untrusted file name
   *
   * @return string the sanitized base file name
   */
  private static function sanitizeFileName(string $fileName): string
  {
    return basename(str_replace('\\', '/', $fileName));
  }
  // #endregion
}
