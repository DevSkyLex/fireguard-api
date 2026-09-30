<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\GetInterventionAttachmentContent;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInterventionAttachmentContentQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionAttachmentContentQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the caller and attachment whose content is requested.
   *
   * @access public
   *
   * @param string $userId user requesting the attachment content
   * @param string $attachmentId attachment identifier to retrieve
   *
   * @return void
   */
  public function __construct(
    public string $userId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
