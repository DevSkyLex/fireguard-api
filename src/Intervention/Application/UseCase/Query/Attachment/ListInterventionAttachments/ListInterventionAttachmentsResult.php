<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInterventionAttachmentsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInterventionAttachmentsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, fileName: string, mimeType: string, size: int, label: ?string, uploadedAt: string, workItemId: ?string}> $attachments
   */
  public function __construct(
    public array $attachments,
  ) {
  }
  // #endregion
}
