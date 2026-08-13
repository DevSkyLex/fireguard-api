<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\GetInterventionAttachmentContent;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetInterventionAttachmentContentQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionAttachmentContentQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $userId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
