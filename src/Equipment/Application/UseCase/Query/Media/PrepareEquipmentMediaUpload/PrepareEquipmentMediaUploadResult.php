<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Media\PrepareEquipmentMediaUpload;

use Equipment\Application\Contract\Media\EquipmentMediaView;
use Shared\Application\Message\ResultMessage;

/** Carries resolved upload scope and previously acknowledged media. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class PrepareEquipmentMediaUploadResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $equipmentId, public string $organizationId, public ?string $interventionId, public ?EquipmentMediaView $replay = null)
  {
  }
}
