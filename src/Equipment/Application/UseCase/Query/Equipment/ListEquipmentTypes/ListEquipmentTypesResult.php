<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListEquipmentTypes;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListEquipmentTypesResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListEquipmentTypesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the ListEquipmentTypesResult class.
   *
   * @since 1.0.0
   *
   * @param list<GetEquipmentTypeResult> $types the equipment type results
   */
  public function __construct(
    public array $types,
  ) {
  }
  // #endregion
}
