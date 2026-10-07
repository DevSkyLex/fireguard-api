<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\EquipmentTypeCatalog\PatchEquipmentType;

use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase PatchEquipmentTypeResult.
 *
 * @category UseCase
 */
final readonly class PatchEquipmentTypeResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param EquipmentTypeDescriptor $type stored descriptor
   *
   * @return void
   */
  public function __construct(public EquipmentTypeDescriptor $type)
  {
  }
  // #endregion
}
