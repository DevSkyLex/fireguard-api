<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail;

use Equipment\Application\Contract\Equipment\CanonicalEquipmentView;
use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Shared\Application\Message\ResultMessage;

/** Carries business detail and its matching canonical revision. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class GetEquipmentDetailResult implements ResultMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public GetEquipmentResult $equipment, public CanonicalEquipmentView $canonical)
  {
  }
}
