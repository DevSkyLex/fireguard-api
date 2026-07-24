<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Attachment\ListInspectionAttachments;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListInspectionAttachmentsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionAttachmentsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, nonConformityId: ?string, fileName: string, mimeType: string, size: int, label: ?string, uploadedAt: string}> $attachments
   */
  public function __construct(
    public array $attachments,
  ) {
  }
  // #endregion
}
