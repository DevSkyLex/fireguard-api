<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Attachment\DownloadMessageAttachment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DownloadMessageAttachmentResult.
 *
 * Carries the stored bytes plus the metadata the Presentation layer needs to
 * shape the download response (`Content-Type`, `Content-Disposition`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DownloadMessageAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $contents the raw file contents
   * @param string $fileName the original client file name
   * @param string $mimeType the stored MIME type
   * @param int $size the stored size in bytes
   */
  public function __construct(
    public string $contents,
    public string $fileName,
    public string $mimeType,
    public int $size,
  ) {
  }
  // #endregion
}
