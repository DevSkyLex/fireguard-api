<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

use Shared\Application\Contract\Attachment\AttachmentDownload;

/**
 * Port AttachmentDownloadPort.
 *
 * Publishes the generic attachment-disposition header policy to owning download endpoints.
 *
 * @category Port
 */
interface AttachmentDownloadPort
{
  /**
   * Method download.
   *
   * @param string $contents authorized attachment bytes
   * @param string $fileName original client file name
   * @param string $mimeType stored MIME type
   *
   * @return AttachmentDownload private download bytes and headers
   */
  public function download(string $contents, string $fileName, string $mimeType): AttachmentDownload;
}
