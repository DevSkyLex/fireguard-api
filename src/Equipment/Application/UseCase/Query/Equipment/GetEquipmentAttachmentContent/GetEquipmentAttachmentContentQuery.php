<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentAttachmentContent;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetEquipmentAttachmentContentQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetEquipmentAttachmentContentQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public string $attachmentId,
  ) {
  }
  // #endregion
}
