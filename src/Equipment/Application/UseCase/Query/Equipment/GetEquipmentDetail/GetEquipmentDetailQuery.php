<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetEquipmentDetail;

use Shared\Application\Message\QueryMessage;

/** Requests a complete equipment detail projection. @category UseCase @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final readonly class GetEquipmentDetailQuery implements QueryMessage
{
  /**
   * @since 1.0.0
   */
  public function __construct(public string $equipmentId, public ?string $organizationId = null, public ?string $userId = null)
  {
  }
}
