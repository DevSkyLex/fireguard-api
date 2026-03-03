<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\AddTagToEquipment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase AddTagToEquipmentResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AddTagToEquipmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $tagId,
    public string $tagName,
    public string $organizationId,
  ) {
  }
  // #endregion
}
