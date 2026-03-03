<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipments;

use Equipment\Application\UseCase\Query\Equipment\GetEquipment\GetEquipmentResult;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListEquipmentsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<GetEquipmentResult> $equipments the equipment list
   */
  public function __construct(
    public array $equipments,
  ) {
  }
  // #endregion
}
