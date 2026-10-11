<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Attachment;

/**
 * DTO UploadedAttachment.
 *
 * Pure validated multipart upload data passed to application commands.
 *
 * @category DTO
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
