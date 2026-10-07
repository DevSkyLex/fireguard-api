<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\EquipmentTypeCatalog\ListEquipmentTypes;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListEquipmentTypesQuery.
 *
 * @category UseCase
 */
final readonly class ListEquipmentTypesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @access public
   *
   * @param string $organizationId catalog scope
   *
   * @return void
   */
  public function __construct(public string $organizationId)
  {
  }
  // #endregion
}
