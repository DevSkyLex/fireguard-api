<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipmentAttachments;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListEquipmentAttachmentsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentAttachmentsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, fileName: string, mimeType: string, size: int, label: ?string, uploadedAt: string}> $attachments
   */
  public function __construct(
    public array $attachments,
  ) {
  }
  // #endregion
}
