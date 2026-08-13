<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInterventionAttachmentsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionAttachmentsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $userId,
    public string $interventionId,
    public ?string $workItemId = null,
  ) {
  }
  // #endregion
}
