<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInterventionAttachmentsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionAttachmentsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Identifies the caller, intervention and optional work-item filter.
   *
   * @access public
   *
   * @param string $userId user requesting the attachment list
   * @param string $interventionId intervention whose attachments are listed
   * @param ?string $workItemId optional work-item identifier used to narrow the list
   *
   * @return void
   */
  public function __construct(
    public string $userId,
    public string $interventionId,
    public ?string $workItemId = null,
  ) {
  }
  // #endregion
}
