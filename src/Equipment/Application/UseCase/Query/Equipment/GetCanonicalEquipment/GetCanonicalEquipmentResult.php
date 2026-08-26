<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment;

use Equipment\Application\Contract\Equipment\CanonicalEquipmentView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetCanonicalEquipmentResult.
 *
 * `view` is null when nothing matches — the caller decides the status,
 * because "absent" and "outside your scope" must answer alike here.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCanonicalEquipmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public ?CanonicalEquipmentView $view = null,
  ) {
  }
  // #endregion
}
