<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\EquipmentTypeCatalog\ListEquipmentTypes;

use Equipment\Application\Contract\EquipmentTypeCatalog\EquipmentTypeDescriptor;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListEquipmentTypesResult.
 *
 * @category UseCase
 */
final readonly class ListEquipmentTypesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param list<EquipmentTypeDescriptor> $types ordered descriptors
   *
   * @return void
   */
  public function __construct(public array $types)
  {
  }
  // #endregion
}
