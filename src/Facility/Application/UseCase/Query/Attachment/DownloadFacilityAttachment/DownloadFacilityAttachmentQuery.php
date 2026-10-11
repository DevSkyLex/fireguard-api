<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Attachment\DownloadFacilityAttachment;

use Shared\Application\Message\QueryMessage;

/** Query DownloadFacilityAttachmentQuery. @category Query */
final readonly class DownloadFacilityAttachmentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(public string $userId, public string $attachmentId)
  {
  }
  // #endregion
}
