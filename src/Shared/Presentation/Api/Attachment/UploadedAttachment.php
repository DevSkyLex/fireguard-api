<?php

declare(strict_types=1);

namespace Shared\Presentation\Api\Attachment;

/**
 * DTO UploadedAttachment.
 *
 * Presentation-layer read model for a validated multipart file upload,
 * produced by {@see MultipartAttachmentGuard} and consumed by every
 * module's `<Module>MediaProcessor`.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UploadedAttachment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $fileName the original file name
   * @param string $contents the file contents
   * @param string $mimeType the sniffed MIME type
   * @param int $size the file size in bytes
   * @param ?string $label the optional human-readable label
   */
  public function __construct(
    public string $fileName,
    public string $contents,
    public string $mimeType,
    public int $size,
    public ?string $label = null,
  ) {
  }
  // #endregion
}
