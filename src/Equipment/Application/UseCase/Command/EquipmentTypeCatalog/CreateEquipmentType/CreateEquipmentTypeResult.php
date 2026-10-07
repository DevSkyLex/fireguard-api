<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\EquipmentTypeCatalog\CreateEquipmentType;

use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase CreateEquipmentTypeResult.
 *
 * @category UseCase
 */
final readonly class CreateEquipmentTypeResult implements ResultMessage
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
