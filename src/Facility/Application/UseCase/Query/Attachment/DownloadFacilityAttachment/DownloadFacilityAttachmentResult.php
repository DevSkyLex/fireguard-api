<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\DownloadFacilityAttachment;

use Shared\Application\Message\ResultMessage;

/** Result DownloadFacilityAttachmentResult. @category Result */
final readonly class DownloadFacilityAttachmentResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(public string $contents, public string $fileName, public string $mimeType)
  {
  }
  // #endregion
}
