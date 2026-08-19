<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentAttachmentContent;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetEquipmentAttachmentContentResult.
 *
 * Carries the attachment's stored bytes alongside the metadata the
 * Presentation layer needs to build the HTTP response (`Content-Type`,
 * `Content-Disposition`, `Content-Length`). Presentation turns this into a
 * `Response`; it performs no decision of its own beyond that translation.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentAttachmentContentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $fileName the original file name
   * @param string $mimeType the stored MIME type
   * @param int $size the stored size in bytes
   * @param string $contents the file's raw bytes
   */
  public function __construct(
    public string $fileName,
    public string $mimeType,
    public int $size,
    public string $contents,
  ) {
  }
  // #endregion
}
